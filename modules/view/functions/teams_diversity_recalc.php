<?php 

function core_picked_percentage($array, $match_heroes = 5) {
  if (empty($array)) return 0;

  sort($array);
  
  $sz = count($array);
  $q3 = $array[round($sz*0.75)];
  $q1 = $array[round($sz*0.25)];
  // $q2 = $array[round($sz*0.5)];

  $arr2 = array_filter($array, function($a) use ($q1, $q3) {
      return $a >= $q1 && $a <= $q3;
  });
  
  $ms = (array_sum($arr2)/$match_heroes);
  
  return count($arr2)/($sz);
}

function teams_diversity_recalc(&$team) {
  $pb = [];

  foreach ($team['pickban'] as $line) {
    if ($line['matches_picked']) $pb[] = $line['matches_picked'];
  }

  $pool = $team['averages']['hero_pool'];
  $mpool = ceil($pool/5);

  $matches = $team['matches'];

  $core = core_picked_percentage($pb);

  return sqrt( ( $pool / (min($matches, $mpool) * 5)) * $core );
}