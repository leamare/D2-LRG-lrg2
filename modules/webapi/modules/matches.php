<?php 

$endpoints['matches'] = function($mods, $vars, &$report) {
  $res = [];

  if (isset($vars['team'])) {
    $context =& $report['teams'][ $vars['team'] ]['matches'];
  } else if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ]['matches'];
  } else {
    $context =& $report['matches'];
  }

  $res['matches'] = [];
  foreach ($context as $id => $data) {
    $res['matches'][] = match_card($id);
  }

  return $res;
};
