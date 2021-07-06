<?php 

$endpoints['items-combos'] = function($mods, $vars, &$report) use (&$endpoints, &$meta) {
  if (!isset($report['items']) || empty($report['items']['pi']) || !isset($report['items']['records']))
    throw new \Exception("No items combos data");
  
  $res = [];

  if (is_wrapped($report['items']['combos'])) {
    $report['items']['combos'] = unwrap_data($report['items']['combos']);
  }
  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }

  if (isset($vars['item'])) {
    $item = $vars['item'];

    if (!isset($report['items']['combos'][$item])) {
      return [
        'item' => $item,
        'total' => null,
        'pairs' => []
      ];
    }

    $items = [];
    foreach ($report['items']['combos'][$item] as $iid => $v) {
      if ($iid == '_h') continue;
      if (empty($v)) continue;
      if (!$v['matches'] && isset($report['items']['combos'][$iid][$item]) && $report['items']['combos'][$iid][$item]['matches']) {
        $v = $report['items']['combos'][$iid][$item];
        $v['time_diff'] = -$v['time_diff'];
      }
      $items[$iid] = $v;
    }

    return [
      'item' => $item,
      'total' => $report['items']['stats']['total'][$item],
      'pairs' => $items
    ];
  } else {
    $meta['item_categories'];

    $pairs = [];
    if (is_wrapped($report['items']['ph'])) {
      $report['items']['ph'] = unwrap_data($report['items']['ph']);
    }

    foreach ($report['items']['combos'] as $iid1 => $items) {
      if (in_array($iid1, $meta['item_categories']['early'])) continue;
      foreach ($items as $iid2 => $v) {
        if (in_array($iid2, $meta['item_categories']['early'])) continue;
        if ($iid2 == '_h') continue;
        if (empty($v)) continue;
        if ($v['matches'] < $report['items']['ph']['total']['med']) continue;
        $v['itemid1'] = $iid1;
        $v['itemid2'] = $iid2;
        $pairs[] = $v;
      }
    }

    return [
      'item' => null,
      'total' => null,
      'pairs' => $pairs
    ];
  }
};