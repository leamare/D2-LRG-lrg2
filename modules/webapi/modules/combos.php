<?php 

function rg_generate_hero_pairs(&$context, $limiter) {
  $r = [];

  foreach ($context as $hid1 => $heroes) {
    foreach ($heroes as $hid2 => $line) {
      if (empty($line) || $line === true)
        continue;
      if ($line['matches'] <= $limiter)
        continue;
      
      $line['heroid1'] = $hid1;
      $line['heroid2'] = $hid2;
      $line['expectation'] = $line['exp'];
      unset($line['exp']);

      $r[] = $line;
    }
  }

  return $r;
}

$endpoints['combos'] = function($mods, $vars, &$report) {
  $res = [];

  if (isset($vars['team'])) {
    $context =& $report['teams'][ $vars['team'] ];
  } else if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ];
  } else {
    $context =& $report;

    if (!empty($report['hph']) && is_wrapped($report['hph'])) {
      $report['hph'] = unwrap_data($report['hph']);
    }

    if (in_array("heroes", $mods) && empty($report['hero_pairs']) && isset($report['hph'])) {
      $context['hero_pairs'] = rg_generate_hero_pairs($report['hph'], $report['settings']['limiter_combograph']);
    }
  }

  if (in_array("heroes", $mods)) {
    $type = "hero";
  } else if (in_array("players", $mods)) {
    $type = "player";
  } else {
    throw new \Exception("No module specified");
  }

  if (in_array("trios", $mods)) {
    $res['type'] = "trios";
    $res['data'] = $context[$type.'_triplets'] ?? $context[$type.'_trios'];
  } else if (in_array("lane_combos", $mods)) {
    $res['type'] = "lane_combos";
    $res['data'] = $context[$type.'_lane_combos'];
  } else {
    $res['type'] = "pairs";
    $res['data'] = $context[$type.'_pairs'];
  }

  return $res;
};
