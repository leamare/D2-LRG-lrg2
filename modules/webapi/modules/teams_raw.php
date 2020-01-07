<?php 

$endpoints['teams_raw'] = function($mods, $vars, &$report) {
  if (isset($vars['teamid']) && isset($report['teams'][ $vars['teamid'] ]))
    return $report['teams'][ $vars['teamid'] ];

  return $report['teams'];
};
