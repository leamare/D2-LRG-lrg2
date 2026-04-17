<?php

if (!function_exists('array_key_first')) {
  function array_key_first(?array $arr) {
    if (!$arr) return null;
    foreach ($arr as $key => $unused) return $key;
    return null;
  }
}
if (!function_exists('array_key_last')) {
  function array_key_last(?array $arr) {
    if (!$arr || !is_array($arr) || empty($arr)) return null;
    return array_keys($arr)[count($arr) - 1];
  }
}
