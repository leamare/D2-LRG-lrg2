<?php 

$repeatVars['records'] = ['region'];

$endpoints['records'] = function($mods, $vars, &$report) use (&$endpoints) {
  if (isset($vars['region']) && isset($report['regions_data'][ $vars['region'] ])) {
    $context =& $report['regions_data'][ $vars['region'] ];
  } else $context =& $report;

  $data = $context['records'] ?? null;
  $data_ext = $context['records_ext'] ?? [];

  if (empty($data))
    throw new \Exception("Problem occured when fetching records.");
  
  if (!empty($data_ext)) {
    $data_ext = unwrap_data($data_ext);
  }

  $res = [];
  
  foreach ($data as $k => $rec) {
    $res[$k] = [];

    $src = array_merge([ $rec ], $data_ext[$k] ?? []);

    foreach ($src as $v) {
      if (empty($v)) continue;

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

      $res[$k][] = $v;
    }
  }

  return $res;
};