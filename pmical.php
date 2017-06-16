<?php

class calendar
{
  const DT_FORMAT = "Ymd\THis\Z";

  private $calendars = array(
    "Field Trips"   => "C:\\tmp\\ft.ics",
    "Test Calendar" => "C:\\tmp\\test.ics",
    "US Holidays" => "C:\\tmp\\us_en.ics"
  );
  private $fileName;
  private $lines;

  public function __construct($name)
  {
    //Lookup the location of the calendar file by name
    if (! array_key_exists($name, $this->calendars))
    {
      die("Unable to find calendar: $name");
    }
    else
    {
      $this->fileName = $this->calendars[$name];
    }

    //If we don't have a calendar file yet, make an empty one
    if (! file_exists($this->fileName))
    {
      $this->lines = array();
      $lines[0] = "BEGIN:VCALENDAR\r\n";
      $lines[1] = "VERSION:2.0\r\n";
      $lines[2] = "PRODID:-//Ryan Tolboom//Calendar in Processmaker//EN\r\n";
      $lines[3] = "URL:http://myURL.here\r\n";
      $lines[4] = "X-WR-CALNAME:$name\r\n";
      $lines[5] = "CALSCALE:GREGORI\r\n";
      $lines[6] = "METHOD:PUBLISH\r\n";
      $lines[7] = "END:VCALENDAR";
      $this->write();
    }
  }

  private function read()
  {
    $this->lines = file($this->fileName);
    if (! $this->lines)
      die("Unable to read file: $this->fileName");
  }

  private function write()
  {
    $handle = fopen($this->fileName, "w");
    if (! $handle)
      die("Unable to open file: $this->fileName");
    if (! fwrite($handle, implode("", $this->lines)))
    {
      fclose($handle);
      die("Unable to write to file: $this->fileName");
    }
    fclose($handle);
  }

  //Deletes an event in the lines array by UID and returns TRUE.
  //If the event isn't there it returns FALSE.
  private function deleteFromLines($uid)
  {
    //Go line by line and find our UID. Make a note of the start
    //and end indexes for the event
    $uidLine = "UID:$uid\r\n";
    $eventStartIndex = 0;
    $eventEndIndex = 0;
    $eventUidIndex = 0;
    foreach($this->lines as $index=>$line)
    {
      switch ($line) {
        case "BEGIN:VEVENT\r\n":
          $eventStartIndex = $index;
          $eventEndIndex = 0;
          break;
        case "END:VEVENT\r\n":
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

  public function add($uid, $start, $end, $description, $summary)
  {
    //Don't allow multiple events with the same UID
    $this->deleteFromLines($uid);

    //Load the calendar from the file
    $this->read();
    
    //Remove the END:VCALENDAR from the end of the file
    array_pop($this->lines);

    //Put all the datetimes in the correct format
    $dtstamp = new DateTime("now");
    $dtstamp = $dtstamp->format(self::DT_FORMAT);
    $dtstart = $start->format(self::DT_FORMAT);
    $dtend = $end->format(self::DT_FORMAT);

    //add this event on the end
    array_push($this->lines, "BEGIN:VEVENT\r\n");
    array_push($this->lines, "UID:$uid\r\n");
    array_push($this->lines, "DTSTAMP:$dtstamp\r\n");
    array_push($this->lines, "DTSTART:$dtstart\r\n");
    array_push($this->lines, "DTEND:$dtend\r\n");
    array_push($this->lines, "DESCRIPTION:$description\r\n");
    array_push($this->lines, "SUMMARY:$summary\r\n");
    array_push($this->lines, "END:VEVENT\r\n");
    array_push($this->lines, "END:VCALENDAR\r\n");

    //Write the file again
    $this->write();
  } 
}

?>
