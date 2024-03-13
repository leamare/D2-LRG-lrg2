<?php

include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");

function rg_generator_stibuilds($table_id, $ishero, $selected_id, $selected_rid, $data) {
  // TODO: Explainers

  $locres = "";

  $lc = "locale_string";

  $locres .= search_filter_component($table_id);
  
  $locres .= <<<TABLE
    <table id="$table_id" class="list sortable">
      <thead>
        <tr>
          <th>{$lc("build")}</th>
          <th>{$lc("matches")}</th>
          <th>{$lc("ratio")}</th>
          <th>{$lc("winrate")}</th>
          <th>{$lc("lane_wr")}</th>
        </tr>
      </thead>
      <tbody>
  TABLE;
  foreach ($data as $stats) {
    if (empty($stats)) continue;
    
    $build = implode(" ", array_map(function($a) {
      return "<a title=\"".item_name($a)."\">".item_icon($a, "bigger")."</a>";
    }, $stats['build']));

    $locres .= "<tr data-value-matches=\"{$stats['matches']}\">".
      "<td>".$build."</td>".
      "<td>".number_format($stats['matches'], 0)."</td>".
      "<td>".number_format($stats['ratio'] * 100, 2)."%</td>".
      "<td>".number_format($stats['winrate'] * 100, 2)."%</td>".
      "<td>".number_format($stats['lane_wr'] * 100, 2)."%</td>".
    "</tr>";
  }
  $locres .= "</tbody></table>";

  return $locres;
}