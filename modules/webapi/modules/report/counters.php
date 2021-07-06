<?php 

$endpoints['counters'] = function($mods, $vars, &$report) {
  if (isset($vars['team'])) {
    throw new \Exception("No team allowed");
  } else if (isset($vars['region'])) {
    throw new \Exception("No region allowed");
  }

  $res = [];

  if (!in_array("heroes", $mods)) {
    throw new \Exception("Incorrect module specified");
  }

  $hvh = []; 
  $devs = [];
  $games = [];
  $wr = [];

  if (is_wrapped($report['hvh'])) {
    $report['hvh'] = unwrap_data($report['hvh']);
  }
  
  foreach ($report['hvh'] as $line) {
    if ($line['matches'] < $report['settings']['limiter_combograph']) 
      continue;
    
    $p = [
      'heroid1' => $line['heroid1'],
      'heroid2' => $line['heroid2'],
      'matches' => $line['matches'],
      'winrate' => $line['h1winrate'],
      'wins' => round($line['h1winrate']*$line['matches']),
      'wr_diff' => round($line['h1winrate']-$report['pickban'][$line['heroid1']]['winrate_picked'], 5),
      'expectation' => $line['exp'],
      'dev_pct' => round( ($line['matches'] - $line['exp']) / $line['matches'], 5)
    ];
    
    $games[] = $line['matches'];
    $wr[] = $line['h1winrate'];
    $hvh[] = $p;
    $devs[] = ($p['matches']-$p['expectation']);
  }
  uasort($hvh, function($a, $b) { return $b['matches'] <=> $a['matches']; });

  if (in_array("graph", $mods)) {
    sort($devs);
    $med_deviation = $devs[ round( count($devs) * 0.75 ) ];
    $hvh = array_filter($hvh, function($a) use ($med_deviation) { return ($a['matches'] - $a['expectation']) > $med_deviation; });

    $d = [];

    foreach ($hvh as $l) {
      if (!isset($d[ $l['heroid1'] ])) {
        $d[ $l['heroid1'] ] = [
          'hero_id' => $l['heroid1'],
          'matches' => $report['pickban'][ $l['heroid1'] ]['matches_total'],
          'matches_picked' => $report['pickban'][ $l['heroid1'] ]['matches_picked'],
          'winrate_picked' => $report['pickban'][ $l['heroid1'] ]['winrate_picked']
        ];
      }
      if (!isset($d[ $l['heroid2'] ])) {
        $d[ $l['heroid2'] ] = [
          'hero_id' => $l['heroid2'],
          'matches' => $report['pickban'][ $l['heroid2'] ]['matches_total'],
          'matches_picked' => $report['pickban'][ $l['heroid2'] ]['matches_picked'],
          'winrate_picked' => $report['pickban'][ $l['heroid2'] ]['winrate_picked']
        ];
      }
    }

    $res['type'] = "graph";
    $res['data'] = [
      "limiter" => $report['settings']['limiter_combograph'],
      "max_wr" => max($wr),
      "max_games" => max($games),
      "nodes" => $d,
      "pairs" => array_values($hvh)
    ];
  } else {
    $res['type'] = "pairs";
    $res['data'] = $hvh;
  }

  return $res;
};
