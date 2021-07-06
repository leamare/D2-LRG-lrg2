<?php 

$endpoints['sides'] = function($mods, $vars, &$report) {
  $res = [];

  if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ];
  } else {
    $context =& $report;
  }

  if (in_array("heroes", $mods) && isset($context['hero_sides'])) {
    $type = "hero";
  } else if (in_array("players", $mods) && isset($context['player_sides'])) {
    $type = "player";
  } else {
    throw new \Exception("No module specified");
  }

  foreach($context[$type.'_sides'] as $i => $side) {
    $res[$i] = [];
    foreach($side as $item) {
      $id = $item[$type.'id'];
      unset($item[$type.'id']);
      $res[$i][ $id ] = $item;
    }
  }

  return $res;
};
