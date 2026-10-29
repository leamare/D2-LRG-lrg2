<?php

function rg_view_generate_tierlists_heroes_meta($parent_module) {
  global $report, $root, $unset_module, $mod;
  if ($mod == rtrim($parent_module, "-")) $unset_module = true;

  include_once($root."/modules/view/generators/tier_list.php");

  $res = [];

  if (is_wrapped($report['hero_positions'] ?? null)) {
    $report['hero_positions'] = unwrap_data($report['hero_positions']);
  }

  $explainer = tier_list_explainer([ "desc_tierlists_heroes", "desc_tierlists_heroes_2" ]);

  # total
  $res['total'] = "";
  if (check_module($parent_module."total")) {
    $data = tier_list_heroes_overall($report);
    $res['total'] = $explainer.
      ($data === null
        ? "<div class=\"content-text\">".locale_string("stats_empty")."</div>"
        : rg_generator_tier_list("tierlists-heroes-meta-total", $data, 'lrg_hero_card'));
  }

  if (empty($report['hero_positions'])) return $res;

  $role_totals = tier_list_hero_role_totals($report);

  $role_card = function($core, $lane) use (&$report, $role_totals) {
    $stats = $report['hero_positions'][$core][$lane];
    $role = "position_$core.$lane";
    return function($hid, $rank = null) use ($stats, $role_totals, $role) {
      return lrg_hero_card_role($hid, $stats[$hid] ?? [], $role_totals[$hid] ?? 0, $role, $rank);
    };
  };

  $res['roles'] = "";
  if (check_module($parent_module."roles")) {
    $columns = [];
    for ($i=1; $i>=0; $i--) {
      for ($j=($i ? 0 : 5); $j<6 && $j>=0; ($i ? $j++ : $j--)) {
        if (empty($report['hero_positions'][$i][$j])) continue;
        $data = tier_list_heroes_position($report, $i, $j);
        if ($data === null) continue;
        $columns[] = [
          'label' => locale_string("position_$i.$j"),
          'group' => "position_$i.$j",
          'tiers' => $data['tiers'],
          'ranges' => $data['ranges'],
          'base' => $data['base'],
          'card' => $role_card($i, $j),
        ];
      }
    }
    $res['roles'] = $explainer.
      (empty($columns)
        ? "<div class=\"content-text\">".locale_string("stats_empty")."</div>"
        : rg_generator_tier_list_columns("tierlists-heroes-meta-roles", $columns, 'lrg_hero_card'));
  }

  for ($i=1; $i>=0; $i--) {
    for ($j=($i ? 0 : 5); $j<6 && $j>=0; ($i ? $j++ : $j--)) {
      if (empty($report['hero_positions'][$i][$j])) continue;

      $key = "position_$i.$j";
      $res[$key] = "";
      if (!check_module($parent_module.$key)) continue;

      $data = tier_list_heroes_position($report, $i, $j);
      $res[$key] = $explainer.
        ($data === null
          ? "<div class=\"content-text\">".locale_string("stats_empty")."</div>"
          : rg_generator_tier_list("tierlists-heroes-meta-$i-$j", $data, $role_card($i, $j)));
    }
  }

  return $res;
}
