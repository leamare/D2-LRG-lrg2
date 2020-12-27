<?php 

$endpoints['items-progression'] = function($mods, $vars, &$report) use (&$endpoints) {
  if (!isset($report['items']) || empty($report['pi']) || !isset($report['items']['progr']))
    throw new \Exception("No items data");

  $res = [];

  if (is_wrapped($report['items']['progr'])) {
    $report['items']['progr'] = unwrap_data($report['items']['progr']);
  }
  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }
  
  if (isset($vars['heroid'])) {
    $hero = $vars['heroid'];
  } else {
    $hero = 'total';
  }

  if (!isset($report['items']['progr'][$hero]))
    $report['items']['progr'][$hero] = [];

  $pairs = [];
  $items = [];
  $max_wr = 0;
  $max_games = 0;
  foreach ($report['items']['progr'][$hero] as $v) {
    if (empty($v)) continue;
    $pairs[] = $v;
    
    if (!in_array($v['item1'], $items)) $items[] = $v['item1'];
    if (!in_array($v['item2'], $items)) $items[] = $v['item2'];

    if ($v['total'] > $max_games) $max_games = $v['total'];
    $diff = abs(($v['winrate'])-0.5);
    if ($diff > $max_wr) {
      $max_wr = $diff;
    }
  }

  $res['hero'] = $vars['heroid'] ?? null;
  $res['wr_amplitude'] = $max_wr;
  $res['matches_amplitude'] = $max_games;
  $res['items'] = [];
  foreach ($items as $iid) {
    $res['items'][$iid] = [
      'purchases' => $report['items']['stats'][$hero][$iid]['purchases'],
      'median_time' => $report['items']['stats'][$hero][$iid]['median'],
      'winrate' => $report['items']['stats'][$hero][$iid]['winrate'],
    ];
  }
  $res['edges'] = $pairs;

  return $res;
};