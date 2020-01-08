<?php 

$endpoints['haverages'] = function($mods, $vars, &$report) {
  $res = [];

  if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ];
  } else {
    $context =& $report;
  }

  if (in_array("heroes", $mods)) {
    $type = "heroes";
  } else if (in_array("players", $mods)) {
    $type = "players";
  } else {
    throw new \Exception("No module specified");
  }

  $res = [
    "type" => $type,
    "data" => $context['averages_'.$type] ?? $context['haverages_'.$type]
  ];

  return $res;
};
