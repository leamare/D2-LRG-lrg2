<?php 

$endpoints['pickban'] = function($mods, $vars, &$report) {
  if (!in_array("heroes", $mods)) throw new \Exception("This module is only available for heroes");

  if (isset($vars['team'])) {
    $parent =& $report['teams'][ $vars['team'] ]; 
    $context =& $report['teams'][ $vars['team'] ]['pickban'];
    $context_total_matches = $report['teams'][ $vars['team'] ]['matches_total'];
    $context_main =& $report['teams'][ $vars['team'] ];
  } else if (isset($vars['region'])) {
    $parent =& $report['regions_data'][ $vars['region'] ]; 
    $context =& $report['regions_data'][ $vars['region'] ]['pickban'];
    $context_total_matches = $report['regions_data'][ $vars['region'] ]['main']['matches_total'];
    $context_main =& $report['regions_data'][ $vars['region'] ]['main'];
  } else {
    $parent =& $report;
    $context =& $report['pickban'];
    $context_total_matches = $report['random']['matches_total'];
    $context_main =& $report['random'];
  }

  if (is_wrapped($context)) {
    $context = unwrap_data($context);
  }

  if(!sizeof($context)) return [];

  $mp = $context_main['heroes_median_picks'] ?? null;
  $mb = $context_main['heroes_median_bans'] ?? null;

  if (!$mp) {
    uasort($context, function($a, $b) {
      return $a['matches_picked'] <=> $b['matches_picked'];
    });
    $mp = $context[ round(sizeof($context)*0.5) ]['matches_picked'];
  }
  if (!$mp) $mp = 1;

  if (!$mb) {
    uasort($context, function($a, $b) {
      return $a['matches_banned'] <=> $b['matches_banned'];
    });
    $mb = $context[ round(sizeof($context)*0.5) ]['matches_banned'];
  }
  if (!$mb) $mb = 1;

  uasort($context, function($a, $b) {
    if($a['matches_total'] == $b['matches_total']) return 0;
    else return ($a['matches_total'] < $b['matches_total']) ? 1 : -1;
  });

  $ranks = [];

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
    $el['contest_rate'] = round($el['matches_total']/$context_total_matches, 5);
    $el['picks_to_median'] = isset($median_picks) ? round($el['matches_picked']/$mp, 1) : null;
    $el['bans_to_median'] = isset($median_bans) ? round($el['matches_banned']/$mb, 1) : null;
  }

  return [
    'pickban' => $context
  ];
};
