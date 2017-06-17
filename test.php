<?php

include('pmical.php');


$saves = 1024;
$deletes = $saves / 2;
$iterations = 16;
$name='Test';

//Create a random date between the beginning of the epoch and now
function randomDateTime()
{
  $date = new DateTime();
  $date->setTimestamp(mt_rand(1, time()));
  return $date;
}

function randomString($length = 10) {
  $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $charactersLength = strlen($characters);
  $randomString = '';
  for ($i = 0; $i < $length; $i++) {
    $randomString .= $characters[rand(0, $charactersLength - 1)];
  }
  return $randomString;
}

function shutDownFunction() {
  print_r(error_get_last());
}

register_shutdown_function('shutDownFunction');

print("Working on calendar: $name.\n");
$uids = array();
$myCal = new pmical($name);
$last_uid = 0;

print("Performing $iterations iterations.\n");
for ($i = 0; $i < $iterations; $i++)
{
  print("Iteration: $i.\n");
  print("Performing $saves saves.\n");
  for ($j = 0; $j < $saves; $j++)
  {
    $uids[$last_uid] = randomString();
    $myCal->save($uids[$last_uid], randomDateTime(), randomDateTime(), randomString(), randomString());
    $last_uid++;
  }
  print("Performing $deletes deletes.\n");
  for ($j = 0; $j < $deletes; $j++)
  {
    $windex = rand(0, $last_uid - 1);
    $myCal->delete($uids[$windex]);
    array_splice($uids, $windex, 1);
    $last_uid--;
  }
}

?>
