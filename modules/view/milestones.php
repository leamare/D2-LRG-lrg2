<?php
$modules['milestones'] = '';

function rg_view_generate_milestones() {
  global $report, $root;

  $res = "";

  $res .= "<div class=\"content-header\">".locale_string("milestones_total")."</div>";
  $res .= "<table class=\"list\" id=\"milestones-total\">";
  foreach ($report['milestones']['total'] as $type => $value) {
    $res .= "<tr><td>".locale_string($type)."</td><td>".
    (strpos($type, "time") !== FALSE || strpos($type, "stuns") !== FALSE ? convert_time_seconds($value[0]) : number_format($value[0])).
    "</td></tr>";
  }
  $res .= "</table>";

  if (isset($report['milestones']['players'])) {
    $res .= "<div class=\"content-header\">".locale_string("milestones_players")."</div>";
    $res .= "<div class=\"small-list-wrapper\">";
    foreach ($report['milestones']['players'] as $type => $list) {
      $res .= "<table id=\"milestones-players-$type\" class=\"list list-fixed list-small\">".
            "<caption>".locale_string($type)."</caption><thead><tr>".
            "<th width=\"60%\">".locale_string("player")."</th>".
            "<th>".locale_string("value")."</th></tr></thead>";
      foreach($list as $pl => $val) {
        $res .= "<td>".player_link($pl)."</td><td>".
        (strpos($type, "time") !== FALSE || strpos($type, "stuns") !== FALSE ? convert_time_seconds($val) : number_format($val)).
        "</td></tr>";
      }
      $res .= "</table>";
    }
    $res .= "</div>";
  }

  if (isset($report['milestones']['teams'])) {
    $res .= "<div class=\"content-header\">".locale_string("milestones_teams")."</div>";
    $res .= "<div class=\"small-list-wrapper\">";
    foreach ($report['milestones']['teams'] as $type => $list) {
      $res .= "<table id=\"milestones-teams-$type\" class=\"list list-fixed list-small\">".
            "<caption>".locale_string($type)."</caption><thead><tr>".
            "<th width=\"3%\"></th><th width=\"45%\">".locale_string("team")."</th>".
            "<th>".locale_string("value")."</th></tr></thead>";
      foreach($list as $pl => $val) {
        $res .= "<td>".team_logo($pl)."</td><td>".team_link($pl)."</td><td>".
        (strpos($type, "time") !== FALSE || strpos($type, "stuns") !== FALSE ? convert_time_seconds($val) : number_format($val)).
        "</td></tr>";
      }
      $res .= "</table>";
    }
    $res .= "</div>";
  }

  if (isset($report['milestones']['heroes'])) {
    $res .= "<div class=\"content-header\">".locale_string("milestones_heroes")."</div>";
    $res .= "<div class=\"small-list-wrapper\">";
    foreach ($report['milestones']['heroes'] as $type => $list) {
      $res .= "<table id=\"milestones-heroes-$type\" class=\"list list-fixed list-small\">".
            "<caption>".locale_string($type)."</caption><thead><tr>".
            "<th width=\"3%\"></th><th width=\"45%\">".locale_string("hero")."</th>".
            "<th>".locale_string("value")."</th></tr></thead>";
      foreach($list as $pl => $val) {
        $res .= "<td>".hero_portrait($pl)."</td><td>".hero_link($pl)."</td><td>".
        (strpos($type, "time") !== FALSE || strpos($type, "stuns") !== FALSE ? convert_time_seconds($val) : number_format($val)).
        "</td></tr>";
      }
      $res .= "</table>";
    }
    $res .= "</div>";
  }

  return $res;
}
