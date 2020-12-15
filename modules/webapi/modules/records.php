<?php 

$endpoints['records'] = function($mods, $vars, &$report) {
  // parse mods for region ID
  // check if region persists
  if (isset($vars['region']) && isset($report['regions_data'][ $vars['region'] ])) {
    $data = $report['regions_data'][ $vars['region'] ]['records'] ?? null;
  } else $data = $report['records'] ?? null;

  if (empty($data))
    throw new \Exception("Problem occured when fetching records.");

  $res = [];
  
  foreach ($data as $k => $v) {
    if ($v['matchid'] && !empty($report['match_participants_teams']))
      $v['match_card_min'] = match_card_min($v['matchid']);
    else 
      $v['match_card_min'] = null;

    if (!$v['heroid'])
      $v['heroid'] = null;

    if (!$v['matchid'])
      $v['matchid'] = null;

    if ($v['playerid']) {
      if (strpos($k, '_team')) {
        $v['name'] = team_name($v['playerid']);
        $v['teamid'] = $v['playerid'];
        $v['playerid'] = null;
      } else {
        $v['name'] = player_name($v['playerid']);
      }
    } else {
      $v['playerid'] = null;
    }
    
    $res[$k] = $v;
  }

  return $res;
};