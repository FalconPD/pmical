<?php

class pmical
{
  const NOFILE = 0;
  const INVALIDFILE = 1;
  const FILEOK = 2;
  const DTFORMAT = 'Ymd\THis\Z';
  const HEADERBYTES = 30; //How many bytes to check at the beginning of an ics file
                          //This covers the BEGIN and VERSION statement
  const TAILBYTES = 15; //How many bytes we cut off the end of the file before we append
                        //this removes the END:VCALENDAR\r\n bytes
  const BLOCKSIZE = 1024; 

  //This is a template for an empty calendar
  //According to the RFC a calendar needs at least
  //one object in it, hence the initial calendar creation
  //event
  const EMPTYCAL = "BEGIN:VCALENDAR\r\n" .
                   "VERSION:2.0\r\n" .
                   "PRODID:-//Ryan Tolboom//pmical//EN\r\n" .
                   "URL:http://myURL.here\r\n" .
                   "X-WR-CALNAME:%name%\r\n" .
                   "CALSCALE:GREGORI\r\n" .
                   "METHOD:PUBLISH\r\n" .
                   "BEGIN:VEVENT\r\n" .
                   "UID:0\r\n" .
                   "DTSTAMP:%dtstamp%\r\n" .
                   "DTSTART:%dtstamp%\r\n" .
                   "SUMMARY:Calendar created\r\n" .
                   "END:VEVENT\r\n" .
                   "END:VCALENDAR\r\n";

  //This is a template for a new calendar event
  //Notice how it also includes the END:VCALENDAR at the end
  //this is because we will be appending this info
  //to the end of the calendar file
  const NEWEVENT = "BEGIN:VEVENT\r\n" .
                   "UID:%uid%\r\n" .
                   "DTSTAMP:%dtstamp%\r\n" .
                   "DTSTART:%dtstart%\r\n" .
                   "DTEND:%dtend%\r\n" .
                   "DESCRIPTION:%description%\r\n" .
                   "SUMMARY:%summary%\r\n" .
                   "END:VEVENT\r\n" .
                   "END:VCALENDAR\r\n";

  const BEGINVEVENT = "BEGIN:VEVENT\r\n";
  const ENDVEVENT = "END:VEVENT\r\n";

  private $fileName;
  private $lines;
  private $config;

  public function __construct($name)
  {
    //Load the config
    $this->config = include("config.php");

    //Lookup the location of the calendar file by name
    if (! array_key_exists($name, $this->config['calendars']))
      die("Unable to find calendar: $name\n");
    else
      $this->fileName = $this->config['calendars'][$name];

    switch ($this->checkFile())
    {
      case self::NOFILE: //If we don't have a calendar file yet, make an empty one 

        //Use the template and replace %name%
        $search = array('%name%', '%dtstamp%');
        $replace = array($name, $this->getDtStamp());
        $emptyCal = str_replace($search, $replace, self::EMPTYCAL);

        //Create and write to the file
        $handle = fopen($this->fileName, "w");
        if (! $handle)
          die("Unable to open new calendar file: $this->fileName\n");
        $size = strlen($emptyCal);
        if (fwrite($handle, $emptyCal, $size) != $size) die("Unable to write $size bytes to new calendar file $this->fileName\n");
        fclose($handle);

        break;
      case self::INVALIDFILE:
        die("Invalid calendar file format: $this->fileName\n");
    }
  }

  private function getDtStamp()
  {
    $dtstamp = new DateTime('now');
    $dtstamp = $dtstamp->format(self::DTFORMAT);
    return $dtstamp;
  }

  private function checkFile()
  {
    //Does the file exist?
    if (! file_exists($this->fileName))
      return self::NOFILE;

    //Can we open it?
    $handle = fopen($this->fileName, "r");
    if (! $handle)
      die("Unable to open file: $this->fileName\n");

    //Is the header what we are expecting?
    $header = fread($handle, self::HEADERBYTES);
    if (! $header)
      die("Unable to read header of file: $this->fileName\n");
    if (strncmp($header, self::EMPTYCAL, self::HEADERBYTES))
    {
      fclose($handle);
      return self::INVALIDFILE;
    }

    fclose($handle);
    return self::FILEOK;
  }

  private function read()
  {
    $this->lines = file($this->fileName, FILE_IGNORE_NEW_LINES);
    if (! $this->lines)
      die("Unable to read file: $this->fileName\n");
  }

  private function write()
  {
    $handle = fopen($this->fileName, "w");
    if (! $handle)
      die("Unable to open file: $this->fileName");
    if (! fwrite($handle, implode("\r\n", $this->lines)))
    {
      fclose($handle);
      die("Unable to write to file: $this->fileName\n");
    }
    fclose($handle);
    $this->lines = array();
  }

  //Copy things from one file handle to another in blocks. This prevents us from
  //having to allocate huge arrays for large files
  private function copy($handle1, $handle2, $bytes)
  {
    while ($bytes > 0)
    {
      if ($bytes >= self::BLOCKSIZE)
        $read = self::BLOCKSIZE;
      else
        $read = $bytes;
      $block = fread($handle1, $read);
      if (! $block)
        die("Error reading during copy.\n");
      $write = fwrite($handle2, $block);
      if ($write != $read)
        die("Error writing during copy.\n");
      $bytes -= $write;
    }
  }

  public function delete($uid)
  {
    //Read through the file line by line and get offset for the start and end of
    //the event
    $original = fopen($this->fileName, "r");
    if (! $original)
      die("Unable to open $this->fileName for reading\n");
    if (! flock($original, LOCK_EX))
    {
      fclose($original);
      print("Unable to obtain file lock in delete function.\n");
    }
    clearstatcache();
    $fileSize = filesize($this->fileName);
    $eventStartIndex = 0;
    $eventEndIndex = 0;
    $eventUidIndex = 0;
    $uidLine = "UID:$uid\r\n";
    while  ($line = fgets($original))
    {
      switch ($line)
      {
        case self::BEGINVEVENT:
          $eventStartIndex = ftell($original) - strlen(self::BEGINVEVENT);
          $eventEndIndex = 0;
          break;
        case self::ENDVEVENT:
          $eventEndIndex = ftell($original);
          break;
        case $uidLine:
          $eventUidIndex = ftell($original);
          break;
      }
      if ($eventStartIndex && $eventEndIndex && $eventUidIndex)
        break;
    }

    //If we found something copy the first part of the file to a temp file
    //copy it to a temp file but skip the event we want to delete.
    if ($eventUidIndex)
    {
      if (fseek($original, 0, SEEK_SET))
        die("Unable to seek to beginning of $this->fileName\n");
      $tmpfname = tempnam(sys_get_temp_dir(), 'pmical_');
      if (! $tmpfname)
        die("Uanble to create temporary file name.\n");
      $tmpFile = fopen($tmpfname, "w");
      if (! $tmpFile)
        die("Unable to open temporary calendar file.\n");
      $this->copy($original, $tmpFile, $eventStartIndex);
      if (fseek($original, $eventEndIndex, SEEK_SET))
        die("Unable to seek to $eventEndIndex in $this->fileName\n");
      $this->copy($original, $tmpFile, $fileSize - $eventEndIndex);
      fclose($tmpFile);
      if (! rename($tmpfname, $this->fileName))
        die("Unable to rename $tmpfname to $this->fileName\n");
    }
    flock($original, LOCK_UN);
    fclose($original);
  }

  public function save($uid, $start, $end, $description, $summary)
  {
    //Don't allow multiple events with the same UID
    $this->delete($uid);

    //Put all the datetimes in the correct format
    $dtstart = $start->format(self::DTFORMAT);
    $dtend = $end->format(self::DTFORMAT);

    //Put variables into our template
    $search = array('%uid%', '%dtstamp%', '%dtstart%', '%dtend%', '%description%', '%summary%');
    $replace = array($uid, $this->getDtStamp(), $dtstart, $dtend, $description, $summary);
    $additionalLines = str_replace($search, $replace, self::NEWEVENT);

    //Add our lines to the end of the file, ovewriting the END:CALENDAR line
    $handle = fopen($this->fileName, "c");
    if (! $handle)
      die("Unable to open $this->fileName in save function.\n");
    if (! flock($handle, LOCK_EX))
    {
      fclose($handle);
      die("Unable to obtain lock on $this->fileName in save function.\n");
    }
    $offset = -1 * self::TAILBYTES;
    if (fseek($handle, $offset, SEEK_END))
      die("Unable to seek to $offset in $this->fileName\n");
    if (! fwrite($handle, $additionalLines))
      die("Unable to write to $this->fileName in save function.\n");
    flock($handle, LOCK_UN);
    fclose($handle);
  } 
}

?>
