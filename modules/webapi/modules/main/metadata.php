<?php 

$endpoints['metadata'] = function($mods, $vars, &$report) use (&$meta) {
  $res = [];
  if (empty($vars['gets'])) return null;
  foreach ($vars['gets'] as $m) {
    $meta[$m];
    if (isset($meta[$m])) {
      $res[$m] = $meta[$m];
    }
  }
  return $res;
};
