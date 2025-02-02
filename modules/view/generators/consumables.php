<?php

include_once($root."/modules/view/functions/hero_name.php");
include_once($root."/modules/view/functions/player_name.php");
include_once($root."/modules/view/functions/explainer.php");

function rg_generator_sticonsumables($table_id, $ishero, $selected_id, $selected_rid, $data, $context, $is_roles_limit) {
  $locres = "";

  $lc = "locale_string";

  if ($is_roles_limit) {
    $locres .= "<div class=\"content-text info\">".
      locale_string("sti_builds_roles_limited").
    "</div>";
  }

  $locres .= explainer_block(locale_string("sti_consumables_explainer"));

  if (strpos($table_id, "-team") !== false) {
    $locres .= "<div class=\"content-text alert\">".locale_string("sti_consumables_partial_notice")."</div>";
  }

  $matches_total = $context['m'];

  if (!$matches_total) {
    return "<div class=\"content-text\">".
      locale_string("stats_empty").
    "</div>";
  }

  $locres .= table_columns_toggle($table_id, [
    'sti_consumables_5m',
    'sti_consumables_10m',
    'sti_consumables_all',
  ], true);

  $locres .= search_filter_component($table_id, true);

  $head = [ "matches", "item_time_mean", "min", "item_time_q1", "median", "item_time_q3", "max" ];

  $cols = function($blk) use ($head) {
    return "<th class=\"separator\" data-col-group=\"sti_consumables_$blk\">".
      implode("</th><th data-col-group=\"sti_consumables_$blk\">", array_map(function($a) {
        return locale_string($a);
      }, $head)).
    "</th>";
  };

  $locres .= <<<TABLE
    <table id="$table_id" class="list wide sortable">
      <thead>
        <tr class="overhead">
          <th width="15%"></th>
          <th colspan="7" class="separator" data-col-group="sti_consumables_5m">{$lc("sti_consumables_5m")}</th>
          <th colspan="7" class="separator" data-col-group="sti_consumables_10m">{$lc("sti_consumables_10m")}</th>
          <th colspan="7" class="separator" data-col-group="sti_consumables_all">{$lc("sti_consumables_all")}</th>
        </tr>
        <tr>
          <th>{$lc("item")}</th>
          {$cols("5m")}
          {$cols("10m")}
          {$cols("all")}
        </tr>
      </thead>
      <tbody>
  TABLE;

  uasort($data['all'], function($b, $a) {
    return $a['matches'] <=> $b['matches'];
  });

  foreach ($data['all'] as $iid => $stats) {
    if (empty($stats)) continue;

    $locres .= "<tr data-value-matches=\"{$stats['matches']}\">".
      "<td>".item_full_link($iid)."</td>".
      implode('', array_map(function($el, $blk) use (&$matches_total) {
        if (empty($el)) {
          return "<td class=\"separator\" data-col-group=\"sti_consumables_$blk\">-</td>".
            "<td data-col-group=\"sti_consumables_$blk\">-</td>".
            "<td data-col-group=\"sti_consumables_$blk\">-</td>".
            "<td data-col-group=\"sti_consumables_$blk\">-</td>".
            "<td data-col-group=\"sti_consumables_$blk\">-</td>".
            "<td data-col-group=\"sti_consumables_$blk\">-</td>".
            "<td data-col-group=\"sti_consumables_$blk\">-</td>";
        }
        return "<td class=\"separator\" data-col-group=\"sti_consumables_$blk\">{$el['matches']}</td>".
          "<td data-col-group=\"sti_consumables_$blk\">".number_format($el['total']/$matches_total, 1)."</td>".
          "<td data-col-group=\"sti_consumables_$blk\">{$el['min']}</td>".
          "<td data-col-group=\"sti_consumables_$blk\">{$el['q1']}</td>".
          "<td data-col-group=\"sti_consumables_$blk\">{$el['med']}</td>".
          "<td data-col-group=\"sti_consumables_$blk\">{$el['q3']}</td>".
          "<td data-col-group=\"sti_consumables_$blk\">{$el['max']}</td>";
      }, [
        $data['5m'][$iid] ?? [],
        $data['10m'][$iid] ?? [],
        $data['all'][$iid],
      ], [ '5m', '10m', 'all' ])).
    "</tr>";
  }
  $locres .= "</tbody></table>";

  return $locres;
}