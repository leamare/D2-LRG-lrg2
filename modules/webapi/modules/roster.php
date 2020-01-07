<?php 

$endpoints['roster'] = function($mods, $vars, &$report) {
  if (isset($vars['teamid']) && isset($report['teams'][ $vars['teamid'] ])) {
    $res = [];
    foreach($report['teams'][ $vars['teamid'] ]['active_roster'] as $player) {
      $res[] = player_card($player);
    }
    return $res;
  }
    //return $report['teams'][ $vars['teamid'] ];
  throw new \Exception("You need teamid for roster");
};
