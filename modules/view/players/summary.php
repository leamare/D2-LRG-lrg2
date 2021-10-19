<?php
include_once($root."/modules/view/generators/summary.php");

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

  $res = rg_generator_summary("players-summary", $report['players_summary'], false);

  $res .= "<div class=\"content-text\">".locale_string("desc_players_summary")."</div>";

  return $res;
}

?>
