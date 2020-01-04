<?php 

$endpoints['combos'] = function($mods, $vars, &$report) {
  $res = [];

  if (isset($vars['team'])) {
    $context =& $report['teams'][ $vars['team'] ];
  } else if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ];
  } else {
    $context =& $report;
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
