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

  $res[$selected_tag][ROLES_IDS[$selected_rid]] = rg_generator_sticonsumables(
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
