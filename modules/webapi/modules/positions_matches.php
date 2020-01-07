<?php 

$endpoints['positions_matches'] = function($mods, $vars, &$report) {
  if (in_array("players", $mods))
    $type = "player";
  else 
    $type = "hero";

  // positions context
  if (isset($vars['team'])) {
    $context =& $report['teams'][ $vars['team'] ][$type.'hero_positions_matches'];
  } else if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ][$type.'_positions_matches'];
  } else {
    $context =& $report[$type.'_positions'];
  }

  if (!isset($vars[$type.'id']))
    throw new \Exception("No ID specified.");

  if (isset($vars['position'])) {
    $position = explode(".", $vars['position']);
    $res = $context[ (int)$position[0] ][ (int)$position[1] ][ $vars[$type.'id'] ] ?? [];
    return [
      $type.'id' => $vars[$type.'id'],
      "position" => $vars['position'],
      "matches" => $res
    ];
  } 

  throw new \Exception("Positions matches requires a position specified.");
};
