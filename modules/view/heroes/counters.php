<?php

// $modules['heroes']['counters'] = [];

function rg_view_generate_heroes_counters() {
  global $report, $parent, $root, $unset_module, $mod;
  if($mod == $parent."counters") $unset_module = true;
  $parent_module = $parent."counters-";
  $res = [];
  include_once($root."/modules/view/generators/combos.php");

  $hvh = []; 
  $devs = [];

  if (check_module($parent_module."pairs") || check_module($parent_module."graph") || !isset($report['hvh_v']) || !check_module($parent_module."pairs_var")) {
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
        'wr_diff' => round($line['h1winrate']-$report['pickban'][$line['heroid1']]['winrate_picked'], 5),
        'expectation' => $line['exp'] ?? 0
      ];
      $hvh[] = $p;
      $devs[] = ($p['matches']-$p['expectation']);
    }
  } else {
    if (is_wrapped($report['hvh_v'])) {
      $report['hvh_v'] = unwrap_data($report['hvh_v']);
    }
    
    foreach ($report['hvh_v'] as $line) {
      if ($line['matches'] < $report['settings']['limiter_combograph']) 
        continue;
      
      $srcline = $line['heroid1'];
      $line['heroid1'] = explode('-', $line['heroid1']);
      $line['heroid2'] = explode('-', $line['heroid2']);
      $p = [
        'heroid1' => +$line['heroid1'][0],
        'variant1' => +$line['heroid1'][1],
        'heroid2' => +$line['heroid2'][0],
        'variant2' => +$line['heroid2'][1],
        'matches' => $line['matches'],
        'winrate' => $line['h1winrate'],
        'wr_diff' => round($line['h1winrate']-($report['hero_variants'][$srcline]['w']/$report['hero_variants'][$srcline]['m']), 5),
        'expectation' => $line['exp'] ?? 0
      ];
      $hvh[] = $p;
      $devs[] = ($p['matches']-$p['expectation']);
    }
  }

  uasort($hvh, function($a, $b) { return $b['matches'] <=> $a['matches']; });

  $res['pairs'] = "";
  if (check_module($parent_module."pairs")) {
    $res['pairs'] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
          "<div class=\"explain-content\">".
            "<div class=\"line\">".locale_string("desc_heroes_counters_pairs", [ "lim"=>$report['settings']['limiter_combograph']+1 ] )."</div>".
          "</div>".
        "</details>";

    $res['pairs'] .=  rg_generator_combos("hero-pairs", $hvh, null);
  }

  if (isset($report['hvh_v'])) {
    $res['pairs_var'] = "";
    if (check_module($parent_module."pairs_var")) {
      $res['pairs_var'] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
            "<div class=\"explain-content\">".
              "<div class=\"line\">".locale_string("desc_heroes_counters_pairs", [ "lim"=>$report['settings']['limiter_combograph']+1 ] )."</div>".
            "</div>".
          "</details>";

      $res['pairs_var'] .=  rg_generator_combos("hero-pairs-variants", $hvh, null, true, true);
    }
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
    $hvh = array_filter($hvh, function($a) use ($med_deviation) { return ($a['matches'] - $a['expectation']) > $med_deviation; });

    $d = [];

    foreach ($hvh as $l) {
      if (!isset($d[ $l['heroid1'] ]))
        $d[ $l['heroid1'] ] = $report['pickban'][ $l['heroid1'] ];
      if (!isset($d[ $l['heroid2'] ]))
        $d[ $l['heroid2'] ] = $report['pickban'][ $l['heroid2'] ];
    }

    $res['graph'] .= rg_generator_meta_graph("heroes-meta-graph", $hvh, $d);
  }

  return $res;
}
