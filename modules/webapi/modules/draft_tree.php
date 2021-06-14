<?php 

$endpoints['draft_tree'] = function($mods, $vars, &$report) {
  if (!isset($report['draft_tree']))
    throw new \Exception("This module doesn't exist");
  if (!in_array("heroes", $mods))
    throw new \Exception("This module is only available for heroes");

  // positions context
  if (isset($vars['team'])) {
    $parent =& $report['teams'][ $vars['team'] ]; 
    $context =& $report['teams'][ $vars['team'] ]['draft_tree'];
    $draft_context =& $report['teams'][ $vars['team'] ]['draft'];
    $limiter = $report['settings']['limiter_triplets'];
  } else if (isset($vars['region'])) {
    $parent =& $report['regions_data'][ $vars['region'] ]; 
    $context =& $report['regions_data'][ $vars['region'] ]['draft_tree'];
    $draft_context =& $report['regions_data'][ $vars['region'] ]['draft'];
    $limiter = $report['regions_data'][ $vars['region'] ]['settings']['limiter_graph']*6;
  } else {
    $parent =& $report;
    $context =& $report['draft_tree'];
    $draft_context =& $report['draft'];
    $limiter = $report['settings']['limiter_combograph']*6;
  }

  if (is_wrapped($context)) $context = unwrap_data($context);

  usort($context, function($a, $b) {
    return $b['count'] <=> $a['count'];
  });

  if (!empty($vars['cat'])) {
    $i = floor(count($context) * (float)($vars['cat']));
    $med = $context[ $i > 0 ? $i-1 : 0 ]['count'];
  } else {
    $med = 0;
  }

  $stages_pop = [];
  $items = [];

  $context = array_filter($context, function($a) use ($med) {
    return $a['count'] > $med;
  });

  return [
    'edges' => $context,
  ];
};