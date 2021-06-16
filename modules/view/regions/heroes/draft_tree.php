<?php
include_once("$root/modules/view/generators/draft_tree.php");

function rg_view_generate_regions_heroes_draft_tree($region, $reg_report) {
  global $meta;
  global $modules;

  $res = rg_generator_draft_tree('region$region-heroes-draft_tree', $reg_report['draft_tree'], $reg_report['draft'], $reg_report['settings']['limiter_graph']*6);

  return $res;
}

