<?php 

$endpoints['locales'] = function($mods, $vars, &$report) {
  global $strings;
  if (empty($vars['gets'])) return null;
  foreach ($vars['gets'] as $loc) {
    include_locale($loc);
  }
  return $strings;
};
