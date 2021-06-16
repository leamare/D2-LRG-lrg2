<?php
//echo dirname(__FILE__);
include_once($root."/modules/view/generators/draft_tree.php");

$modules['heroes']['draft_tree'] = "";

function rg_view_generate_heroes_draft_tree() {
  global $report;

  $res = rg_generator_draft_tree('draft_tree', $report['draft_tree'], $report['draft'], $report['settings']['limiter_combograph']*6);

  return $res;
}
