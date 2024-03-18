<?php

include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");
include_once($root."/modules/view/functions/explainer.php");

function rg_generator_stitems($table_id, $ishero, $selected_id, $selected_rid, $data, $context, $is_roles_limit) {
  // TODO: Explainers

  $locres = "";
  $table = "";

  $lc = "locale_string";
  $matches_med = [];

  if ($is_roles_limit) {
    $locres .= "<div class=\"content-text info\">".
      locale_string("sti_builds_roles_limited").
    "</div>";
  }

  $locres .= explainer_block(locale_string("sti_items_explainer"));

  $table .= search_filter_component($table_id);

  $table .= <<<TABLE
    <table id="$table_id" class="list sortable">
      <thead>
        <tr>
          <th>{$lc("item")}</th>
          <th>{$lc("matches")}</th>
          <th>{$lc("purchase_rate")}</th>
          <th>{$lc("winrate")}</th>
          <th>{$lc("lane_wr")}</th>
        </tr>
      </thead>
      <tbody>
  TABLE;
  foreach ($data as $ciid => $stats) {
    $cnt = $ciid % 100;
    $item = floor($ciid / 100);
    $isfirst = $cnt > 1 ? 0 : 1;
    $matches_med[] = $stats['matches'];

    $table .= "<tr data-value-matches=\"{$stats['matches']}\" data-value-cnt=\"{$isfirst}\">".
      "<td>".item_full_link($item).($cnt > 1 ? " #".$cnt : "")."</td>".
      "<td>".number_format($stats['matches'], 0)."</td>".
      "<td>".number_format($stats['freq'] * 100, 2)."%</td>".
      "<td>".number_format($stats['wins'] * 100 / $stats['matches'], 2)."%</td>".
      "<td>".number_format($stats['lane_wins'] * 100 / $stats['matches'], 2)."%</td>".
    "</tr>";
  }
  $table .= "</tbody></table>";

  sort($matches_med);

  $locres .= filter_toggles_component($table_id, [
    'matches' => [
      'value' => $matches_med[ count($matches_med)/2 ],
      'label' => 'data_filter_matches'
    ],
    'cnt' => [
      'value' => 1,
      'label' => 'data_filter_first_purchase'
    ],
  ], $table_id).$table;

  return $locres;
}