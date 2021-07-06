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

    $compound_ranking_sort = function($a, $b) use ($dt) {
      return positions_ranking_sort($a, $b, $dt['ms']);
    };
    uasort($pvp_context, $compound_ranking_sort);
  
    $increment = 100 / sizeof($pvp_context); $i = 0;
  
    foreach ($pvp_context as $elid => $el) {
      if(isset($last) && $el == $last) {
        $i++;
        $pvp_context[$elid]['rank'] = $last_rank;
      } else
        $pvp_context[$elid]['rank'] = round(100 - $increment*$i++, 2);
      $last = $el;
      $last_rank = $pvp_context[$elid]['rank'];
    }
  
    unset($last);
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
