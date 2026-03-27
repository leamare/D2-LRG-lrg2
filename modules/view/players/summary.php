<?php
include_once($root."/modules/view/generators/summary.php");
include_once($root."/modules/commons/volatility.php");
include_once($root."/modules/view/generators/pvp_unwrap_data.php");

$modules['players']['summary'] = "";

function rg_view_generate_players_summary() {
  global $report;

  if (isset($report['players_additional'])) {
    generate_positions_strings();

    if (isset($report['player_positions'])) {
      foreach($report['players_summary'] as $id => $player) {
        if (!isset($report['players_additional'][$id]['positions'])) continue;
        $position = reset($report['players_additional'][$id]['positions']);
        if (empty($position)) {
          $position = "unknown";
        } else {
          $position = "position_".$position["core"].".".$position["lane"];
        }
        $report['players_summary'][$id]['common_position'] = locale_string($position);
      }
    }
  }

  $inject = [];
  if (isset($report['pvp']) && isset($report['players_additional'])) {
    $winrates = [];
    foreach ($report['players_additional'] as $id => $pl) {
      if (!isset($pl['matches']) || $pl['matches'] <= 0) continue;
      $winrates[$id] = ['matches' => $pl['matches'], 'winrate' => $pl['won'] / max(1, $pl['matches'])];
    }
    $pvp = rg_generator_pvp_unwrap_data($report['pvp'], $winrates, false);
    foreach ($pvp as $playerid => $pairs) {
      if (!is_numeric($playerid)) continue;
      $vol = rg_volatility_metrics($pairs);
      $inject[$playerid] = [
        'volatility_normalized_relative'      => $vol['normalized_relative'],
        'volatility_normalized_total'         => $vol['normalized_total'],
        'volatility_normalized_avg_advantage' => $vol['normalized_avg_advantage'],
      ];
    }
    unset($pvp, $winrates);
  }

  $res = rg_generator_summary("players-summary", $report['players_summary'], false, false, false, $inject);

  $res .= "<div class=\"content-text\">".locale_string("desc_players_summary")."</div>";

  return $res;
}

?>
