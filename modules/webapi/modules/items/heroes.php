<?php 

$endpoints['items-heroes'] = function($mods, $vars, &$report) use (&$endpoints) {
  if (!isset($report['items']) || empty($report['pi']) || !isset($report['items']['stats']))
    throw new \Exception("No items stats data");

  $res = [];

  if (!isset($vars['item']))
    throw new \Exception("Need to select an item for items-heroes.");

  $item = $vars['item'];

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }

  if (!isset($report['items']['stats']['total'][$item]))
    return [
      'item' => $item,
      'total' => null,
      'heroes' => null
    ];

  $ranks = [];

  $ranking_sort = function($a, $b) {
    return items_ranking_sort($a, $b);
  };

  uasort($report['items']['stats']['total'], $ranking_sort);

  $increment = 100 / sizeof($report['items']['stats']['total']); $i = 0;

  foreach ($report['items']['stats']['total'] as $id => $el) {
    if(isset($last) && $el == $last) {
      $i++;
      $ranks[$id] = $last_rank;
    } else
      $ranks[$id] = 100 - $increment*$i++;
    $report['items']['stats']['total'][$id]['rank'] = round($ranks[$id], 2);
    $last = $el;
    $last_rank = $ranks[$id];
  }
  unset($last);

  $total = $report['items']['stats']['total'][$item];
  unset($report['items']['stats']['total']);

  $heroes = [];

  foreach ($report['items']['stats'] as $hero => $items) {
    if (!empty($items[$item]))
      $heroes[$hero] = $items[$item];
  }

  $ranks = [];

  $ranking_sort = function($a, $b) {
    return items_ranking_sort($a, $b);
  };

  uasort($heroes, $ranking_sort);

  $increment = 100 / sizeof($heroes); $i = 0;

  foreach ($heroes as $id => $el) {
    if(isset($last) && $el == $last) {
      $i++;
      $ranks[$id] = $last_rank;
    } else
      $ranks[$id] = 100 - $increment*$i++;
    $heroes[$id]['rank'] = round($ranks[$id], 2);
    unset($heroes[$id]['category']);
    $last = $el;
    $last_rank = $ranks[$id];
  }
  unset($last);

  $res['item'] = $item;
  $res['total'] = $total;
  $res['heroes'] = $heroes;

  return $res;
};