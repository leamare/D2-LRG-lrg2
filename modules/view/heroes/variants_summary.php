<?php
include_once($root."/modules/view/generators/summary.php");

$modules['heroes']['vsummary'] = "";

function rg_view_generate_heroes_variants_summary() {
  global $report, $parent, $root, $unset_module, $mod;
  if($mod == $parent."vsummary") $unset_module = true;
  $parent_module = $parent."vsummary-";

  $res = [];
  
  if (is_wrapped($report['hero_summary_variants'])) $report['hero_summary_variants'] = unwrap_data($report['hero_summary_variants']);

  generate_positions_strings();

  foreach ($report['hero_summary_variants'] as $i => $data) {
    $tag = $i ? 'position_'.ROLES_IDS_SIMPLE[$i] : 'total';

    $res[$tag] = "";
    if (check_module($parent_module.$tag)) {
      $data = array_filter($data, function($e) {
        return !empty($e) && !empty($e['matches_s']);
      });
      uasort($data, function($a, $b) {
        return $b['matches_s'] <=> $a['matches_s'];
      });
      $res[$tag] = rg_generator_summary("heroes-vsummary", $data, true, true, true);
      $res[$tag] .= "<div class=\"content-text\">".locale_string("desc_heroes_summary")."</div>";
    }
  }

  return $res;
}
