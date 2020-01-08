<?php 

function rgapi_generator_overview_combos($context, $limiter = 10, $heroes_flag = true) {
  if(empty($context)) return [];

  # Figuring out what kind of context we have here
  $i = $limiter;

  uasort($context, function($a, $b) {
    $dev_a = $a['matches']-$a['expectation'];
    $dev_b = $b['matches']-$b['expectation'];
    if($dev_a == $dev_b) return 0;
    return ($dev_a < $dev_b) ? 1 : -1;
  });

  $res = [];
  foreach($context as $combo) {
    $i--;
    $res[] = $combo;
    if($i < 0) break;
  }

  return $res;
}
