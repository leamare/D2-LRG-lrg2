<?php 

function rgapi_generator_pickban_overview($context, $context_total_matches, $limiter = 10, $heroes_flag = true) {
  $compound_ranking_sort = function($a, $b) use ($context_total_matches) {
    return compound_ranking_sort($a, $b, $context_total_matches);
  };

  uasort($context, $compound_ranking_sort);

  $increment = 100 / sizeof($context); $i = 0;

  foreach ($context as $id => $el) {
    $context[$id]['rank'] = 100 - $increment*$i++;
  }

  return $context;
}