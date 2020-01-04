<?php 

$endpoints['party_graph'] = function($mods, $vars, &$report) {
  if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ];
  } else {
    $context =& $report;
  }

  $pairs = $context['players_parties_graph'] 
      ?? $context['players_combo_graph'];
  
  $context_pickban = $report['players_additional'];

  $limiter = $context['settings']['limiter_graph']
      ?? $context['settings']['limiter_combograph'];

  $max_wr = 0; $max_games = 0;
  foreach($pairs as $combo) {
    $diff = abs(($combo['winrate'] ?? $combo['wins']/$combo['matches'])-0.5);
    $max_wr = $diff > $max_wr ? $diff : $max_wr;
    $max_games = $combo['matches'] > $max_games ? $combo['matches'] : $max_games;
  }
  $max_wr *= 2;

  $nodes = [];
  foreach($context_pickban as $elid => $el) {
    if($counter++ >= $endp && !has_pair($elid, $pairs)) {
        continue;
    }
    $nodes[] = [
      "player_id" => $elid,
      "matches" => $el['matches'],
      "winrate" => $el['won'] / $el['matches'],
    ];
  }

  $res = [
    "limiter" => $limiter,
    "max_wr" => $max_wr,
    "max_games" => $max_games,
    "nodes" => $nodes,
    "pairs" => $pairs
  ];

  return $res;
};
