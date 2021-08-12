<?php
include_once("$root/modules/view/generators/tvt_unwrap_data.php");
include_once("$root/modules/view/generators/tvt_grid.php");

function rg_view_generate_teams_grid() {
  global $report;

  $team_ids = array_keys($report['teams']);
  $tvt = rg_generator_tvt_unwrap_data($report['tvt'], $report['teams']);

  if (isset($report['match_participants_teams'])) {
    foreach ($report['match_participants_teams'] as $mid => $teams) {
      if (!isset($tvt[$teams['dire']][$teams['radiant']]['matchids'])) {
        $tvt[$teams['dire']][$teams['radiant']]['matchids'] = [];
      }
      $tvt[$teams['dire']][$teams['radiant']]['matchids'][] = $mid;

      if (!isset($tvt[$teams['radiant']][$teams['dire']]['matchids'])) {
        $tvt[$teams['radiant']][$teams['dire']]['matchids'] = [];
      }
      $tvt[$teams['radiant']][$teams['dire']]['matchids'][] = $mid;
    }
  }

  $res  = "<div class=\"content-text\">".locale_string("desc_tvt")."</div>";

  $res .= rg_generator_tvt_grid("teams-tvt", $tvt, $report['teams_interest'] ?? []);

  return $res;
}

?>
