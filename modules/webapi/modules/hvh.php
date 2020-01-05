<?php 

$endpoints['hvh'] = function($mods, $vars, &$report) {
  if (isset($vars['team'])) {
    throw new \Exception("No team allowed");
  } else if (isset($vars['region'])) {
    throw new \Exception("No region allowed");
  }

  $hvh = rg_generator_pvp_unwrap_data($report['hvh'], $report['pickban']);

  if (isset($vars['heroid'])) {
    return $hvh[ $vars['heroid'] ];
  }
  return $hvh;
};
