<?php 

$endpoints['positions'] = function($mods, $vars, &$report) {
  if (in_array("players", $mods))
    $type = "player";
  else 
    $type = "hero";

  // positions context
  if (isset($vars['team'])) {
    $context = $report['teams'][ $vars['team'] ][$type.'hero_positions'];
    $context_total_matches = $report['teams'][ $vars['team'] ]['matches_total'];
  } else if (isset($vars['region'])) {
    $context = $report['regions_data'][ $vars['region'] ][$type.'_positions'];
    $context_total_matches = $report['regions_data'][ $vars['region'] ]['main']["matches_total"];
  } else {
    $context = $report[$type.'_positions'];
    $context_total_matches = $report["random"]["matches_total"];
  }

  if (isset($vars['position'])) {
    $position = explode(".", $vars['position']);
    $res = $context[ (int)$position[0] ][ (int)$position[1] ];
    positions_ranking($res, $context_total_matches);
    return [
      $vars['position'] => $res
    ];
  }
  
  // positions overview
  $res = [];
  $res['total'] = [];
  for ($i=1; $i>=0; $i--) {
    foreach ($context[$i] as $j => &$pos_summary) {
      positions_ranking($pos_summary, $context_total_matches);
      foreach ($pos_summary as $id => $data) {
        if (isset($res['total'][$id])) $res['total'][$id] += $data['matches_s'];
        else $res['total'][$id] = $data['matches_s'];
      }
      $res["$i.$j"] = $pos_summary;
    }
  }
  return $res;
};

function positions_ranking(&$context, $total_matches) {
  $ranks = [];
  $context_copy = $context;
  $total_matches = 0;
  foreach ($context as $c) {
    if ($total_matches < $c['matches_s']) $total_matches = $c['matches_s'];
  }

  uasort($context_copy, function($a, $b) use ($total_matches) {
    return positions_ranking_sort($a, $b, $total_matches);
  });

  $increment = 100 / sizeof($context_copy); $i = 0;

  foreach ($context_copy as $id => $el) {
    if(isset($last) && $el['matches_s'] == $last['matches_s'] && $el['winrate_s'] == $last['winrate_s']) {
      $i++;
      $context[$id]['rank'] = $last_rank;
    } else
    $context[$id]['rank'] = 100 - $increment*$i++;
    $last = $el;
    $last_rank = $context[$id]['rank'];
  }
}