<?php 

$endpoints['matches'] = function($mods, $vars, &$report) use (&$meta) {
  if (empty($report['matches'])) 
    throw new Exception("No matches available for this report");

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
    if (isset($report['matches_additional']) && isset($vars['team']) && isset($vars['region'])) {
      $region = $meta['clusters'][ $report['matches_additional'][$mid]['cluster'] ];
      if ($region != $vars['region']) continue;
    }

    if (isset($vars['playerid']) && isset($vars['heroid'])) {
      $found = false;
      foreach ($report['matches'][$id] as $pl) {
        if ($pl['player'] == $vars['playerid'] && $pl['hero'] == $vars['heroid']) {
          if (isset($vars['team']) && isset($report['match_participants_teams']) && ( $report['match_participants_teams'][$id][ $pl['radiant'] ? 'radiant' : 'dire' ] ?? null ) != $vars['team']) {
            continue;
          }
          if (!check_positions_matches($id, false)) continue;
          $found = true;
          break;
        }
      }
      if (!$found) continue;
    } else if (isset($vars['heroid'])) {
      $found = false;
      foreach ($report['matches'][$id] as $pl) {
        if ($pl['hero'] == $vars['heroid']) {
          if (isset($vars['team']) && isset($report['match_participants_teams']) && ( $report['match_participants_teams'][$id][ $pl['radiant'] ? 'radiant' : 'dire' ] ?? null ) != $vars['team']) {
            continue;
          }
          if (!check_positions_matches($id, true)) continue;
          $found = true;
          break;
        }
      }
      if (!$found) continue;
    } else if (isset($vars['playerid'])) {
      $found = false;
      foreach ($report['matches'][$id] as $slot => $pl) {
        if ($pl['player'] == $vars['playerid']) {
          if (isset($vars['team']) && isset($report['match_participants_teams']) && ( $report['match_participants_teams'][$id][ $pl['radiant'] ? 'radiant' : 'dire' ] ?? null ) != $vars['team']) {
            continue;
          }
          if (!check_positions_matches($id, false)) continue;
          $found = true;
          break;
        }
      }
      if (!$found) continue;
    }

    $res['matches'][] = match_card($id);
  }

  return $res;
};

function check_positions_matches($id, $ishero) {
  global $vars, $report;
  $type = $ishero ? 'hero' : 'player';

  if (isset($report[$type.'_positions_matches']) && isset($vars['position'])) {
    $position = explode('.', $vars['position']);
    if ($position[1]) {
      $pm = $report[$type.'_positions_matches'][ (int)$position[0] ][ (int)$position[1] ][ $vars[$type.'id'] ] ?? [];
    } else {
      $pm = [];
      foreach ($report[$type.'_positions_matches'][ (int)$position[0] ] as $players) {
        if (isset($players[ $vars[$type.'id'] ]))
          $pm = $pm + $players[ $vars[$type.'id'] ];
      }
    }
    if (!in_array($id, $pm))
      return false;
  }
  return true;
}