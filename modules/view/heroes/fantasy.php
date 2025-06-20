<?php
include_once($root."/modules/view/generators/summary.php");
include_once($root."/modules/view/functions/explainer.php");

$modules['heroes']['fantasy'] = "";

function rg_view_generate_heroes_fantasy() {
  global $report;

  if (is_wrapped($report['fantasy']['heroes_mvp'])) { 
    $report['fantasy']['heroes_mvp'] = unwrap_data($report['fantasy']['heroes_mvp']);
  }

  $res = explainer_block(locale_string("fantasy_summary_desc"));

  $postfixes = [
    'awards' => [ 'mvp', 'mvp_losing', 'core', 'support', 'lvp' ],
    'fantasy' => [ 'total_points', 'kda', 'farm', 'combat', 'objectives' ],
  ];

  // Add _fantasy postfix to keys
  $fantasy_data = [];
  foreach ($report['fantasy']['heroes_mvp'] as $hero_id => $data) {
    $fantasy_data[$hero_id] = [
      'matches' => $data['matches_s'],
      'total_awards' => $data['total_awards'],
    ];
    foreach ($postfixes as $type => $keys) {
      foreach ($keys as $key) {
        $fantasy_data[$hero_id][$key . '_' . $type] = $data[$key];
      }
    }
  }

  $res .= rg_generator_summary("heroes-fantasy", $fantasy_data);

  return $res;
}

