<?php
include_once("$root/modules/view/generators/pickban.php");

function rg_view_generate_regions_heroes_rolepickban($region, $reg_report) {
  global $meta, $modules, $leaguetag, $linkvars;

  if (is_wrapped($reg_report['hero_positions'])) $reg_report['hero_positions'] = unwrap_data($reg_report['hero_positions']);

  $pb = [];

  for ($i=1; $i>=0; $i--) {
    for ($j=0; $j<6 && $j>=0; $j++) {
      if(!empty($reg_report['hero_positions'][$i][$j])) {
        $role = "$i.$j";
        foreach ($reg_report['hero_positions'][$i][$j] as $hid => $data) {
          $pb[$hid.'|'.$role] = [
            'matches_picked' => $data['matches_s'],
            'winrate_picked' => $data['winrate_s'],
            'matches_banned' => round( ($data['matches_s']/$reg_report['pickban'][$hid]['matches_picked'])*$reg_report['pickban'][$hid]['matches_banned'] ),
            'winrate_banned' => $reg_report['pickban'][$hid]['winrate_banned'],
          ];
          $pb[$hid.'|'.$role]['matches_total'] = $pb[$hid.'|'.$role]['matches_picked'] + $pb[$hid.'|'.$role]['matches_banned'];
          $pb[$hid.'|'.$role]['role'] = $role;
        }
      }
    }
  }

  $res = "<div class=\"selector-modules-level-5\">".
    "<span class=\"selector\">".
      "<a href=\"?league=".$leaguetag."&mod=regions-region$region-heroes-pickban".(empty($linkvars) ? "" : "&".$linkvars)."\">".
        locale_string("overview").
      "</a>".
    "</span>".
    " | ".
    "<span class=\"selector active\">".
      "<a href=\"?league=".$leaguetag."&mod=regions-region$region-heroes-rolepickban".(empty($linkvars) ? "" : "&".$linkvars)."\">".
        locale_string("rolepickban").
      "</a>".
    "</span>".
  "</div>";

  $res .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\">".
      "<div class=\"line\">".locale_string("desc_heroes_pickban")."</div>".
      "<div class=\"line\">".locale_string("desc_pickban_ranks")."</div>".
      "<div class=\"line\">".locale_string("desc_pickban_roles")."</div>".
      "<div class=\"line\">".locale_string("desc_balance")."</div>".
    "</div>".
  "</details>";

  $res .= rg_generator_pickban("region$region-heroes-pickban", $pb, $reg_report['main'], true, true);
  $res .= rg_generator_uncontested($meta["heroes"], $reg_report['pickban']);

  return $res;
}

