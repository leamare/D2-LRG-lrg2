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

  $explainer = "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\">".
      "<div class=\"line\">".locale_string("desc_heroes_hvh")."</div>".
      "<div class=\"line\">".locale_string("desc_laning_1")."</div>".
      "<div class=\"line\">".locale_string("desc_laning_2")."</div>".
      "<div class=\"line\">".locale_string("desc_laning_3")."</div>".
      "<div class=\"line\">".locale_string("desc_laning_4")."</div>".
      "<div class=\"line\">".locale_string("desc_laning_5")."</div>".
      "<div class=\"line\">".locale_string("desc_laning_6")."</div>".
      "<div class=\"line\">".locale_string("desc_laning_7")."</div>".
    "</div>".
  "</details>";

  $res['total'] = '';

  if(check_module($parent_module."total")) {
    $res["total"] = "<div class=\"content-text\">".locale_string("desc_heroes_hvh")."</div>";
    
    $res["total"] = $explainer;

    $res["total"] .= rg_generator_laning_profile("$parent_module-total", $report['hero_laning'], 0);
  }

  foreach($hnames as $hid => $name) {
    $strings['en']["heroid".$hid] = hero_name($hid);
    $res["heroid".$hid] = "";

    if(check_module($parent_module."heroid".$hid)) {
      $res["heroid".$hid] = $explainer;
      $res["heroid".$hid] .= rg_generator_laning_profile("$parent_module-$hid", $report['hero_laning'], $hid);
    }
  }

  return $res;
}


