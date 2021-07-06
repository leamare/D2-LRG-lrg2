<?php 

$endpoints['matchcards'] = function($mods, $vars, &$report) {
  $res = [];
  foreach($vars['gets'] as $m) {
    if (isset($report['matches_additional'][$m]))
      $res[] = match_card($m);
  }
  return $res;
};
