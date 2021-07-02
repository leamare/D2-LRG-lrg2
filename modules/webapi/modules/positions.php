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
    positions_ranking($res, $context_total_matches);
    
    foreach ($res as $id => $data) {
      $res[$id]['picks_to_median'] = isset($median_picks) ? round($data['matches_s']/$median_picks, 3) : null;
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
      positions_ranking($pos_summary, $context_total_matches);
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

  if (!empty($context_copy)) {
    $increment = 100 / sizeof($context_copy); $i = 0;

    foreach ($context_copy as $id => $el) {
      if(isset($last) && $el['matches_s'] == $last['matches_s'] && $el['winrate_s'] == $last['winrate_s']) {
        $i++;
        $context[$id]['rank'] = $last_rank;
      } else {
        $context[$id]['rank'] = round(100 - $increment*$i++, 2);
      }
      $last = $el;
      $last_rank = $context[$id]['rank'];
    }
  }

  $last = null;

  foreach($context_copy as &$data) {
    $data['winrate_s'] = 1-$data['winrate_s'];
  }

  uasort($context_copy, function($a, $b) use ($total_matches) {
    return positions_ranking_sort($a, $b, $total_matches);
  });

  if (!empty($context_copy)) {
    $i = 0;

    foreach ($context_copy as $id => $el) {
      if(isset($last) && $el['matches_s'] == $last['matches_s'] && $el['winrate_s'] == $last['winrate_s']) {
        $i++;
        $context[$id]['arank'] = $last_rank;
      } else {
        $context[$id]['arank'] = round(100 - $increment*$i++, 2);
      }
      $last = $el;
      $last_rank = $context[$id]['arank'];
    }
  }
}