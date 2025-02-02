<?php

$modules['heroes']['positions'] = [];

function rg_view_generate_heroes_positions() {
  global $report, $parent, $root, $meta, $strings, $unset_module, $mod;
  if($mod == $parent."positions") $unset_module = true;
  $parent_module = $parent."positions-";

  $res = [];
  $res["overview"] = "";
  
  if (is_wrapped($report['hero_positions'])) $report['hero_positions'] = unwrap_data($report['hero_positions']);

  for ($i=1; $i>=0; $i--) {
    for ($j=($i ? 0 : 5); $j<6 && $j>=0; ($i ? $j++ : $j--)) {
      //if (!$i) { $j = 0; }
      if(!empty($report['hero_positions'][$i][$j]))
        $res["position_$i.$j"]  = "";

      //if (!$i) { break; }
    }
  }

  if (check_module($parent_module."overview")) {
    include_once($root."/modules/view/generators/positions_overview.php");
    $res["overview"] = rg_generator_positions_overview("heroes-positions-overview", $report['hero_positions']);
    $res["overview"] .= "<div class=\"content-text\">".locale_string("desc_heroes_positions")."</div>";
  }
  {
    include_once($root."/modules/view/generators/summary.php");

    for ($i=1; $i>=0; $i--) {
      for ($j=($i ? 0 : 5); $j<6 && $j>=0; ($i ? $j++ : $j--)) {
        //if (!$i) { $j = 0; }

        if (!check_module($parent_module."position_$i.$j") || empty($report['hero_positions'][$i][$j])) {
          //if (!$i) { break; }
          continue;
        }
        if(isset($report['hero_positions_matches'])) {
          foreach($report['hero_positions_matches'][$i][$j] as $hid => $matches) {
            $report['hero_positions'][$i][$j][$hid]['matchlinks'] = "<a onclick=\"showModal('".
                htmlspecialchars(join_matches_add($matches, true, $hid, true)).
                "', '".locale_string("matches")." - ".addcslashes(hero_name($hid)." - ".locale_string("position_$i.$j"), "'")."');\">".
                locale_string("matches")."</a>";
          }
        }

        $res["position_$i.$j"] = rg_generator_summary(
          "heroes-positions-$i-$j", 
          $report['hero_positions'][$i][$j], 
          true, 
          true,
        );

        $res["position_$i.$j"] .= "<div class=\"content-text\">".locale_string("desc_heroes_positions")."</div>";
        //if (!$i) { break; }
      }
    }
  }

  return $res;
}

