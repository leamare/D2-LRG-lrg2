<?php 

$endpoints['items-stats'] = function($mods, $vars, &$report) use (&$endpoints, &$meta) {
  if (!isset($report['items']) || empty($report['pi']) || !isset($report['items']['stats']))
    throw new \Exception("No items stats data");

  $res = [];

  $hero = $vars['heroid'] ?? 'total';

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }

  if (!empty($vars['item_cat']) && !in_array('all', $vars['item_cat'])) {
    $items_allowed = [];
    $items_categories = [];
    $meta['item_categories'];
    foreach ($vars['item_cat'] as $ic) {
      if (!isset($meta['item_categories'][$ic])) continue;
      $items_categories[] = $ic;
      $items_allowed = array_merge($items_allowed, $meta['item_categories'][$ic]);
    }
    $items_allowed = array_unique($items_allowed);
  } else {
    $items_allowed = null;
    $items_categories = ['all'];
  }

  if (!isset($report['items']['stats'][$hero]))
    $report['items']['stats'][$hero] = [];

  foreach ($report['items']['stats'][$hero] as $iid => $v) {
    if (empty($v)) unset($report['items']['stats'][$hero][$iid]);
  }

  if (!empty($report['items']['stats'][$hero])) {
    $ranks = [];

    $ranking_sort = function($a, $b) {
      return items_ranking_sort($a, $b);
    };

    uasort($report['items']['stats'][$hero], $ranking_sort);

    $increment = 100 / sizeof($report['items']['stats'][$hero]); $i = 0;

    foreach ($report['items']['stats'][$hero] as $id => $el) {
      if(isset($last) && $el == $last) {
        $i++;
        $ranks[$id] = $last_rank;
      } else {
        $ranks[$id] = 100 - $increment*$i++;
      }
      $report['items']['stats'][$hero][$id]['rank'] = round($ranks[$id], 2);
      $last = $el;
      $last_rank = $ranks[$id];
    }
    unset($last);
  }

  $res['categories'] = $items_categories;
  $res['allowed_items'] = $items_allowed;
  $res['hero'] = $vars['heroid'] ?? null;
  if (isset($vars['heroid'])) {
    $res['hero_pickban'] = $report['pickban'][$hero] ?? [];
  } else {
    $res['hero_pickban'] = null;
  }
  if (empty($items_allowed)) {
    $res['items'] = $report['items']['stats'][$hero];
  } else {
    $res['items'] = [];
    foreach($report['items']['stats'][$hero] as $iid => $line) {
      if (in_array($iid, $items_allowed)) $res['items'][$iid] = $line;
    }
  }

  return $res;
};