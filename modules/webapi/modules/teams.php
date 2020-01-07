<?php 

$endpoints['teams'] = function($mods, $vars, &$report) use (&$endpoints) {
  if (in_array("cards", $mods)) {
    $res = [];
    $tids = array_keys($report['teams']);
    foreach($tids as $tid) {
      $res[] = team_name($tid);
    }
    return $res;
  }
  return $endpoints['teams_raw']($mods, $vars, $report);
};
