<?php 

function rgapi_generator_overview_sides_section($context, $context_pb, $count) {
  $diff_rad_wr = [];
  $diff_rad_match = [];
  $heroes = [];

  foreach ($context as $side => $els) {
    foreach ($els as $el) {
      $id = $el['heroid'];
      if (!isset($heroes[$id])) $heroes[$id] = [];
      $heroes[$id][$side] = $el;
    }
  }

  foreach ($heroes as $hid => $data) {
    if (!isset($data[0]) || !isset($data[1])) {
      continue;
    }
    $diff_rad_wr[$hid] = $data[1]['winrate'] - $data[0]['winrate'];
    $diff_rad_match[$hid] = $data[1]['matches'] - $data[0]['matches'];
  }

  arsort($diff_rad_wr);

  $subset = array_slice($diff_rad_wr, 0, $count, true);
  $rd = array_map(function($v, $k) use (&$diff_rad_match, $context_pb) {
    return [
      'hero' => $k,
      'pickrate_diff' => round($diff_rad_match[$k]/$context_pb[$k]['matches_picked'], 4),
      'winrate_diff' => round($v, 4)
    ];
  }, $subset, array_keys($subset));

  $subset = array_slice(array_reverse($diff_rad_wr, true), 0, $count, true);
  $dd = array_map(function($v, $k) use (&$diff_rad_match, $context_pb) {
    return [
      'hero' => $k,
      'pickrate_diff' => round(-$diff_rad_match[$k]/$context_pb[$k]['matches_picked'], 4),
      'winrate_diff' => round(-$v, 4)
    ];
  }, $subset, array_keys($subset));

  return [
    'radiant_advantage' => $rd,
    'dire_advantage' => $dd
  ];
}