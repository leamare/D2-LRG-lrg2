<?php 

include_once($root."/modules/view/generators/starting_builds.php");

$modules['items']['stibuilds'] = [];

function rg_view_generate_items_sti_builds() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings, $leaguetag, $carryon;

  if($mod == $parent."stibuilds") $unset_module = true;
  $parent_module = $parent."stibuilds-";

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

  $carryon["/^items-stibuilds-(heroid\d+|total)$/"] = "/^items-stibuilds-(heroid\d+|total)/";

  $roles = array_keys($report['starting_items']['items']);

  $res['total'] = [];
  if(check_module($parent_module.'total')) {
    $selected_hid = 0;
    $selected_tag = "total";
  }
  foreach ($hnames as $hid => $name) {
    register_locale_string(hero_name($hid), "heroid".$hid);

    $res["heroid".$hid] = in_array($hid, $report['starting_items']['builds'][0]['keys']) ? [] : "";

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
      if (in_array($selected_hid, $report['starting_items']['builds'][$rid]['keys'])) {
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

  $report['starting_items']['builds'][$selected_rid] = unwrap_data($report['starting_items']['builds'][$selected_rid]);
  $data = $report['starting_items']['builds'][$selected_rid][$selected_hid] ?? null;
  $report['starting_items']['matches'][$selected_rid] = unwrap_data($report['starting_items']['matches'][$selected_rid]);

  if (!empty($data)) {
    $res[$selected_tag][ROLES_IDS[$selected_rid]] = rg_generator_stibuilds(
      "items-stibuilds-$selected_tag",
      true,
      $selected_hid,
      $selected_rid,
      $data,
      $report['starting_items']['matches'][$selected_rid][$selected_hid],
      $report['settings']['sti_builds_limit'],
      $report['settings']['sti_builds_roles_limit']
    );
  }

  return $res;
}
