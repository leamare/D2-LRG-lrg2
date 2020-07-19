<?php

$picks = [];
foreach($result["pickban"] as $hero)
  $picks[] = (int)($hero["matches_picked"] ?? $hero["matches_total"]);

function calculate_limiters(array $dataset, int $teams = null): array {
  if (!empty($dataset)) {
    $median = quantile($dataset, 0.5);
    $mean = expected($dataset);
    $sq_dev = sq_dev($dataset);
    // getting quantiles higher than mean, it's easier to work with
    $q1 = 1-find_position($dataset, $mean+$sq_dev)/count($dataset);
    $q2 = 1-find_position($dataset, ($mean+$sq_dev)*2)/count($dataset);
    $q2m = 1-find_position($dataset, ($mean+$sq_dev)*1.85)/count($dataset);
    //echo "\n".(($mean+$sq_dev)*1.85)."\n"; die();
    // graphs limiter isn't really good at M-2sd, so we need to use a value
    // somewhere in the middle, 1.75 multiplier proved to be the best solution
    // since it's pretty close to 10% treshold which I was using before
    // It's kind of practically proven value
    $q3 = 1-find_position($dataset, ($mean+$sq_dev)*3)/count($dataset);
    // and then i screwed up all the maths
    // All these shenanigans didn't really change much for low match counts
    // but for higher match counts I've got a much better result by changing values into 1-Q((M+sd)*n)
    // and it's actually pretty much Q(M-sd*n), except it's easier to work with
  } else {
    $median = 0;
    $sq_dev = 0;
    $q1 = 0; $q2 = 0; $q2m = 0; $q3 = 0;
  }

  return [
    "sq_dev" => $sq_dev,
    "limiter_quantile" => $q1,
    "median" => $median,
    "limiter_higher" => quantile($dataset, $q1),
    "limiter_middle" => round(quantile($dataset, $q2)),
    "limiter_graph" => round(quantile($dataset, $q2m)),
    "limiter_lower" => round($teams ? 
      ceil(quantile($dataset, $q1)/$teams) :
      quantile($dataset, $q3))
  ];
}

$limiters = calculate_limiters($picks, $result['random']['teams_on_event'] ?? null);

//compatibility
$limiter = $limiters['limiter_higher'];
$limiter_graph = $limiters['limiter_graph'];
$limiter_lower = $limiters['limiter_lower'];
$limiter_middle = $limiters['limiter_middle'];
$limiter_median = $limiters['median'];
$limiter_quantile = $limiters['limiter_quantile'];

echo <<<LIMITERS
[ ] Limiter: $limiter
[ ] Limiter for graphs: $limiter_graph
[ ] Lower Limiter: $limiter_lower
[ ] Middle Limiter: $limiter_middle
[ ] Median Limiter: $limiter_median
[ ] Quantile: $limiter_quantile

LIMITERS;

?>
