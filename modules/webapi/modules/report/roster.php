<?php 

$endpoints['roster'] = function($mods, $vars, &$report) {
  if (isset($vars['team']) && isset($report['teams'][ $vars['team'] ])) {
    $res = [];
    foreach($report['teams'][ $vars['team'] ]['active_roster'] as $player) {
      $res[] = player_card($player);
    }
    return $res;
  }
    //return $report['teams'][ $vars['teamid'] ];
  throw new \Exception("You need teamid for roster");
};
