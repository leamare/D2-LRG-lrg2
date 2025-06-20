<?php
include_once($root."/modules/view/generators/summary.php");
include_once($root."/modules/view/functions/explainer.php");

$modules['players']['fantasy'] = "";

function rg_view_generate_players_fantasy() {
  global $report;

  if (is_wrapped($report['fantasy']['players_mvp'])) {
    $report['fantasy']['players_mvp'] = unwrap_data($report['fantasy']['players_mvp']);
  }

  $res = explainer_block(locale_string("fantasy_summary_desc"));

  $postfixes = [
    'awards' => [ 'mvp', 'mvp_losing', 'core', 'support', 'lvp' ],
    'fantasy' => [ 'total_points', 'kda', 'farm', 'combat', 'objectives' ],
  ];
  
  $fantasy_data = [];
  foreach ($report['fantasy']['players_mvp'] as $player_id => $data) {
    $fantasy_data[$player_id] = [
      'matches' => $data['matches_s'],
      'total_awards' => $data['total_awards'],
    ];
    foreach ($postfixes as $type => $keys) {
      foreach ($keys as $key) {
        $fantasy_data[$player_id][$key . '_' . $type] = $data[$key];
      }
    }
  }

  $res .= rg_generator_summary("players-fantasy", $fantasy_data, false);

  return $res;
}
