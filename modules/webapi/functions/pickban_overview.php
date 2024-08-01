<?php 

include_once(__DIR__ . "/../../view/functions/ranking.php");

function rgapi_generator_pickban_overview(&$context, $context_total_matches, $limiter = 10, $params = [], $heroes_flag = true) {
  compound_ranking($context, $context_total_matches);

  uasort($context, function($a, $b) {
    return $b['wrank'] <=> $a['wrank'];
  });

  $min = end($context)['wrank'];
  $max = reset($context)['wrank'];

  foreach ($context as $id => $el) {
    $context[$id]['rank'] = 100 * ($el['wrank']-$min) / ($max-$min);
    unset($context[$id]['wrank']);

    $context[$id]['picks_to_median'] = isset($params['median_picks']) ? round($el['matches_picked']/$params['median_picks'], 3) : null;
    $context[$id]['bans_to_median'] = isset($params['median_bans']) ? round($el['matches_banned']/$params['median_bans'], 3) : null;
  }

  $context_b = $context;
  $k = array_keys($context_b);

  $r = array_splice($context_b, 0, $limiter);
  $k = array_splice($k, 0, $limiter);
  
  return array_combine($k, $r);
}