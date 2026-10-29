<?php

include_once($root."/modules/view/functions/entity_cards.php");
include_once($root."/modules/view/functions/tier_lists.php");
include_once($root."/modules/view/functions/table_columns_toggle.php");
include_once($root."/modules/view/functions/explainer.php");

function tier_list_explainer($keys) {
  $lines = "";
  foreach ($keys as $key) $lines .= "<div class=\"line\">".locale_string($key)."</div>";

  return "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
      "<div class=\"explain-content\">".$lines."</div>".
    "</details>".
    simple_view_table_toggle();
}

/**
 * @param array $ranges
 * @param string $tier
 * @return string
 */
function tier_list_range_label($ranges, $tier) {
  if (empty($ranges[$tier])) return "";
  [ $min, $max ] = $ranges[$tier];
  return "<span class=\"tier-range\">".number_format($max, 1)." - ".number_format($min, 1)."</span>";
}

function tier_list_label_cell($tier, $ranges, $demoted_label) {
  if ($tier === 'not_meta') {
    return "<td class=\"tier-label\">".locale_string($demoted_label)."</td>";
  }
  return "<td class=\"tier-label\">".$tier.tier_list_range_label($ranges, $tier)."</td>";
}

function tier_list_render_cards($ids, $card, $ranks) {
  return implode('', array_map(function($id) use ($card, $ranks) {
    return $card($id, $ranks[$id] ?? null);
  }, $ids));
}

function rg_generator_tier_list($table_id, $data, $card, $demoted_label = 'tier_not_meta', $col_label = 'heroes') {
  $tiers = $data['tiers'] ?? [];
  $ranges = $data['ranges'] ?? [];
  $ranks = $data['base'] ?? [];
  if (empty($tiers)) return "";

  $rows = "";
  foreach (array_merge(TIER_LIST_TIERS, [ 'not_meta' ]) as $t) {
    if (empty($tiers[$t])) continue;
    $cls = $t === 'not_meta' ? 'tier-row-none' : 'tier-row-'.strtolower($t);
    $rows .= "<tr class=\"tier-row $cls".($t === 'not_meta' ? "" : " highlighted")."\">".
      tier_list_label_cell($t, $ranges, $demoted_label).
      "<td class=\"tier-cards\">".tier_list_render_cards($tiers[$t], $card, $ranks)."</td>".
    "</tr>";
  }

  if (empty($rows)) return "";

  return "<table id=\"$table_id\" class=\"list wide tier-list-table\"><thead><tr>".
      "<th>".locale_string("tier")."</th>".
      "<th>".locale_string($col_label)."</th>".
    "</tr></thead><tbody>".$rows."</tbody></table>";
}

function rg_generator_tier_list_columns($table_id, $columns, $card, $demoted_label = 'tier_not_meta') {
  $columns = array_values(array_filter($columns, function($c) { return !empty($c['tiers']); }));
  if (empty($columns)) return "";

  $placed = [];
  $unplaced = [];
  $unplaced_ranks = [];
  foreach ($columns as $c) {
    foreach (($c['base'] ?? []) as $id => $r) {
      if (!isset($unplaced_ranks[$id])) $unplaced_ranks[$id] = $r;
    }
    foreach (TIER_LIST_TIERS as $t) {
      foreach ($c['tiers'][$t] ?? [] as $id) $placed[$id] = true;
    }
    foreach ($c['tiers']['not_meta'] ?? [] as $id) $unplaced[$id] = true;
  }
  $unplaced = array_keys(array_diff_key($unplaced, $placed));

  $groups = [];
  $head = "";
  foreach ($columns as $c) {
    $g = $c['group'] ?? null;
    $groups[] = $g;
    $colgr = $g === null ? "" : " data-col-group=\"".str_replace('.', '-', $g)."\"";
    $head .= "<th$colgr>".$c['label']."</th>";
  }

  $rows = "";
  foreach (TIER_LIST_TIERS as $t) {
    $any = false;
    foreach ($columns as $c) {
      if (!empty($c['tiers'][$t])) { $any = true; break; }
    }
    if (!$any) continue;

    $rows .= "<tr class=\"tier-row tier-row-".strtolower($t)." highlighted\">".
      "<td class=\"tier-label\">".$t."</td>";
    foreach ($columns as $c) {
      $g = $c['group'] ?? null;
      $colgr = $g === null ? "" : " data-col-group=\"".str_replace('.', '-', $g)."\"";
      $col_card = $c['card'] ?? $card;
      $rows .= "<td class=\"tier-cards\"$colgr>".
        (empty($c['tiers'][$t])
          ? ""
          : tier_list_range_label($c['ranges'] ?? [], $t).
            tier_list_render_cards($c['tiers'][$t], $col_card, $c['base'] ?? [])).
      "</td>";
    }
    $rows .= "</tr>";
  }

  if (!empty($unplaced)) {
    $rows .= "<tr class=\"tier-row tier-row-none\">".
      "<td class=\"tier-label\">".locale_string($demoted_label)."</td>".
      "<td class=\"tier-cards\" colspan=\"".count($columns)."\">".
        tier_list_render_cards($unplaced, $card, $unplaced_ranks).
      "</td>".
    "</tr>";
  }

  if (empty($rows)) return "";

  $res = "";
  $toggle_groups = array_values(array_filter($groups, function($g) { return $g !== null; }));
  if (!empty($toggle_groups)) {
    $res .= table_columns_toggle($table_id, $toggle_groups, true);
  }

  return $res."<table id=\"$table_id\" class=\"list wide tier-list-table tier-list-columns\"><thead><tr>".
      "<th>".locale_string("tier")."</th>".$head.
    "</tr></thead><tbody>".$rows."</tbody></table>";
}

function rg_generator_tier_list_entity_heroes($table_id, $stats, $games, $desc) {
  $data = tier_list_entity_heroes($stats, $games);

  $res = tier_list_explainer([ $desc ]);
  if ($data === null) {
    return $res."<div class=\"content-text\">".locale_string("stats_empty")."</div>";
  }

  return $res.rg_generator_tier_list(
    $table_id,
    $data,
    function($hid, $rank = null) use ($stats, $games) { return lrg_hero_card_record($hid, $stats[$hid] ?? null, $rank, $games); },
    'tier_unplaced', 'heroes'
  );
}
