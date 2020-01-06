<?php 

$endpoints['pvp'] = function($mods, $vars, &$report) {
  if (isset($vars['team'])) {
    throw new \Exception("No team allowed");
  } else if (isset($vars['region'])) {
    throw new \Exception("No region allowed");
  }

  $winrates = [];
  if (isset($report['players_additional'])) {
    foreach($report['players_additional'] as $id => $player) {
      $winrates[$id]['winrate'] = $player['won']/$player['matches'];
    }
  }

  $pvp = rg_generator_pvp_unwrap_data($report['pvp'], $winrates, false);

  if (isset($vars['playerid'])) {
    return $pvp[ $vars['playerid'] ];
  }
  return $pvp;
};
