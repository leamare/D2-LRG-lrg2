<?php 

include_once($root."/modules/view/functions/itembuilds.php");

$endpoints['items-builds'] = function($mods, $vars, &$report) use (&$endpoints, &$meta) {
  if (!isset($report['items']) || empty($report['items']['pi']) || !isset($report['items']['progr']))
    throw new \Exception("No items data");

  if (!isset($report['items']['progrole']))
    throw new \Exception("No items progression for roles");

  $res = [];

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }
  
  if (isset($vars['heroid'])) {
    $hero = $vars['heroid'];
  } else {
    $hero = null;
  }

  if (!isset($report['items']['progrole']['data'][$hero]))
    $report['items']['progrole']['data'][$hero] = [];

  if (isset($vars['position'])) {
    if (!isset($report['items']['progrole']['data'][$hero][ $vars['position'] ]))
      return [];
    $crole = $vars['position'];
  } else {
    $crole = array_keys($report['items']['progrole']['data'][$hero])[0] ?? null;
  }



  if ($hero === null) {
    foreach ($report['items']['progrole']['data'] as $hid => $positions) {
      if (empty($hid)) continue;
      $res[] = [
        'hero' => $hid,
        'positions' => array_keys($positions),
      ];
    }

    return $res;
  } else {
    if ($crole === null) return [];

    $data = [];

    foreach ($report['items']['progrole']['data'][$hero][$crole] as $elem) {
      $data[] = array_combine($report['items']['progrole']['keys'], $elem);
    }

    $pbdata = $report['pickban'][$hero] ?? [];

    $pairs = [];
    $items_matches = []; $items_matches_1 = [];

    if (!isset($report['items']['progr'][$hero])) $report['items']['progr'][$hero] = [];
    foreach ($data as $v) {
      if (empty($v)) continue;
      if ($v['item1'] == $v['item2']) continue;
      $pairs[] = $v;

      if (!isset($items_matches_1[ $v['item1'] ])) {
        $items_matches_1[ $v['item1'] ] = 0;
      }
      if (!isset($items_matches[ $v['item1'] ])) {
        $items_matches[ $v['item1'] ] = 0;
      }
      $items_matches_1[ $v['item1'] ] += $v['total'];
      
      if (!isset($items_matches[ $v['item2'] ])) {
        $items_matches[ $v['item2'] ] = 0;
      }
      $items_matches[ $v['item2'] ] += $v['total'];
    }

    foreach ($items_matches as $iid => $v) {
      $report['items']['stats'][$hero][$iid]['purchases'] = max($items_matches[$iid] ?? 0, $items_matches_1[$iid] ?? 0);
    }
    unset($items_matches_1);
    
    usort($pairs, function($a, $b) {
      return $b['total'] <=> $a['total'];
    });
  
    if (isset($report['hero_positions'])) {
      if (is_wrapped($report['hero_positions'])) $report['hero_positions'] = unwrap_data($report['hero_positions']);
      [$core, $lane] = explode('.', $crole);
      $pbdata['role_matches'] = isset($report['hero_positions'][$core][$lane][$hero])
        ? $report['hero_positions'][$core][$lane][$hero]['matches_s']
        : 0;
      $pbdata['role_winrate'] = isset($report['hero_positions'][$core][$lane][$hero])
        ? $report['hero_positions'][$core][$lane][$hero]['winrate_s']
        : 0;
    } else {
      $pbdata['role_matches'] = empty($pairs) ? 0 : $pairs[ 0 ]['total']*1.25;
    }

    $report['items']['stats'][$hero] = array_filter($report['items']['stats'][$hero], function($a) {
      return !empty($a);
    });

    $facets = null;

      // variants
  if (!empty($report['hero_summary_variants'])) {
    if (is_wrapped($report['hero_summary_variants'])) {
      $report['hero_summary_variants'] = unwrap_data($report['hero_summary_variants']);
    }

    $facets = [];
    $facets_list = isset($report['meta']['variants']) ? array_keys($report['meta']['variants'][$hero]) : $meta['facets']['heroes'][$hero];
    $rid = array_search($core.'.'.$lane, ROLES_IDS_SIMPLE);
    foreach ($facets_list as $i => $facet) {
      $i++;
      $hvid = $hero.'-'.$i;
    
      $hero_stats = $report['hero_summary_variants'][$rid][$hvid] ?? [];

      $facets[] = [
        'variant' => $i,
        'is_role' => true,
        'ratio' => $hero_stats['matches_s']/$pbdata['role_matches'],
        'matches' => $hero_stats['matches_s'] ?? 0,
        'winrate' => $hero_stats['winrate_s'] ?? '-',
      ];
    }
  } else if (isset($report['hero_variants'])) {
    $facets_list = isset($report['meta']['variants']) ? array_keys($report['meta']['variants'][$hero]) : $meta['facets']['heroes'][$hero];
    $facets = [];
    foreach ($facets_list as $i => $facet) {
      $i++;
      $hvid = $hero.'-'.$i;
      $stats = [
        'm' => 0,
        'w' => 0,
        'f' => 0,
      ];
      if (isset($report['hero_variants'][$hvid])) {
        $stats = $report['hero_variants'][$hvid];
      }
      $facets[] = [
        'variant' => $i,
        'is_role' => false,
        'ratio' => $stats['f'],
        'matches' => $stats['m'],
        'winrate' => $stats['m'] ? $stats['w']/$stats['m'] : 0,
      ];
    }
  }
  
    [ $build, $tree ] = generate_item_builds($pairs, $report['items']['stats'][$hero], $pbdata);

    $res = [
      'hero' => $hero,
      'role' => $crole,
      'stats' => $pbdata,
      'build' => $build,
    ];

    if (!empty($facets)) {
      $res['facets'] = $facets;
    }

    if (isset($vars['gets']) && in_array("tree", $vars['gets'])) {
      $res['tree'] = $tree;
    }

    return $res;
  }

  return [];
};