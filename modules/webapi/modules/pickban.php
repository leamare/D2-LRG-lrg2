<?php 

$endpoints['pickban'] = function($mods, $vars, &$report) {
  if (!in_array("heroes", $mods)) throw new \Exception("This module is only available for heroes");

  if (isset($vars['team'])) {
    $context =& $report['teams'][ $vars['team'] ]['pickban'];
    $context_total_matches = $report['teams'][ $vars['team'] ]['matches_total'];
  } else if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ]['pickban'];
    $context_total_matches = $report['regions_data'][ $vars['region'] ]['main']["matches_total"];
  } else {
    $context =& $report['pickban'];
    $context_total_matches = $report["random"]["matches_total"];
  }

  if(!sizeof($context)) return [];

  uasort($context, function($a, $b) {
    if($a['matches_total'] == $b['matches_total']) return 0;
    else return ($a['matches_total'] < $b['matches_total']) ? 1 : -1;
  });

  $ranks = [];
  $context_copy = $context;

  $compound_ranking_sort = function($a, $b) use ($context_total_matches) {
    return compound_ranking_sort($a, $b, $context_total_matches);
  };

  uasort($context, $compound_ranking_sort);

  $increment = 100 / sizeof($context); $i = 0;

  foreach ($context as $id => $el) {
    if(isset($last) && $el == $last) {
      $i++;
      $ranks[$id] = $last_rank;
    } else
      $ranks[$id] = 100 - $increment*$i++;
    $last = $el;
    $last_rank = $ranks[$id];
  }

  foreach($context as $id => &$el) {
    $el['rank'] = round($ranks[$id], 2);
    $el['contest_rate'] = $el['matches_total']/$context_total_matches;
  }

  return [
    'pickban' => $context
  ];
};
