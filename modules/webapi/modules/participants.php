<?php 

$endpoints['participants'] = function($mods, $vars, &$report) {
  $res = [];

  if (isset($report['teams']) && in_array("teams", $mods)) {
    $teams = true;
  } else {
    $teams = false;
  }

  if (isset($vars['region'])) {
    if ($teams) {
      $context =& $report['regions_data'][ $vars['region'] ]['teams'];
    } else {
      $context =& $report['regions_data'][ $vars['region'] ]['players_summary'];
    }
  } else {
    if ($teams) {
      $context =& $report['teams'];
    } else {
      $context =& $report['players'];
    }
  }

  $res[$teams ? 'teams' : 'players'] = [];
  foreach ($context as $id => $data) {
    if ($teams) {
      $res['teams'][] = team_card($id);
    } else {
      $res['players'][] = player_card($id);
    }
  }

  return $res;
};
