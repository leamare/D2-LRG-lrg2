<?php 

$endpoints['hvh'] = function($mods, $vars, &$report) {
  if (isset($vars['team'])) {
    throw new \Exception("No team allowed");
  } else if (isset($vars['region'])) {
    throw new \Exception("No region allowed");
  }

  $hvh = rg_generator_pvp_unwrap_data($report['hvh'], $report['pickban']);

  foreach ($hvh as $srcid => &$pvp_context) {
    if (isset($vars['heroid']) && $vars['heroid'] != $srcid) continue;

    $dt = [
      'ms' => $report['pickban'][ $srcid ]['matches_picked']
    ];

    $compound_ranking_sort = function($a, $b) use ($dt) {
      return positions_ranking_sort($a, $b, $dt['ms']);
    };
    uasort($pvp_context, $compound_ranking_sort);
    $pvp_context_cpy = $pvp_context;
  
    $increment = 100 / sizeof($pvp_context); $i = 0;
  
    foreach ($pvp_context as $elid => $el) {
      if(isset($last) && $el == $last) {
        $i++;
        $pvp_context[$elid]['rank'] = $last_rank;
      } else
        $pvp_context[$elid]['rank'] = round(100 - $increment*$i++, 2);
      $last = $el;
      $last_rank = $pvp_context[$elid]['rank'];
      
      $pvp_context_cpy[$elid]['winrate'] = 1-$pvp_context_cpy[$elid]['winrate'];
    }

    unset($last);

    uasort($pvp_context_cpy, $compound_ranking_sort);
    $i = 0;
  
    foreach ($pvp_context_cpy as $elid => $el) {
      if(isset($last) && $el == $last) {
        $i++;
        $pvp_context[$elid]['arank'] = $last_rank;
      } else
        $pvp_context[$elid]['arank'] = round(100 - $increment*$i++, 2);
      $last = $el;
      $last_rank = $pvp_context[$elid]['arank'];

      if (isset($el['expectation'])) {
        $pvp_context[$elid]['deviation'] = $el['matches']-$el['expectation'];
        $pvp_context[$elid]['deviation_pct'] = round(($el['matches']-$el['expectation'])*100/$el['matches'], 2);
      }
    }
  
    unset($last);
  }

  if (isset($vars['heroid'])) {
    if (isset($report['hero_laning'])) {
      if (is_wrapped($report['hero_laning'])) {
        $report['hero_laning'] = unwrap_data($report['hero_laning']);
      }

      foreach($report['hero_laning'][$vars['heroid']] as $opid => $hero) {
        if (empty($hvh[$vars['heroid']][$opid])) continue;
        $hvh[$vars['heroid']][$opid]['lane_rate'] = round( ($hero['matches'] ?? 0)/$hvh[$vars['heroid']][$opid]['matches'], 4 );
        $hvh[$vars['heroid']][$opid]['lane_wr'] = $hero['lane_wr'] ?? 0;
      }
    }

    return [
      'reference' => [
        'id' => $vars['heroid'],
        'matches' => $report['pickban'][ $vars['heroid'] ]['matches_picked'],
        'wins' => round($report['pickban'][ $vars['heroid'] ]['matches_picked'] * $report['pickban'][ $vars['heroid'] ]['winrate_picked']),
        'winrate' => $report['pickban'][ $vars['heroid'] ]['winrate_picked'],
      ],
      'opponents' => $hvh[ $vars['heroid'] ]
    ];
  }
  return $hvh;
};
