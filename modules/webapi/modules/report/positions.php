<?php 

$repeatVars['positions'] = ['position'];

$endpoints['positions'] = function($mods, $vars, &$report) {
  if (in_array("players", $mods))
    $type = "player";
  else 
    $type = "hero";

  // positions context
  if (isset($vars['team'])) {
    $parent =& $report['teams'][ $vars['team'] ]; 
    $context =& $report['teams'][ $vars['team'] ][$type.'_positions'];
    $context_total_matches = $report['teams'][ $vars['team'] ]['matches_total'];
  } else if (isset($vars['region'])) {
    $parent =& $report['regions_data'][ $vars['region'] ]; 
    $context =& $report['regions_data'][ $vars['region'] ][$type.'_positions'];
    $context_total_matches = $report['regions_data'][ $vars['region'] ]['main']["matches_total"];
  } else {
    $parent =& $report;
    $context =& $report[$type.'_positions'];
    $context_total_matches = $report["random"]["matches_total"];
  }

  if (is_wrapped($context)) $context = unwrap_data($context);

  $median_picks = $parent['random']['heroes_median_picks'] ?? $parent['main']['heroes_median_picks'] ?? null;

  if (isset($vars['position'])) {
    $position = explode(".", $vars['position']);
    if ($position[1] == 0) {
      //$position[1] = array_keys($context[ (int)$position[0] ])[0];
      $p0 = [];
      $p0m = [];
      foreach($context[ (int)$position[0] ] as $p) {
        foreach($p as $hero => $stats) {
          if(!isset($p0[$hero])) {
            $p0[$hero] = [];
            $p0m[$hero] = 0;
          }
          $p0m[$hero] += $stats['matches_s'];
          $m = $stats['matches_s'];
          unset($stats['matches_s']);
          foreach ($stats as $k => $v) {
            $p0[$hero][$k] = ($p0[$hero][$k] ?? 0) + $v*$m;
          }
        }
      }
      foreach($p0 as $hero => $stats) {
        foreach ($stats as $k => $v) {
          $p0[$hero][$k] = round($v/$p0m[$hero], 4);
        }
        $p0[$hero]['matches_s'] = $p0m[$hero];
      }
      $res = $p0;
      //$res = $context[ (int)$position[0] ][ (int)$position[1] ];
    } else {
      if (!isset($context[ (int)$position[0] ][ (int)$position[1] ]))
        $position[1] = array_keys($context[ (int)$position[0] ])[0];
      $res = $context[ (int)$position[0] ][ (int)$position[1] ];
    }
    positions_ranking_helper($res, $context_total_matches);
    
    $keys = array_keys( array_values($res)[0] );
    $is_dmg_per_min = in_array("hero_damage_per_min_s", $keys) && in_array("gpm", $keys) && !in_array("damage_to_gold_per_min_s", $keys);
    if (in_array("hero_damage_per_min_s", $keys) && in_array("gpm", $keys) && !in_array("damage_to_gold_per_min_s", $keys)) {
      foreach ($context as $id => $el) {
        $context[$id] = array_insert_before($context[$id], "gpm", [
          "damage_to_gold_per_min_s" => ($context[$id]['hero_damage_per_min_s'] ?? 0)/($context[$id]['gpm'] ?? 1),
        ]);
      }

      $keys = array_insert_before($keys, array_search("gpm", $keys), [ 'damage_to_gold_per_min_s' ]);
    }

    foreach ($res as $id => $data) {
      $res[$id]['picks_to_median'] = isset($median_picks) ? round($data['matches_s']/$median_picks, 3) : null;
      if ($is_dmg_per_min) {
        $res[$id] = array_insert_before($res[$id], "gpm", [
          "damage_to_gold_per_min_s" => ($res[$id]['hero_damage_per_min_s'] ?? 0)/($res[$id]['gpm'] ?? 1),
        ]);
      }
    }

    return [
      implode('.', $position) => $res
    ];
  }
  
  // positions overview
  $res = [];
  $res['total'] = [];
  for ($i=1; $i>=0; $i--) {
    foreach ($context[$i] as $j => &$pos_summary) {
      if (empty($pos_summary)) continue;
      positions_ranking_helper($pos_summary, $context_total_matches);
      foreach ($pos_summary as $id => $data) {
        if (isset($res['total'][$id])) $res['total'][$id] += $data['matches_s'];
        else $res['total'][$id] = $data['matches_s'];
        $pos_summary[$id]['picks_to_median'] = isset($median_picks) ? round($data['matches_s']/$median_picks, 3) : null;
      }
      $res["$i.$j"] = $pos_summary;
    }
  }
  return $res;
};

function positions_ranking_helper(&$context, $total_matches) {
  $context_copy = $context;
  $total_matches = 0;
  foreach ($context as $c) {
    if ($total_matches < $c['matches_s']) $total_matches = $c['matches_s'];
  }

  positions_ranking($context, $total_matches);

  uasort($context, function($a, $b) {
    return $b['wrank'] <=> $a['wrank'];
  });

  $min = end($context)['wrank'];
  $max = reset($context)['wrank'];

  foreach ($context as $elid => $el) {
    $context[$elid]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
    $context_copy[$elid]['winrate_s'] = 1-($context_copy[$elid]['winrate'] ?? $context_copy[$elid]['winrate_s']);
  }

  positions_ranking($context_copy, $total_matches);

  uasort($context_copy, function($a, $b) {
    return $b['wrank'] <=> $a['wrank'];
  });

  $min = end($context_copy)['wrank'];
  $max = reset($context_copy)['wrank'];

  foreach ($context_copy as $elid => $el) {
    $context_copy[$elid]['arank'] = 100 * ($el['wrank']-$min) / ($max-$min);
    unset($context[$elid]['wrank']);
  }
}