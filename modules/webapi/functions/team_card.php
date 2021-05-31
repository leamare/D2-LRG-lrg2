<?php

function team_card($tid) {
  global $report;
  global $meta;

  if(!isset($report['teams'])) return null;

  if (empty($report['teams'][$tid]['averages'])) {
    $team = [
      "team_id" => $tid,
      "team_name" => team_name($tid),
      "matches" => $report['teams'][$tid]['matches_total'],
      "wins" => $report['teams'][$tid]['wins'],
      "winrate" => round($report['teams'][$tid]['wins']*100/$report['teams'][$tid]['matches_total']),
    ];

    $roster = [];
    foreach($report['teams'][$tid]['active_roster'] as $player) {
      $p = [
        "player_id" => $player,
        "player_name" => player_name($player),
        "position" => null
      ];
      if (isset($report['players'][$player])) {
        $position = reset($report['players_additional'][$player]['positions']);
        $p['position'] = isset($position['core']) ? $position['core'].".".$position['lane'] : null;
      }
      $roster[] = $p;
    }
    $team['roster'] = $roster;

    return $team;
  }

  $team = [
    "team_id" => $tid,
    "team_name" => team_name($tid),
    "matches" => $report['teams'][$tid]['matches_total'],
    "wins" => $report['teams'][$tid]['wins'],
    "winrate" => round($report['teams'][$tid]['wins']*100/$report['teams'][$tid]['matches_total']),
    "gpm" => $report['teams'][$tid]['averages']['gpm'],
    "xpm" => $report['teams'][$tid]['averages']['xpm'],
    "kills" => round($report['teams'][$tid]['averages']['kills'], 2),
    "deaths" => round($report['teams'][$tid]['averages']['deaths'], 2),
    "assists" => round($report['teams'][$tid]['averages']['assists'], 2),
  ];

  if(isset($report['teams'][$tid]['regions'])) {
    asort($report['teams'][$tid]['regions']);
    $team['regions'] = array_keys($report['teams'][$tid]['regions'])[0];
  }

  $roster = [];
  foreach($report['teams'][$tid]['active_roster'] as $player) {
    if (!isset($report['players'][$player])) continue;
    $p = [
      "player_id" => $player,
      "player_name" => player_name($player)
    ];
    $position = reset($report['players_additional'][$player]['positions']);
    $p['position'] = isset($position['core']) ? $position['core'].".".$position['lane'] : null;
    $roster[] = $p;
  }
  $team['roster'] = $roster;

  if (isset($report['teams'][$tid]['pickban'])) {
    $top_heroes = [];

    $heroes = $report['teams'][$tid]['pickban'];
    uasort($heroes, function($a, $b) {
      if($a['matches_picked'] == $b['matches_picked']) return 0;
      else return ($a['matches_picked'] < $b['matches_picked']) ? 1 : -1;
    });

    $counter = 0;
    foreach($heroes as $hid => $stats) {
      if($counter > 3) break;
      $top_heroes[] = [
        "hero_id" => $hid,
        "matches_picked" => $stats['matches_picked'],
        "winrate_picked" => round(($stats['winrate_picked'] ?? $stats['wins_picked']/$stats['matches_picked'])*100, 2)
      ];
      $counter++;
    }
    $team['top_heroes'] = $top_heroes;
  }

  if (isset($report['teams'][$tid]['hero_pairs'])) {
    $heroes = $report['teams'][$tid]['hero_pairs'];
    $top_pairs = [];

    $counter = 0;
    foreach($heroes as $stats) {
      if($counter > 2) break;
      $top_pairs[] = [
        "hero_ids" => [ $stats['heroid1'], $stats['heroid2'] ],
        "matches" => $stats['matches'],
        "winrate" => round($stats['winrate']*100, 2)
      ];
      $counter++;
    }
    $team['top_pairs'] = $top_pairs;
  }

  return $team;
}
