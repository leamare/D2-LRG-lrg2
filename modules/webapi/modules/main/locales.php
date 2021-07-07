<?php 

$endpoints['locales'] = function($mods, $vars, &$report) {
  $strings = [];
  if (empty($vars['gets'])) return null;
  foreach ($vars['gets'] as $loc) {
    if (file_exists(__DIR__ . "/../../../../locales/$loc.php"))
      include(__DIR__ . "/../../../../locales/$loc.php");
  }
  return $strings;
};
