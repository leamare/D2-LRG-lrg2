<?php

function player_card($player_id) {
  global $report;
  $pname = player_name($player_id);
  $pinfo = $report['players_additional'][$player_id];

  if(isset($report['regions_data'])) {
    $regions = [];
    foreach($report['regions_data'] as $rid => $region) {
      if(isset($region['players_summary'][$player_id])) {
        $regions[] = $rid;
      }
    }
  }

  $player = [
    "player_id" => $player_id,
    "player_name" => $pname,
    "team_id" => isset($report['teams']) && isset($pinfo['team']) && isset($report['teams'][ $pinfo['team'] ]) ? $pinfo['team'] : null,
    "matches" => $pinfo['matches'] ?? 0,
    "wins" => $pinfo['won'] ?? 0, 
    "winrate" => round(($pinfo['won'] ?? 0)*100/($pinfo['matches'] ?? 1), 2),
    "gpm" => $pinfo['gpm'] ?? 0,
    "xpm" => $pinfo['xpm'] ?? 0,
    "hero_pool" => $pinfo['hero_pool_size'] ?? 0
  ];

  if (isset($regions)) $player['regions'] = $regions;

  # heroes
  $heroes = [];
  foreach($pinfo['heroes'] as $hero) {
    $heroes[ $hero['heroid'] ] = [
      "matches" => $hero['matches'],
      "wins" => $hero['wins']
    ];
  }

  # positions
  $positions = [];
  foreach($pinfo['positions'] as $position) {
    $positions[ $position["core"].".".$position["lane"] ] = [
      "matches" => $position['matches'],
      "wins" => $position['wins']
    ];
  }

  $player['heroes'] = $heroes;
  $player['positions'] = $positions;

  return $player;
}
