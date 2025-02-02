<?php

// $modules['heroes']['counters'] = [];

function rg_view_generate_players_enemies() {
  global $report, $parent, $root, $unset_module, $mod;
  if($mod == $parent."pvpover") $unset_module = true;
  $parent_module = $parent."pvpover-";
  $res = [];
  include_once($root."/modules/view/generators/combos.php");

  $pvp = []; 
  $devs = [];

  if (is_wrapped($report['pvp'])) {
    $report['pvp'] = unwrap_data($report['pvp']);
  }
  
  foreach ($report['pvp'] as $line) {
    if ($line['matches'] < $report['settings']['limiter_combograph']) 
      continue;

    $p = [
      'playerid1' => $line['playerid1'],
      'playerid2' => $line['playerid2'],
      'matches' => $line['matches'],
      'winrate' => $line['p1winrate'],
      'wr_diff' => round($line['p1winrate']-$report['players_summary'][$line['playerid1']]['winrate_s'], 5),
      'expectation' => $line['exp'] ?? null
    ];
    if (!isset($p['expectation'])) {
      $mt = $report['main']['matches_total'] ?? $report['main']['matches'] ?? $report['random']['matches_total'];
      $p['expectation'] = round(
        ($report['players_summary'][$line['playerid1']]['matches_s']/$mt) 
        * ($report['players_summary'][$line['playerid2']]['matches_s']/$mt)
        * $mt / 2
      );
    }
    $pvp[] = $p;
    $devs[] = ($p['matches']-$p['expectation']);
  }
  uasort($pvp, function($a, $b) { return $b['matches'] <=> $a['matches']; });

  $res['pairs'] = "";
  if (check_module($parent_module."pairs")) {
    $res['pairs'] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
          "<div class=\"explain-content\">".
            "<div class=\"line\">".locale_string("desc_heroes_counters_pairs", [ "lim"=>$report['settings']['limiter_combograph']+1 ] )."</div>".
          "</div>".
        "</details>";

    $res['pairs'] .=  rg_generator_combos("players-pairs", $pvp, null, false);
  }

  $res['graph'] = "";
  if (check_module($parent_module."graph")) {
    $res['graph'] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
          "<div class=\"explain-content\">".
            "<div class=\"line\">".locale_string("desc_heroes_counters_pairs_graph", [ "lim"=>$report['settings']['limiter_combograph']+1 ] )."</div>".
          "</div>".
        "</details>";

    sort($devs);
    $med_deviation = $devs[ round( count($devs) * 0.75 ) ];
    $pvp = array_filter($pvp, function($a) use ($med_deviation) { return ($a['matches'] - $a['expectation']) > $med_deviation; });

    $d = [];

    foreach ($pvp as $l) {
      if (!isset($d[ $l['playerid1'] ]))
        $d[ $l['playerid1'] ] = $report['pickban'][ $l['playerid1'] ];
      if (!isset($d[ $l['playerid2'] ]))
        $d[ $l['playerid2'] ] = $report['pickban'][ $l['playerid2'] ];
    }

    $res['graph'] .= rg_generator_meta_graph("players-foe-graph", $pvp, $report['players_additional'], false);
  }

  return $res;
}
