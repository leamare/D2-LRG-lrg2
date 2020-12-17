<?php 

$endpoints['items-overview'] = function($mods, $vars, &$report) use (&$endpoints, &$meta) {
  $meta['item_categories'];

  if (is_wrapped($report['items']['stats'])) {
    $report['items']['stats'] = unwrap_data($report['items']['stats']);
  }
  if (is_wrapped($report['items']['ph'])) {
    $report['items']['ph'] = unwrap_data($report['items']['ph']);
  }
  if (is_wrapped($report['items']['pi'])) {
    $report['items']['pi'] = unwrap_data($report['items']['pi']);
  }
  if (isset($report['items']['records'])) {
    if (is_wrapped($report['items']['records'])) {
      $report['items']['records'] = unwrap_data($report['items']['records']);
    }
  }

  $res = [];

  $limit = $report['settings']['overview_items_limit'] ?? 10; 

  $best = [];
  $worst = [];
  uasort($report['items']['stats']['total'], function($a, $b) {
    return ( $a['winrate']-$a['wo_wr'] ) <=> ( $b['winrate']-$b['wo_wr'] );
  });
  $keys = array_keys($report['items']['stats']['total']);
  $sz = sizeof($keys);

  for ($i = 0, $j = 0; $i < $sz && $j < $limit; $i++) {
    if (empty($keys[$i]) || empty($report['items']['stats']['total'][ $keys[$i] ])) continue;
    if (in_array($keys[$i], $meta['item_categories']['early'])) continue;
    if ($report['items']['stats']['total'][ $keys[$i] ]['purchases'] <= $report['items']['ph']['total']['q1']) continue;
    $worst[ $keys[$i] ] = $report['items']['stats']['total'][ $keys[$i] ];
    $j++;
  }

  for ($i = $sz-1, $j = 0; $i > 0 && $j < $limit; $i--) {
    if (empty($keys[$i]) || empty($report['items']['stats']['total'][ $keys[$i] ])) continue;
    if (in_array($keys[$i], $meta['item_categories']['early'])) continue;
    if ($report['items']['stats']['total'][ $keys[$i] ]['purchases'] <= $report['items']['ph']['total']['q1']) continue;
    $best[ $keys[$i] ] = $report['items']['stats']['total'][ $keys[$i] ];
    $j++;
  }

  $res['total_best'] = $best;
  $res['total_worst'] = $worst;

  unset($report['items']['stats']['total']);

  $gradients = [];
  $best_a = [];
  $worst_a = [];

  foreach ($report['items']['stats'] as $hero => $items) {
    foreach ($items as $iid => $line) {
      if (empty($line)) continue;
      if (in_array($iid, $meta['item_categories']['early'])) continue;
      if ($line['purchases'] <= $report['items']['ph'][$hero]['q3'] || $line['purchases'] <= $report['items']['pi'][$iid]['q3']) continue;
      $line['hero'] = $hero;
      $line['item'] = $iid;
      
      if ($line['winrate'] > $line['wo_wr']) $best_a[] = $line;
      else $worst_a[] = $line;

      if ($line['grad'] < 0) $gradients[] = $line;
    }
  }

  uasort($best_a, function($b, $a) {
    return ( $a['winrate']-$a['wo_wr'] ) <=> ( $b['winrate']-$b['wo_wr'] );
  });
  uasort($worst_a, function($a, $b) {
    return ( $a['winrate']-$a['wo_wr'] ) <=> ( $b['winrate']-$b['wo_wr'] );
  });

  $best = array_slice($best_a, 0, $limit);
  $worst = array_slice($worst_a, 0, $limit);

  $res['heroes_best'] = $best;
  $res['heroes_worst'] = $worst;

  uasort($gradients, function($a, $b) {
    return $a['grad'] <=> $b['grad'];
  });
  $gradients = array_slice($gradients, 0, $limit);

  if (isset($report['items']['records'])) {
    foreach ($gradients as $i => $v) {
      $gradients[$i]['record'] = $report['items']['records'][ $v['item'] ][ $v['hero'] ] ?? [];
    }
  }
  $res['gradients'] = $gradients;

  return $res;
};