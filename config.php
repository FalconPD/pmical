<?php

return array(
  //This array tells pmical what your calendars are called and where to
  //find them
  'calendars' => array(
    'Field Trips' => 'calendars/ft.ics',
    'US Holidays' => 'calendars/us_en.ics',
    'Test' => 'calendars/test.ics',
  ),

  //From here down shouldn't need to be changed
  'emptyCalendar' => array(
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    "PRODID:-//Ryan Tolboom//pmical//EN",
    "URL:http://myURL.here",
    "X-WR-CALNAME:%name%",
    "CALSCALE:GREGORI",
    "METHOD:PUBLISH",
    "END:VCALENDAR",
  ),
  'newEvent' => array(
    "BEGIN:VEVENT",
    "UID:%uid%",
    "DTSTAMP:%dtstamp%",
    "DTSTART:%dtstart%",
    "DTEND:%dtend%",
    "DESCRIPTION:%description%",
    "SUMMARY:%summary%",
    "END:VEVENT",
    "END:VCALENDAR",
  ),
);

?>
