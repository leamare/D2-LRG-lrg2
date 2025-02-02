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
      $winrates[$id]['matches'] = $player['matches'];
      $winrates[$id]['winrate'] = $player['won']/$player['matches'];
    }
  }

  $pvp = rg_generator_pvp_unwrap_data($report['pvp'], $winrates, false);

  foreach ($pvp as $srcid => &$pvp_context) {
    if (isset($vars['playerid']) && $vars['playerid'] != $srcid) continue;

    $dt = [
      'ms' => $winrates[ $srcid ]['matches']
    ];

    $pvp_context_cpy = $pvp_context;

    positions_ranking($pvp_context, $dt['ms']);

    uasort($pvp_context, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($pvp_context)['wrank'];
    $max = reset($pvp_context)['wrank'];
  
    foreach ($pvp_context as $elid => $el) {
      $pvp_context[$elid]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
      $pvp_context_cpy[$elid]['winrate'] = 1-$pvp_context_cpy[$elid]['winrate'];
    }

    positions_ranking($pvp_context_cpy, $dt['ms']);

    uasort($pvp_context_cpy, function($a, $b) {
      return $b['wrank'] <=> $a['wrank'];
    });
  
    $min = end($pvp_context_cpy)['wrank'];
    $max = reset($pvp_context_cpy)['wrank'];
  
    foreach ($pvp_context_cpy as $elid => $el) {
      $pvp_context[$elid]['arank'] = 100 * ($el['wrank']-$min) / ($max-$min);
      unset($pvp_context[$elid]['wrank']);

      if (isset($el['expectation']) && !isset($el['deviation'])) {
        $pvp_context[$elid]['deviation'] = $el['matches']-$el['expectation'];
        $pvp_context[$elid]['deviation_pct'] = round(($el['matches']-$el['expectation'])*100/$el['matches'], 2);
      }
    }
  }

  if (isset($vars['playerid'])) {
    return [
      'reference' => [
        'id' => $vars['playerid'],
        'matches' => $winrates[ $vars['playerid'] ]['matches'],
        'wins' => $report['players_additional'][ $vars['playerid'] ]['won'],
        'winrate' => round($winrates[ $vars['playerid'] ]['winrate'], 4),
      ],
      'opponents' => $pvp[ $vars['playerid'] ]
    ];
  }
  return $pvp;
};
