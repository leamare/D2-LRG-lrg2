<?php

include_once($root."/modules/view/generators/consumables.php");

$modules['items']['sticonsumables'] = [];

function rg_view_generate_items_sti_consumables() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $leaguetag;

  if($mod == $parent."sticonsumables") $unset_module = true;
  $parent_module = $parent."sticonsumables-";

  $res = [];

  $hnames = $meta["heroes"];
  uasort($hnames, function($a, $b) {
    if($a['name'] == $b['name']) return 0;
    else return ($a['name'] > $b['name']) ? 1 : -1;
  });

  generate_positions_strings();

  $selected_rid = null;
  $selected_hid = 0;
  $selected_tag = 'total';

  $carryon["/^items-sticonsumables-(heroid\d+|total)$/"] = "/^items-sticonsumables-(heroid\d+|total)/";

  $roles = array_keys($report['starting_items']['consumables']['all']);

  $res['total'] = [];
  if(check_module($parent_module.'total')) {
    $selected_hid = 0;
    $selected_tag = "total";
  }
  foreach ($hnames as $hid => $name) {
    register_locale_string(hero_name($hid), "heroid".$hid);

    $res["heroid".$hid] = isset($report['starting_items']['consumables']['all'][0][$hid]) ? [] : "";

    if(check_module($parent_module."heroid".$hid)) {
      $selected_tag = "heroid".$hid;
      $selected_hid = $hid;
    }
  }

  $bcnt = 0;

  if (is_array($res[$selected_tag])) {
    if($mod == $parent_module.$selected_tag) $unset_module = true;
    $parent_module = $parent_module.$selected_tag."-";

    foreach ($roles as $rid) {
      if (isset($report['starting_items']['consumables']['all'][$rid][$selected_hid])) {
        $res[$selected_tag][ROLES_IDS[$rid]] = "";
        $bcnt++;

        if (check_module($parent_module.ROLES_IDS[$rid])) {
          $selected_rid = $rid;
        }
      }
    }
  }

  if (!$bcnt) {
    $locres = "<div class=\"content-text\">".
      locale_string("stats_empty").
    "</div>";

    $res[$selected_tag] = $locres;

    return $res;
  }

  if ($selected_rid === null) {
    foreach ($roles as $rid) {
      if (isset($report['starting_items']['consumables']['all'][$rid][$selected_hid])) {
        $selected_rid = $rid;
        break;
      }
    }
  }

  $data = [
    '5m' => null,
    '10m' => null,
    'all' => null,
  ];

  foreach ($data as $blk => $d) {
    if (empty($report['starting_items']['consumables'][$blk][$selected_rid][$selected_hid])) {
      $data[$blk] = [];
      continue;
    }
    $report['starting_items']['consumables'][$blk][$selected_rid][$selected_hid]['head'] = $report['starting_items']['cons_head'];
    $data[$blk] = unwrap_data($report['starting_items']['consumables'][$blk][$selected_rid][$selected_hid]);
  }

  $report['starting_items']['matches'][$selected_rid] = unwrap_data($report['starting_items']['matches'][$selected_rid]);

  $context = null;
  if ($selected_hid != 0) {
    $pbdata = $report['pickban'][$selected_hid] ?? [];

    if (isset($report['hero_laning']) && is_wrapped($report['hero_laning'])) {
      $report['hero_laning'] = unwrap_data($report['hero_laning']);
    }
    $lanedata = $report['hero_laning'][0][$selected_hid] ?? [];

    $maindata = $report['random'] ?? $report['main'];

    $context = [
      'matches' => $pbdata['matches_picked'] ?? 0,
      'winrate' => $pbdata['winrate_picked'] ?? 0,
      'pickrate' => ($pbdata['matches_picked'] ?? 0)/$maindata['matches_total'],
      'lane_wr' => $lanedata['lane_wr'] ?? null,
      'role_matches' => null,
      'role_winrate' => null,
      'role_ratio' => null,
    ];

    if ($selected_rid != 0 && isset($report['hero_positions'])) {
      if (is_wrapped($report['hero_positions'])) {
        $report['hero_positions'] = unwrap_data($report['hero_positions']);
      }

      [$core, $lane] = explode('.', ROLES_IDS_SIMPLE[$selected_rid]);
      $posdata = $report['hero_positions'][$core][$lane][$selected_hid] ?? [];

      $context['role_matches'] = $posdata['matches_s'] ?? null;
      $context['role_winrate'] = $posdata['winrate_s'] ?? null;
      $context['role_ratio'] = !empty($posdata['matches_s']) && $context['matches'] ? $posdata['matches_s']/$context['matches'] : null;
    }
  }

  $res[$selected_tag][ROLES_IDS[$selected_rid]] = "";

  if (!empty($context)) {
    $res[$selected_tag][ROLES_IDS[$selected_rid]] .= "<div class=\"content-text\">".
      "<table id=\"stitems-consumables-$selected_hid-$selected_rid-context\" class=\"list\">".
      "<thead><tr>".
        "<th></th>".
        "<th>".locale_string("hero")."</th>".
        "<th class=\"separator\">".locale_string("matches")."</th>".
        "<th>".locale_string("pickrate")."</th>".
        "<th>".locale_string("winrate")."</th>".
        "<th>".locale_string("lane_wr")."</th>".
        "<th class=\"separator\">".locale_string("position")."</th>".
        "<th>".locale_string("ratio")."</th>".
        "<th>".locale_string("winrate")."</th>".
      "</tr></thead>".
      "<tbody>".
        "<tr>".
          "<td>".hero_portrait($selected_hid)."</td>".
          "<td>".hero_link($selected_hid)."</td>".
          "<td class=\"separator\">".($context['matches'] ?? 0)."</td>".
          "<td>".number_format(($context['pickrate'] ?? 0)*100, 2)."%</td>".
          "<td>".number_format(($context['winrate'] ?? 0)*100, 2)."%</td>".
          "<td>".(isset($context['lane_wr']) ? number_format(($context['lane_wr'] ?? 0)*100, 2)."%" : '-')."</td>".
          "<td class=\"separator\">".($context['role_matches'] ?? '-')."</td>".
          "<td>".($context['role_matches'] ? number_format(($context['role_ratio'] ?? 0)*100, 2)."%" : '-')."</td>".
          "<td>".($context['role_matches'] ? number_format(($context['role_winrate'] ?? 0)*100, 2)."%" : '-')."</td>".
        "</tr>".
      "</tbody>".
    "</table></div>";
  }

  $res[$selected_tag][ROLES_IDS[$selected_rid]] .= rg_generator_sticonsumables(
    "items-sticonsumables-$selected_tag",
    true,
    $selected_hid,
    $selected_rid,
    $data,
    $report['starting_items']['matches'][$selected_rid][$selected_hid],
    $report['settings']['sti_builds_roles_limit']
  );

  return $res;
}

