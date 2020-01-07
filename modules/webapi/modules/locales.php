<?php 

$endpoints['locales'] = function($mods, $vars, &$report) {
  $strings = [];
  foreach ($vars['gets'] as $loc) {
    if (file_exists(__DIR__ . "/../../../locales/$loc.php"))
      include_once(__DIR__ . "/../../../locales/$loc.php");
  }
  return $strings;
};
