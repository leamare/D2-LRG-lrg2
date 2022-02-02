<?php

$modules['heroes']['combos'] = [];

function rg_view_generate_regions_heroes_combos($region, $reg_report, $modstr) {
  global $root, $unset_module, $mod;
  if($mod == $modstr."combos") $unset_module = true;
  $parent_module = $modstr."combos-";
  $res = [];
  include_once($root."/modules/view/generators/combos.php");

  if(isset($reg_report['hero_pairs'])) {
    $res['pairs'] = "";
    if (check_module($parent_module."pairs")) {
      $res['pairs'] = "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
        "<div class=\"explain-content\">".
          "<div class=\"line\">".locale_string("desc_heroes_pairs", [ 
            "limh"=>($reg_report['settings']['limiter_graph'] )+1 
          ] )."</div>".
        "</div>".
      "</details>";
      $res['pairs'] .=  rg_generator_combos("region$region-hero-pairs",
                                         $reg_report['hero_pairs'],
                                         []
                                       );
    }
  }
  if(isset($reg_report['hero_trios']) && !empty($reg_report['hero_trios'])) {
    $res['trios'] = "";
    if (check_module($parent_module."trios")) {
      $res['trios'] = "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
        "<div class=\"explain-content\">".
          "<div class=\"line\">".locale_string("desc_heroes_trios", [ "liml"=>ceil($reg_report['settings']['limiter_graph']*0.25)+1 ] )."</div>".
        "</div>".
      "</details>";
      $res['trios'] .= rg_generator_combos("region$region-hero-trios",
                                         $reg_report['hero_trios'],
                                         []
                                       );
    }
  }
  if(isset($reg_report['hero_lane_combos'])) {
    $res['lane_combos'] = "";
    if (check_module($parent_module."lane_combos")) {
      $res['lane_combos'] = "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
        "<div class=\"explain-content\">".
          "<div class=\"line\">".locale_string("desc_heroes_lane_combos", [ "liml"=>round($reg_report['settings']['limiter_graph']*0.5)+1 ] )."</div>".
        "</div>".
      "</details>";
      $res['lane_combos'] .=  rg_generator_combos("region$region-hero-lanecombos", $reg_report['hero_lane_combos'], []);
    }
  }

  return $res;
}

?>
