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

  $res = $context[$type.'_sides'];

  return $res;
};
