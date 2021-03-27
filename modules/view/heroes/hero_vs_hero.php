<?php

include_once($root."/modules/view/generators/pvp_unwrap_data.php");
include_once($root."/modules/view/generators/pvp_profile.php");

$modules['heroes']['hvh'] = [];

function rg_view_generate_heroes_hvh() {
  global $report, $mod, $parent, $strings, $meta, $unset_module;
  if($mod == $parent."hvh") $unset_module = true;
  $parent_module = $parent."hvh-";

  $hvh = rg_generator_pvp_unwrap_data($report['hvh'], $report['pickban']);

  $hnames = $meta["heroes"];

  uasort($hnames, function($a, $b) {
    if($a['name'] == $b['name']) return 0;
    else return ($a['name'] > $b['name']) ? 1 : -1;
  });

  $res['counters'] = "";
  if (check_module($parent_module."counters")) {
    $parent = $parent_module;
    $res['counters'] = rg_view_generate_heroes_counters();
  }

  foreach($hnames as $hid => $name) {
    $strings['en']["heroid".$hid] = hero_name($hid);
    $res["heroid".$hid] = "";

    if(check_module($parent_module."heroid".$hid)) {
      if (isset($report['hero_laning'])) {
        if (is_wrapped($report['hero_laning'])) {
          $report['hero_laning'] = unwrap_data($report['hero_laning']);
        }

        foreach($report['hero_laning'][$hid] as $opid => $hero) {
          if (empty($hvh[$hid][$opid])) continue;
          $hvh[$hid][$opid]['lane_rate'] = ($hero['matches'] ?? 0)/$hvh[$hid][$opid]['matches'];
          $hvh[$hid][$opid]['lane_wr'] = $hero['lane_wr'] ?? 0;
        }
      }


      $res["heroid".$hid] = "<div class=\"content-text\">".locale_string("desc_heroes_hvh")."</div>";
      $res["heroid".$hid] .= "<div class=\"content-text\">".locale_string("desc_heroes_hvh_2")."</div>";
      $res["heroid".$hid] .= rg_generator_pvp_profile("hero-hvh-$hid", $hvh[$hid], $report['pickban'], $hid);
    }
  }

  return $res;
}

?>
