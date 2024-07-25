<?php

$modules['heroes']['combos'] = [];

function rg_generate_hero_pairs() {
  global $report;

  $r = [];

  foreach ($report['hph'] as $hid1 => $heroes) {
    foreach ($heroes as $hid2 => $line) {
      if (empty($line) || $line === true)
        continue;
      if ($line['matches'] <= $report['settings']['limiter_combograph'])
        continue;
      
      $line['heroid1'] = $hid1;
      $line['heroid2'] = $hid2;
      $line['expectation'] = $line['exp'];

      $r[] = $line;
    }
  }

  return $r;
}

function rg_view_generate_heroes_combos() {
  global $report, $parent, $root, $unset_module, $mod;
  if($mod == $parent."combos") $unset_module = true;
  $parent_module = $parent."combos-";
  $res = [];
  include_once($root."/modules/view/generators/combos.php");

  if (!empty($report['hph']) && is_wrapped($report['hph'])) {
    $report['hph'] = unwrap_data($report['hph']);
  }

  if(isset($report['hero_pairs']) || isset($report['hph'])) {
    $res['pairs'] = "";
    if (check_module($parent_module."pairs")) {
      if (empty($report['hero_pairs'])) {
        $report['hero_pairs'] = rg_generate_hero_pairs();
      }

      $res['pairs'] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
        "<div class=\"explain-content\">".
          "<div class=\"line\">".locale_string("desc_heroes_pairs", [ "limh"=>$report['settings']['limiter_combograph']+1 ] )."</div>".
        "</div>".
      "</details>";

      $res['pairs'] .=  rg_generator_combos("hero-pairs",
                                         $report['hero_pairs'],
                                         ($report['hero_pairs_matches'] ?? null)
                                       );
    }
  }
  if(isset($report['hero_triplets']) && !empty($report['hero_triplets'])) {
    $res['trios'] = "";
    if (check_module($parent_module."trios")) {
      $res['trios'] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
        "<div class=\"explain-content\">".
          "<div class=\"line\">".locale_string("desc_heroes_trios", [ "liml"=>ceil($report['settings']['limiter_combograph']*0.25)+1 ] )."</div>".
        "</div>".
      "</details>";
      
      $res['trios'] .= rg_generator_combos("hero-trios",
                                         $report['hero_triplets'],
                                         (isset($report['hero_triplets_matches']) ? $report['hero_triplets_matches'] : [])
                                       );
    }
  }

  if(isset($report['hero_lane_combos'])) {
    $res['lane_combos'] = "";
    if (check_module($parent_module."lane_combos")) {
      $res['lane_combos'] .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
        "<div class=\"explain-content\">".
          "<div class=\"line\">".locale_string("desc_heroes_lane_combos", [ "liml"=>round($report['settings']['limiter_combograph']*0.5)+1 ] )."</div>".
        "</div>".
      "</details>";

      $res['lane_combos'] .=  rg_generator_combos("hero-lanecombos", $report['hero_lane_combos'], []);
    }
  }

  return $res;
}
