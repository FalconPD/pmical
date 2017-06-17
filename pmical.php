<?php

class pmical
{
  const NOFILE = 0;
  const INVALIDFILE = 1;
  const FILEOK = 2;
  const DTFORMAT = 'Ymd\THis\Z';
  const HEADERBYTES = 30; //How many bytes to check at the beginning of an ics file
                          //This covers the BEGIN and VERSION statement

  //This is a template for an empty calendar
  //According to the RFC a calendar needs at least
  //one object in it, hence the initial calendar creation
  //event
  const EMPTYCAL = <<<EOT
BEGIN:VCALENDAR\r
VERSION:2.0\r
PRODID:-//Ryan Tolboom//pmical//EN\r
URL:http://myURL.here\r
X-WR-CALNAME:%name%\r
CALSCALE:GREGORI\r
METHOD:PUBLISH\r
BEGIN:VEVENT\r
UID:0\r
DTSTAMP:%dtstamp%\r
DTSTART:%dtstamp%\r
SUMMARY:Calendar created\r
END:VEVENT\r
END:VCALENDAR\r\n
EOT;

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
        if (fwrite($handle, $emptyCal, $size) != $size)
          die("Unable to write $size bytes to new calendar file $this->fileName\n");
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

  //Deletes an event in the lines array by UID and returns TRUE.
  //If the event isn't there it returns FALSE.
  private function deleteFromLines($uid)
  {
    //Go line by line and find our UID. Make a note of the start
    //and end indexes for the event
    $uidLine = "UID:$uid";
    $eventStartIndex = 0;
    $eventEndIndex = 0;
    $eventUidIndex = 0;
    foreach($this->lines as $index=>$line)
    {
      switch ($line) {
        case "BEGIN:VEVENT":
          $eventStartIndex = $index;
          $eventEndIndex = 0;
          break;
        case "END:VEVENT":
          $eventEndIndex = $index;
          break;
        case $uidLine:
          $eventUidIndex = $index;
          break;
      }
      if ($eventStartIndex && $eventEndIndex && $eventUidIndex)
      {
        //Remove the lines associated with that UID
        $lines = array_splice($this->lines, $eventStartIndex,
                              $eventEndIndex - $eventStartIndex + 1);
        return TRUE;
      }
    }
    return FALSE;
  }

  //Reads the calendar file, deletes the event (if there), and writes
  //the calendar file (if needed)
  public function delete($uid)
  {
    $this->read();
    if ($this->deleteFromLines($uid))
      $this->write();
  }

  public function save($uid, $start, $end, $description, $summary)
  {
    //Don't allow multiple events with the same UID
    $this->deleteFromLines($uid);

    //Load the calendar from the file
    $this->read();
    
    //Remove the END:VCALENDAR from the end of the file
    array_pop($this->lines);

    //Put all the datetimes in the correct format
    $dtstart = $start->format(self::DTFORMAT);
    $dtend = $end->format(self::DTFORMAT);

    //Put variables into our template
    $search = array('%uid%', '%dtstamp%', '%dtstart%', '%dtend%', '%description%', '%summary%');
    $replace = array($uid, $this->getDtStamp(), $dtstart, $dtend, $description, $summary);
    $additionalLines = str_replace($search, $replace, $this->config['newEvent']);
    
    //add this event on the end
    $this->lines = array_merge($this->lines, $additionalLines);

    //Write the file again
    $this->write();
  } 
}

?>
