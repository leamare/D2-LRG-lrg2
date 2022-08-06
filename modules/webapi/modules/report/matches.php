<?php 

$repeatVars['matches'] = ['team', 'optid'];

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

  if ($vars['team'] ?? false)
    $res['card'] = team_card($vars['team']);

  $res['matches'] = [];

  if (isset($vars['playerid']) || isset($vars['heroid'])) {
    $positions = [];

    for($i=0; $i<=1; $i++) {
      for($j=0; $j<=5; $j++) {
        $list = [];

        if (isset($report['hero_positions_matches']) && isset($vars['heroid'])) {
          if (isset($report['hero_positions_matches'][$i][$j][ $vars['heroid'] ])) {
            $list = $report['hero_positions_matches'][$i][$j][ $vars['heroid'] ];
          }
        }
        if (isset($report['player_positions_matches']) && isset($vars['playerid'])) {
          if (!isset($report['player_positions_matches'][$i][$j][ $vars['playerid'] ])) {
            $list = $list + $report['player_positions_matches'][$i][$j][ $vars['playerid'] ];
          }
        }

        array_unique($list);

        foreach ($list as $mid) {
          $positions[$mid] = "$i.$j";
        }
      }
    }

    if (isset($vars['playerid']) && isset($vars['heroid'])) {

    }
  }

  if (in_array("unteamed", $mods)) {
    $vars['optid'] = 0;
  }

  foreach ($context as $id => $data) {
    if (isset($report['matches_additional']) && isset($vars['team']) && isset($vars['region'])) {
      $region = $meta['clusters'][ $report['matches_additional'][$mid]['cluster'] ];
      if ($region != $vars['region']) continue;
    }

    if (isset($vars['optid']) && isset($report['match_participants_teams'])) {
      if (!in_array($vars['optid'], $report['match_participants_teams'][$id]) && !(!$vars['optid'] && count($report['match_participants_teams'][$id]) < 2)) {
        continue;
      }
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

    $card = match_card($id);
    if (isset($positions)) {
      $card['position'] = $positions[$id] ?? null;
    }
    $res['matches'][] = $card;
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