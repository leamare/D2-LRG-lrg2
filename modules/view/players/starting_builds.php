<?php 

include_once($root."/modules/view/generators/starting_builds.php");

$modules['players']['items']['stibuilds'] = [];

function rg_view_generate_players_sti_builds() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $leaguetag, $carryon;

  if($mod == $parent."stibuilds") $unset_module = true;
  $parent_module = $parent."stibuilds-";

  $res = [];

  $pids = $report['starting_items_players']['builds'][0]['keys'];
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

  $carryon["/^players-items-stitems-(playerid\d+|total)$/"] = "/^players-items-stitems-(playerid\d+|total)/";

  $roles = array_keys($report['starting_items_players']['builds']);

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
      if (isset($report['starting_items_players']['items'][$rid][$selected_pid])) {
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

  if (!isset($selected_rid)) {
    $selected_rid = 0;

    $unset_module = true;
    check_module($parent_module.ROLES_IDS[$selected_rid]);
  }

  $report['starting_items_players']['builds'][$selected_rid] = unwrap_data($report['starting_items_players']['builds'][$selected_rid]);
  $data = $report['starting_items_players']['builds'][$selected_rid][$selected_pid] ?? null;
  $report['starting_items_players']['matches'][$selected_rid] = unwrap_data($report['starting_items_players']['matches'][$selected_rid]);

  if (!empty($data)) {
    $res[$selected_tag][ROLES_IDS[$selected_rid]] = rg_generator_stibuilds(
      "items-stibuilds-$selected_tag",
      false,
      $selected_pid,
      $selected_rid,
      $data,
      $report['starting_items_players']['matches'][$selected_rid][$selected_pid],
      $report['settings']['sti_builds_players_limit'],
      $report['settings']['sti_builds_roles_players_limit']
    );
  }

  return $res;
}
