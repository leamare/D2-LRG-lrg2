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
  $selected_hid = null;
  $selected_tag = null;

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

  $res[$selected_tag][ROLES_IDS[$selected_rid]] = rg_generator_sticonsumables(
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

