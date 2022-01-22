<?php 

include_once(__DIR__ . "/../../../view/functions/teams_diversity_recalc.php");

$endpoints['teams_raw'] = function($mods, $vars, &$report) {
  if (isset($vars['teamid']) && isset($report['teams'][ $vars['teamid'] ])) {
    if (isset($report['teams'][ $vars['team'] ]['averages']) || isset($report['teams'][ $vars['team'] ]['averages']['hero_pool'])) 
      $report['teams'][ $vars['team'] ]['averages']['diversity'] = teams_diversity_recalc($report['teams'][ $vars['team'] ]);

    return $report['teams'][ $vars['teamid'] ];
  }

  foreach ($report['teams'] as $team => $data) {
    if (!isset($data['averages']) || !isset($data['averages']['hero_pool'])) continue;

      $report['teams'][$team]['averages']['diversity'] = teams_diversity_recalc($data);
  }

  return $report['teams'];
};
