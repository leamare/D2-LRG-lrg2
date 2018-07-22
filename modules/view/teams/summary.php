<?php
include_once("$root/modules/view/functions/links.php");

function rg_view_generate_teams_summary() {
  global $report;

  $res  = "<div class=\"content-text\">".locale_string("desc_teams_summary")."</div>";

  uasort($report['teams'], function($a, $b) {
    if($a['matches_total'] == $b['matches_total']) return 0;
    else return ($a['matches_total'] < $b['matches_total']) ? 1 : -1;
  });

  $percentages = [
    "rad_ratio",
    "radiant_wr",
    "dire_wr"
  ];

  $aliases = [
    "wards_placed" => "wards_placed_s",
    "sentries_placed" => "sentries_placed_s",
    "wards_destroyed" => "wards_destroyed_s",
    "radiant_wr" => "rad_wr_s",
    "dire_wr" => "dire_wr_s",
    "avg_match_len" => "duration"
  ];

  $res .= "<table id=\"teams-summary\" class=\"list wide\">";

  foreach($report['teams'] as $team_id => $team) {
    $keys = array_keys($team['averages']);
    break;
  }

  $table_id = "teams-summary";
  $i = 0;

  $res .= "<tr class=\"thead\">".
            "<th onclick=\"sortTable(".($i++).",'$table_id');\">".locale_string("team_name")."</th>".
            "<th onclick=\"sortTableNum(".($i++).",'$table_id');\">".locale_string("matches_s")."</th>".
            "<th onclick=\"sortTableNum(".($i++).",'$table_id');\">".locale_string("winrate")."</th>";
  foreach($keys as $k) {
    if (isset($aliases[$k])) $k = $aliases[$k];
      $res .= "<th onclick=\"sortTableNum(".($i++).",'$table_id');\">".locale_string($k)."</th>";
  }

  $res .= "</tr>";

  foreach($report['teams'] as $team_id => $team) {
    $res .= "<tr>".
              "<td>".team_link($team_id)."</td>".
              "<td>".$team['matches_total']."</td>".
              "<td>".number_format($team['wins']*100/$team['matches_total'],2)."%</td>";

    foreach($team['averages'] as $k => $v) {
        $res .= "<td>".
                number_format($v*(in_array($k, $percentages) ? 100 : 1),
                  ($v > 1000) ? 0 : (
                      ($v > 100) ? 1 : 2
                    )
                  ).
                (in_array($k, $percentages) ? "%" : "")."</td>";
    }
    $res .= "</tr>";
  }
  $res .= "</table>";

  return $res;
}

?>