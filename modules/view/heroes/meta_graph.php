<?php

include_once($root."/modules/view/generators/meta_graph.php");

$modules['heroes']['meta_graph'] = "";

function rg_view_generate_heroes_meta_graph() {
  global $report;

  if (empty($report['hero_combos_graph']) && ( isset($report['hero_pairs']) || isset($report['hph']) ) ) {
    if (!empty($report['hph']) && is_wrapped($report['hph'])) {
      $report['hph'] = unwrap_data($report['hph']);
    }

    if (empty($report['hero_pairs'])) {
      $report['hero_pairs'] = rg_generate_hero_pairs();
    }

    $report['hero_combos_graph'] = [];
    $dev = [];

    foreach ($report['hero_pairs'] as &$pair) {
      $pair['dev_pct'] = ($pair['matches'] - $pair['expectation'])/$pair['matches'];
      $dev[] = $pair['dev_pct'];
    }
    sort($dev);
    $lim = $dev[ round( count($dev)*0.6 ) ];
    
    foreach ($report['hero_pairs'] as $pair) {
      if ($pair['dev_pct'] < $lim) continue;

      $report['hero_combos_graph'][] = [
        'heroid1' => $pair['heroid1'],
        'heroid2' => $pair['heroid2'],
        'matches' => $pair['matches'],
        'wins' => $pair['wins'] ?? round($pair['matches'] * $pair['winrate']),
        'winrate' => $pair['winrate'],
        'dev_pct' => $pair['dev_pct'],
      ];
    }
  }

  $locale_settings = ["lim" => $report['settings']['limiter_combograph']+1,
      "per" => "35%"
  ];

  $res = "<div class=\"content-text\">".locale_string("desc_meta_graph", $locale_settings)."</div>";

  $res .= rg_generator_meta_graph("heroes-meta-graph", $report['hero_combos_graph'], $report['pickban']);

  $res .= "<div class=\"content-text\">".locale_string("desc_meta_graph_add", $locale_settings)."</div>";

  return $res;
}

?>
