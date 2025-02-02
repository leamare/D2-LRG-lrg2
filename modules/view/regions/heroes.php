<?php

$res["region".$region]["heroes"] = [];

if($mod == $modstr."-heroes") $unset_module = true;
$parent_mod = $modstr."-heroes-";

$res["region".$region]['heroes']["pickban"] = "";
include_once("heroes/pickban.php");

if(check_module($parent_mod."pickban") || check_module($parent_mod."variantspb") || check_module($parent_mod."rolepickban")) {
  $variants = [];
  global $leaguetag;

  $cursection = "pickban";

  if (isset($reg_report['hvariants']) && check_module($parent_mod."variantspb")) {
    include_once("heroes/variants_pickban.php");
    $res["region".$region]['heroes']['pickban'] = rg_view_generate_regions_heroes_variantspb($region, $reg_report);
    $cursection = "variantspb";
  } else if (isset($reg_report['hero_positions']) && check_module($parent_mod."rolepickban")) {
    include_once("heroes/rolepickban.php");
    $res["region".$region]['heroes']['pickban'] = rg_view_generate_regions_heroes_rolepickban($region, $reg_report);
    $cursection = "rolepickban";
  } else {
    $res["region".$region]['heroes']['pickban'] = rg_view_generate_regions_heroes_pickban($region, $reg_report);
  }

  $variants[] = "<span class=\"selector".($cursection == "pickban" ? " active" : "")."\">".
    "<a href=\"?league=".$leaguetag."&mod=regions-region$region-heroes-pickban".(empty($linkvars) ? "" : "&".$linkvars)."\">".
      locale_string("overview").
    "</a>".
  "</span>";

  if (!empty($reg_report['hvariants'])) {
    $variants[] = "<span class=\"selector".($cursection == "variantspb" ? " active" : "")."\">".
      "<a href=\"?league=".$leaguetag."&mod=regions-region$region-heroes-variantspb".(empty($linkvars) ? "" : "&".$linkvars)."\">".
        locale_string("variantspb").
      "</a>".
    "</span>";
  }

  if (!empty($report['hero_positions'])) {
    $variants[] = "<span class=\"selector".($cursection == "rolepickban" ? " active" : "")."\">".
      "<a href=\"?league=".$leaguetag."&mod=regions-region$region-heroes-rolepickban".(empty($linkvars) ? "" : "&".$linkvars)."\">".
        locale_string("rolepickban").
      "</a>".
    "</span>";
  }

  if (count($variants) > 1) {
    $res["region".$region]['heroes']['pickban'] = "<div class=\"selector-modules-level-5\">".
      implode(" | ", $variants).
    "</div>".$res["region".$region]['heroes']['pickban'];
  }
}

if(isset($reg_report['haverages_heroes'])) {
  $res["region".$region]['heroes']["haverages"] = "";
  include_once("heroes/haverages.php");

  if(check_module($parent_mod."haverages")) {
    $res["region".$region]['heroes']['haverages'] = rg_view_generate_regions_heroes_haverages($region, $reg_report);
  }
}

if(isset($reg_report['draft'])) {
  $res["region".$region]['heroes']["draft"] = "";
  include_once("heroes/draft.php");

  if(check_module($parent_mod."draft")) {
    $res["region".$region]['heroes']['draft'] = rg_view_generate_regions_heroes_draft($region, $reg_report);
  }
}

if(isset($reg_report['draft_tree'])) {
  $res["region".$region]['heroes']["draft_tree"] = "";
  include_once("heroes/draft_tree.php");

  if(check_module($parent_mod."draft_tree")) {
    $res["region".$region]['heroes']['draft_tree'] = rg_view_generate_regions_heroes_draft_tree($region, $reg_report);
  }
}

if(isset($reg_report['heroes_meta_graph'])) {
  $res["region".$region]['heroes']["meta_graph"] = "";
  include_once("heroes/meta_graph.php");

  if(check_module($parent_mod."meta_graph")) {
    $res["region".$region]['heroes']['meta_graph'] = rg_view_generate_regions_heroes_meta_graph($region, $reg_report);
  }
}

if(isset($reg_report['hero_pairs']) ||
    (isset($reg_report['hero_trios']) && !empty($reg_report['hero_trios'])) ||
    (isset($reg_report['hero_lane_combos']) && !empty($reg_report['hero_lane_combos'])) ) {
  $res["region".$region]['heroes']["combos"] = [];
  include_once("heroes/combos.php");

  if(check_module($parent_mod."combos")) {
    $res["region".$region]['heroes']['combos'] = rg_view_generate_regions_heroes_combos($region, $reg_report, $parent_mod);
  }
}

if(isset($reg_report['hero_positions'])) {
  $res["region".$region]['heroes']["positions"] = [];
  include_once("heroes/positions.php");

  if(check_module($parent_mod."positions")) {
    $res["region".$region]['heroes']['positions'] = rg_view_generate_regions_heroes_positions($region, $reg_report, $parent_mod);
  }
}

if(isset($reg_report['hero_summary'])) {
  $res["region".$region]['heroes']["summary"] = "";
  include_once("heroes/summary.php");

  if(check_module($parent_mod."summary")) {
    $res["region".$region]['heroes']['summary'] = rg_view_generate_regions_heroes_summary($region, $reg_report);
  }
}

?>
