<?php 

include_once($root."/modules/view/generators/consumables.php");

$modules['players']['items']['sticonsumables'] = [];

function rg_view_generate_players_sti_consumables() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $leaguetag, $carryon;

  if($mod == $parent."sticonsumables") $unset_module = true;
  $parent_module = $parent."sticonsumables-";

  $res = [];

  $pids = array_keys($report['starting_items_players']['consumables']['all'][0]);
  $pnames = [];
  foreach ($pids as $id) {
    if (!$id) continue;

    $pnames[$id] = player_name($id, false);
  }

  uasort($pnames, function($a, $b) {
    if($a == $b) return 0;
    
    return strcasecmp($a, $b);
  });

  generate_positions_strings();

  $selected_rid = null;
  $selected_pid = null;
  $selected_tag = null;

  $carryon["/^players-items-sticonsumables-(playerid\d+|total)$/"] = "/^players-items-sticonsumables-(playerid\d+|total)/";

  $roles = array_keys($report['starting_items_players']['consumables']['all']);

  $res['total'] = [];
  if(check_module($parent_module.'total')) {
    $selected_pid = 0;
    $selected_tag = "total";
  }
  foreach ($pnames as $pid => $name) {
    register_locale_string($name, "playerid".$pid);

    $res["playerid".$pid] = [];

    if(check_module($parent_module."playerid".$pid)) {
      $selected_tag = "playerid".$pid;
      $selected_pid = $pid;
    }
  }

  $bcnt = 0;

  if (is_array($res[$selected_tag])) {
    if($mod == $parent_module.$selected_tag) $unset_module = true;
    $parent_module = $parent_module.$selected_tag."-";

    foreach ($roles as $rid) {
      if (isset($report['starting_items_players']['consumables']['all'][$rid][$selected_pid])) {
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

  $data = [
    '5m' => null,
    '10m' => null,
    'all' => null,
  ];

  foreach ($data as $blk => $d) {
    if (empty($report['starting_items_players']['consumables'][$blk][$selected_rid][$selected_pid])) {
      $data[$blk] = [];
      continue;
    }
    $report['starting_items_players']['consumables'][$blk][$selected_rid][$selected_pid]['head'] = $report['starting_items_players']['cons_head'];
    $data[$blk] = unwrap_data($report['starting_items_players']['consumables'][$blk][$selected_rid][$selected_pid]);
  }

  $report['starting_items_players']['matches'][$selected_rid] = unwrap_data($report['starting_items_players']['matches'][$selected_rid]);

  $context_info = null;
  if ($selected_pid != 0) {
    $pbdata = $report['players_summary'][$selected_pid] ?? [];

    if (is_wrapped($report['player_laning'])) {
      $report['player_laning'] = unwrap_data($report['player_laning']);
    }
    $lanedata = $report['player_laning'][0][$selected_pid] ?? [];

    $context_info = [
      'matches' => $pbdata['matches_s'] ?? 0,
      'winrate' => $pbdata['winrate_s'] ?? 0,
      'lane_wr' => $lanedata['lane_wr'] ?? null,
      'role_matches' => null,
      'role_winrate' => null,
      'role_ratio' => null,
    ];

    if ($selected_rid != 0 && isset($report['player_positions'])) {
      if (is_wrapped($report['player_positions'])) {
        $report['player_positions'] = unwrap_data($report['player_positions']);
      }

      [$core, $lane] = explode('.', ROLES_IDS_SIMPLE[$selected_rid]);
      $posdata = $report['player_positions'][$core][$lane][$selected_pid] ?? [];

      $context_info['role_matches'] = $posdata['matches_s'];
      $context_info['role_winrate'] = $posdata['winrate_s'];
      $context_info['role_ratio'] = $posdata['matches_s']/$context_info['matches'];
    }
  }

  $res[$selected_tag][ROLES_IDS[$selected_rid]] = "";

  if ($context_info != null) {
    $res[$selected_tag][ROLES_IDS[$selected_rid]] .= "<div class=\"content-text\">".
      "<table id=\"stibuilds-$selected_pid-$selected_rid-context\" class=\"list\">".
      "<thead><tr>".
        "<th>".locale_string("player")."</th>".
        "<th class=\"separator\">".locale_string("matches")."</th>".
        "<th>".locale_string("winrate")."</th>".
        "<th>".locale_string("lane_wr")."</th>".
        "<th class=\"separator\">".locale_string("position")."</th>".
        "<th>".locale_string("ratio")."</th>".
        "<th>".locale_string("winrate")."</th>".
      "</tr></thead>".
      "<tbody>".
        "<tr>".
          "<td>".player_link($selected_pid)."</td>".
          "<td class=\"separator\">".($context_info['matches'] ?? 0)."</td>".
          "<td>".number_format(($context_info['winrate'] ?? 0)*100, 2)."%</td>".
          "<td>".(isset($context_info['lane_wr']) ? number_format(($context_info['lane_wr'] ?? 0)*100, 2)."%" : '-')."</td>".
          "<td class=\"separator\">".($context_info['role_matches'] ?? '-')."</td>".
          "<td>".($context_info['role_matches'] ? number_format(($context_info['role_ratio'] ?? 0)*100, 2)."%" : '-')."</td>".
          "<td>".($context_info['role_matches'] ? number_format(($context_info['role_winrate'] ?? 0)*100, 2)."%" : '-')."</td>".
        "</tr>".
      "</tbody>".
    "</table></div>";
  }

  $res[$selected_tag][ROLES_IDS[$selected_rid]] .= rg_generator_sticonsumables(
    "items-sticonsumables-$selected_tag",
    false,
    $selected_pid,
    $selected_rid,
    $data,
    $report['starting_items_players']['matches'][$selected_rid][$selected_pid],
    $report['settings']['sti_builds_roles_players_limit']
  );

  return $res;
}
