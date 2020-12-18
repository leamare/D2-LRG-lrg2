<?php 

include_once(__DIR__ . "/items/overview.php");
include_once(__DIR__ . "/items/stats.php");
include_once(__DIR__ . "/items/heroes.php");
include_once(__DIR__ . "/items/icombos.php");
include_once(__DIR__ . "/items/progression.php");
include_once(__DIR__ . "/items/irecords.php");

$endpoints['items'] = function($mods, $vars, &$report) use (&$endpoints) {
  if (in_array('heroes', $mods) || in_array('heroboxplots', $mods) || in_array('hboxplots', $mods)) {
    $res = $endpoints['items-heroes']($mods, $vars, $report);
    $res['__endp'] = "items-heroes";
  } else if (in_array('combos', $mods) || in_array('icombos', $mods)) {
    $res = $endpoints['items-combos']($mods, $vars, $report);
    $res['__endp'] = "items-combos";
  } else if (in_array('stats', $mods) || in_array('boxplots', $mods)) {
    $res = $endpoints['items-stats']($mods, $vars, $report);
    $res['__endp'] = "items-stats";
  } else if (in_array('records', $mods) || in_array('irecords', $mods)) {
    $res = $endpoints['items-records']($mods, $vars, $report);
    $res['__endp'] = "items-records";
  } else if (in_array('progression', $mods) || in_array('proglist', $mods)) {
    $res = $endpoints['items-progression']($mods, $vars, $report);
    $res['__endp'] = "items-progression";
  } else {
    $res = $endpoints['items-overview']($mods, $vars, $report);
    $res['__endp'] = "items-overview";
  }
  

  return $res;
};