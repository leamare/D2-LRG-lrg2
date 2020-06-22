<?php 

include_once(__DIR__ . "/../../view/functions/ranking.php");

function rgapi_generator_pickban_overview(&$context, $context_total_matches, $limiter = 10, $params = [], $heroes_flag = true) {
  $compound_ranking_sort = function($a, $b) use ($context_total_matches) {
    return compound_ranking_sort($a, $b, $context_total_matches);
  };

  uasort($context, $compound_ranking_sort);

  $increment = 100 / sizeof($context); $i = 0;

  foreach ($context as $id => $el) {
    $context[$id]['rank'] = round(100 - $increment*$i++, 2);
    $context[$id]['picks_to_median'] = isset($params['median_picks']) ? round($el['matches_picked']/$params['median_picks'], 3) : null;
    $context[$id]['bans_to_median'] = isset($params['median_bans']) ? round($el['matches_banned']/$params['median_bans'], 3) : null;
  }

  return $context;
}