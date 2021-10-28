<?php

$modules['heroes']['hph'] = [];
unset($modules['heroes']['combos']);

function rg_view_generate_heroes_hph() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings;
  if($mod == $parent."hph") $unset_module = true;
  $parent_module = $parent."hph-";
  $res = [];
  include_once($root."/modules/view/generators/hph.php");

  if (is_wrapped($report['hph'])) {
    $report['hph'] = unwrap_data($report['hph']);
  }

  $hnames = $meta["heroes"];

  uasort($hnames, function($a, $b) {
    if($a['name'] == $b['name']) return 0;
    else return ($a['name'] > $b['name']) ? 1 : -1;
  });

  $res['combos'] = "";
  if (check_module($parent_module."combos")) {
    $parent = $parent_module;
    $res['combos'] = rg_view_generate_heroes_combos();
  }

  foreach($hnames as $hid => $name) {
    $strings['en']["heroid".$hid] = hero_name($hid);
    $res["heroid".$hid] = "";

    if(check_module($parent_module."heroid".$hid)) {
      if (!empty ($report['hph'][$hid])) {
        foreach ($report['hph'][$hid] as $id => $line) {
          if ($id == '_h') {
            unset($report['hph'][$hid][$id]);
            continue;
          }
          if ($line === null) unset($report['hph'][$hid][$id]);
          if (is_array($line) && $line['matches'] === -1) $report['hph'][$hid][$id] = $report['hph'][$id][$hid];
        }

        // $res["heroid".$hid] = "<div class=\"content-text\">".locale_string("desc_heroes_hph")."</div>";
        $res["heroid".$hid] .= "<div class=\"content-text\">".locale_string("pairs_desc")."</div>";
        $res["heroid".$hid] .= "<div class=\"content-text\">".locale_string("pairs_desc_2")."</div>";
        $res["heroid".$hid] .= rg_generator_hph_profile("$parent_module-$hid", $report['hph'][$hid], $report['pickban'], $hid);
      } else {
        $res["heroid".$hid] .= "<div class=\"content-text\">".locale_string("stats_empty")."</div>";
      }
    }
  }

  return $res;
}
