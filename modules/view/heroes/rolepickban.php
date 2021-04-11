<?php
//echo dirname(__FILE__);
include_once($root."/modules/view/generators/pickban.php");

$modules['heroes']['rolepickban'] = "";

function rg_view_generate_heroes_rolepickban() {
  global $report;
  global $meta;

  $res = "";

  if (is_wrapped($report['hero_positions'])) $report['hero_positions'] = unwrap_data($report['hero_positions']);

  $pb = [];

  for ($i=1; $i>=0; $i--) {
    for ($j=0; $j<6 && $j>=0; $j++) {
      if(!empty($report['hero_positions'][$i][$j])) {
        $role = "$i.$j";
        foreach ($report['hero_positions'][$i][$j] as $hid => $data) {
          $pb[$hid.'|'.$role] = [
            'matches_picked' => $data['matches_s'],
            'winrate_picked' => $data['winrate_s'],
            'matches_banned' => round( ($data['matches_s']/$report['pickban'][$hid]['matches_picked'])*$report['pickban'][$hid]['matches_banned'] ),
            'winrate_banned' => $report['pickban'][$hid]['winrate_banned'],
          ];
          $pb[$hid.'|'.$role]['matches_total'] = $pb[$hid.'|'.$role]['matches_picked'] + $pb[$hid.'|'.$role]['matches_banned'];
          $pb[$hid.'|'.$role]['role'] = $role;
        }
      }
    }
  }

  $res .= rg_generator_pickban("heroes-rolepickban", $pb, $report["random"], true, true);

  $res .= rg_generator_uncontested($meta["heroes"], $report['pickban']);

  $res .= "<div class=\"content-text\">".locale_string("desc_heroes_pickban")."</div>";

  return $res;
}

