<?php

$modules['heroes']['laning'] = [];

function rg_view_generate_heroes_laning() {
  global $report, $parent, $root, $unset_module, $mod, $meta, $strings;
  if($mod == $parent."laning") $unset_module = true;
  $parent_module = $parent."laning-";
  $res = [];
  include_once($root."/modules/view/generators/laning.php");

  if (is_wrapped($report['hero_laning'])) {
    $report['hero_laning'] = unwrap_data($report['hero_laning']);
  }

  $hnames = $meta["heroes"];

  uasort($hnames, function($a, $b) {
    if($a['name'] == $b['name']) return 0;
    else return ($a['name'] > $b['name']) ? 1 : -1;
  });

  $res['total'] = '';

  if(check_module($parent_module."total")) {
    $res["total"] = "<div class=\"content-text\">".locale_string("desc_heroes_hvh")."</div>";
    $res["total"] .= "<div class=\"content-text\">".locale_string("laning_desc")."</div>";
    $res["total"] .= rg_generator_laning_profile("$parent_module-total", $report['hero_laning'], 0);
  }

  foreach($hnames as $hid => $name) {
    $strings['en']["heroid".$hid] = hero_name($hid);
    $res["heroid".$hid] = "";

    if(check_module($parent_module."heroid".$hid)) {
      $res["heroid".$hid] = "<div class=\"content-text\">".locale_string("desc_heroes_hvh")."</div>";
      $res["heroid".$hid] .= "<div class=\"content-text\">".locale_string("laning_desc")."</div>";
      $res["heroid".$hid] .= rg_generator_laning_profile("$parent_module-$hid", $report['hero_laning'], $hid);
    }
  }

  return $res;
}

?>
