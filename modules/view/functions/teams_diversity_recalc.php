<?php 

function core_picked_percentage($array, $q1 = null, $q3 = null) {
  if (empty($array)) return 0;

  sort($array);
  
  $sz = count($array);
  if (!isset($q3)) $q3 = $array[round($sz*0.75)];
  if (!isset($q1)) $q1 = $array[round($sz*0.25)];
  $q2 = $array[round($sz*0.5)];

  $arr2 = array_filter($array, function($a) use ($q1, $q3) {
      return $a >= $q1 && $a <= $q3;
  });
  
  
  return count($arr2)/($sz);
}

function teams_diversity_recalc(&$team) {
  global $meta;

  $pb = [];

  foreach ($team['pickban'] as $line) {
    if ($line['matches_picked']) $pb[] = $line['matches_picked'];
  }

  $pool = $team['averages']['hero_pool'];
  $mpool = ceil(count($meta['heroes'])/5);

  $matches = (int)$team['matches_total'];

  $core = core_picked_percentage($pb);

  return sqrt( ( $pool / (min($matches, $mpool) * 5)) * $core );
}

