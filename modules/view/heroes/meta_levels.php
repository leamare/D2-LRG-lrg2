<?php

include_once($root."/modules/view/functions/meta_levels.php");
include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/entity_cards.php");

$modules['heroes']['meta_levels'] = "";

function rg_view_generate_heroes_meta_levels() {
  global $report;

  $data = meta_levels_from_report($report);
  if (empty($data)) return "<div class=\"content-text\">".locale_string("stats_empty")."</div>";

  generate_positions_strings();

  $layers = $data['layers'];
  $projections = $data['projections'] ?? [];

  $res = "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
      "<div class=\"explain-content\">".
        "<div class=\"line\">".locale_string("desc_meta_levels")."</div>".
        "<div class=\"line\">".locale_string("desc_meta_levels_2")."</div>".
        "<div class=\"line\">".locale_string("desc_meta_levels_3")."</div>".
        "<div class=\"line\">".locale_string("desc_meta_levels_4")."</div>".
        "<div class=\"line\">".locale_string("desc_meta_levels_5")."</div>".
      "</div>".
    "</details>";

  $res .= "<table class=\"list wide meta-levels-table\"><thead><tr>".
      "<th>".locale_string("meta_levels_col_level")."</th>".
      "<th>".locale_string("meta_levels_col_core")."</th>".
      "<th>".locale_string("meta_levels_col_combo")."</th>".
    "</tr></thead><tbody>";

  foreach ($layers as $i => $layer) {
    $res .= "<tr>".
      "<td>".locale_string("meta_level_n", [ "n" => $i ])."</td>".
      "<td class=\"meta-level-cards\">".meta_levels_render_heroes($layer['core'])."</td>".
      "<td class=\"meta-level-cards\">".meta_levels_render_heroes($layer['combo'])."</td>".
    "</tr>";
  }

  foreach ($projections as $p => $projection) {
    $res .= "<tr>".
      "<td>".locale_string("meta_levels_projection", [ "n" => count($layers) + $p ])."</td>".
      "<td class=\"meta-level-cards\">".meta_levels_render_heroes($projection['core'])."</td>".
      "<td class=\"meta-level-cards\">".meta_levels_render_heroes($projection['combo'] ?? [])."</td>".
    "</tr>";
  }

  $res .= "</tbody></table>";

  return $res;
}

function meta_levels_render_heroes($ids) {
  if (empty($ids)) return "";

  $res = "";
  foreach ($ids as $hid) {
    $res .= lrg_hero_card($hid);
  }
  return $res;
}
