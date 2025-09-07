<?php

$picks = [];
foreach($result["pickban"] as $hero)
  $picks[] = (int)($hero["matches_picked"] ?? $hero["matches_total"]);

if ($lg_settings['ana']['players']) {
  $players = array_map(
    function($a) { return reset($a); }, 
    instaquery($conn, "SELECT COUNT(matchid) FROM matchlines GROUP BY playerid;")
  );
} else if ($lg_settings['ana']['teams']) {
  $teams_matches = array_map(
    function($a) { return reset($a); }, 
    instaquery($conn, "SELECT COUNT(matchid) FROM teams_matches GROUP BY teamid;")
  );
}

function calculate_limiters(array $dataset, $teams = null, $total = null): array {
  if (!empty($dataset)) {
    if (empty($total))
      $total = max($dataset);

    $median = quantile($dataset, 0.5);
    $mean = expected($dataset);
    $sq_dev = sq_dev($dataset);
    // getting quantiles higher than mean, it's easier to work with
    $q1 = 1-find_position($dataset, $mean+$sq_dev)/count($dataset);
    $q2 = 1-find_position($dataset, ($mean+$sq_dev)*2)/count($dataset);
    $q2m = 1-find_position($dataset, ($mean+$sq_dev)*1.85)/count($dataset);
    //echo "\n".(($mean+$sq_dev)*1.85)."\n"; die();
    $q3 = 1-find_position($dataset, ($mean+$sq_dev)*3)/count($dataset);
    // and then i screwed up all the maths
    // All these shenanigans didn't really change much for low match counts
    // but for higher match counts I've got a much better result by changing values into 1-Q((M+sd)*n)
    // and it's actually pretty much Q(M-sd*n), except it's easier to work with

    $pairs = [];
    foreach ($dataset as $i => $v1) {
      foreach ($dataset as $j => $v2) {
        if ($i == $j) continue;
        $pairs[] = round($v1 * $v2 / $total);
      }
    }
    sort($pairs);
    // Graphs limiter is based on a different dataset
    // that is basically representing expectations for all the possible pairs.
    // Expectation * 2 to be exact.
    // Quantile 0.8 is used to filter out top 20% of pairs (well, probably closer to top 10%)
    // but anyway.
  } else {
    $median = 0;
    $sq_dev = 0;
    $q1 = 0; $q2 = 0; $q2m = 0; $q3 = 0;

    $pairs = [ 0 ];
  }

  return [
    "sq_dev" => unzero($sq_dev),
    "limiter_quantile" => $q1,
    "median" => unzero($median),
    "limiter_higher" => unzero(quantile($dataset, $q1)),
    "limiter_middle" => unzero(round(quantile($dataset, $q2))),
    "limiter_graph" => unzero(round(quantile($pairs, 0.8))),
    "limiter_lower" => unzero(round($teams ? 
      ceil(quantile($dataset, $q1)/$teams) :
      quantile($dataset, $q3)))
  ];
}

$limiters = calculate_limiters($picks, $result['random']['teams_on_event'] ?? null, $result['random']["matches_total"]);

if ($lg_settings['ana']['players']) {
  $limiters_players = calculate_limiters($players, $result['random']['teams_on_event'] ?? null, $result['random']["matches_total"]);
} else if ($lg_settings['ana']['teams']) {
  $limiters_teams = calculate_limiters($teams_matches, $result['random']['teams_on_event'] ?? null, $result['random']["matches_total"]);
}
  
//compatibility
$limiter = $limiters['limiter_higher'];
$limiter_graph = $limiters['limiter_graph'];
$limiter_lower = $limiters['limiter_lower'];
$limiter_middle = $limiters['limiter_middle'];
$limiter_median = $limiters['median'];
$limiter_quantile = $limiters['limiter_quantile'];

if ($lg_settings['ana']['players']) {
  $pl_limiter = round($limiters_players['limiter_higher'] * 0.75);
  $pl_limiter_median = $limiters_players['median'];
} else if ($lg_settings['ana']['teams']) {
  $pl_limiter = round($limiters_teams['limiter_higher'] * 0.75);
  $pl_limiter_median = $limiters_teams['median'];
} else {
  $pl_limiter = $limiter_graph;
  $pl_limiter_median = $limiter_median;
}

echo <<<LIMITERS
[ ] Limiter: $limiter
[ ] Limiter for graphs: $limiter_graph
[ ] Lower Limiter: $limiter_lower
[ ] Middle Limiter: $limiter_middle
[ ] Median Limiter: $limiter_median
[ ] Quantile: $limiter_quantile
[ ] Limiter Players: $pl_limiter

LIMITERS;
