<?php

/**
  *   convert_time
  * args: $time_decimal (float)
  * returns: $time_string (string)
 **/

function convert_time($time_decimal) {
  if ($time_decimal < 0) {
    $str = '-';
    $time_decimal = abs($time_decimal);
  } else {
    $str = '';
  }

  $minutes = floor($time_decimal ?? 0);
  $seconds = floor(($time_decimal-$minutes)*60);
  if ($minutes > 60) {
    $hours = floor($minutes / 60);
    $minutes %= 60;
  }

  if(isset($hours)) $str .= $hours.":";
  $str .= ($minutes < 10 ? "0" : "").$minutes.":".($seconds < 10 ? "0" : "").$seconds;

  return $str;
}

function convert_time_seconds($time) {
  if ($time < 0) {
    $str = '-';
    $time = abs($time);
  } else {
    $str = '';
  }

  $minutes = floor($time / 60);
  $seconds = (int)$time % 60;
  if ($minutes > 60) {
    $hours = floor($minutes / 60);
    $minutes %= 60;
  }

  if(isset($hours)) $str .= $hours.":";
  $str .= ($minutes < 10 ? "0" : "").$minutes.":".($seconds < 10 ? "0" : "").$seconds;

  return $str;
}