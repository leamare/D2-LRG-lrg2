<?php

include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");
include_once($root."/modules/view/functions/explainer.php");

function rg_generator_stibuilds($table_id, $ishero, $selected_id, $selected_rid, $data, $context, $is_limit, $is_roles_limit) {
  $lc = "locale_string";

  $locres = "";
  $table = "";

  if ($is_limit || $is_roles_limit) {
    $locres .= "<div class=\"content-text ".(!$is_limit ? "info" : "notice")."\">".
      ($is_limit ? locale_string("sti_builds_limited", [ "limit" => $context['l'] ]) : "").
      ($is_limit && $is_roles_limit ? "<br />" : "").
      ($is_roles_limit ? locale_string("sti_builds_roles_limited") : "").
    "</div>";
  }

  $matches_med = [];

  $locres .= explainer_block(locale_string("sti_builds_explainer"));

  $table .= search_filter_component($table_id);
  
  $table .= <<<TABLE
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

    $matches_med[] = $stats['matches'];

    $table .= "<tr data-value-matches=\"{$stats['matches']}\">".
      "<td>".$build."</td>".
      "<td>".number_format($stats['matches'], 0)."</td>".
      "<td>".number_format($stats['ratio'] * 100, 2)."%</td>".
      "<td>".number_format($stats['winrate'] * 100, 2)."%</td>".
      "<td>".number_format($stats['lane_wr'] * 100, 2)."%</td>".
    "</tr>";
  }
  $table .= "</tbody></table>";

  if (!$is_limit) {
    sort($matches_med);
    $locres .= filter_toggles_component($table_id, [
      'matches' => [
        'value' => max($matches_med[ round(count($matches_med) * 0.75) ], round($context['m'] * 0.005)),
        'label' => 'data_filter_matches'
      ],
    ], $table_id);
  }
  
  $locres .= $table;

  return $locres;
}