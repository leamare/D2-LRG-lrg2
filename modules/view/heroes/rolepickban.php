<?php
//echo dirname(__FILE__);
include_once($root."/modules/view/generators/pickban.php");

// $modules['heroes']['rolepickban'] = "";

$selectors_override['variantspb'] = 'pickban';

function rg_view_generate_heroes_rolepickban() {
  global $report, $meta, $leaguetag, $linkvars;

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

  $variants = [];
  // 
  $variants[] = "<span class=\"selector\">".
    "<a href=\"?league=".$leaguetag."&mod=heroes-pickban".(empty($linkvars) ? "" : "&".$linkvars)."\">".
      locale_string("overview").
    "</a>".
  "</span>";

  if (!empty($report['hero_variants'])) {
    $variants[] = "<span class=\"selector\">".
      "<a href=\"?league=".$leaguetag."&mod=heroes-variantspb".(empty($linkvars) ? "" : "&".$linkvars)."\">".
        locale_string("variantspb").
      "</a>".
    "</span>";
  }

  if (!empty($report['hero_positions'])) {
    $variants[] = "<span class=\"selector active\">".
      "<a href=\"?league=".$leaguetag."&mod=heroes-rolepickban".(empty($linkvars) ? "" : "&".$linkvars)."\">".
        locale_string("rolepickban").
      "</a>".
    "</span>";
  }

  if (count($variants) > 1) {
    $res .= "<div class=\"selector-modules-level-5\">".
      implode(" | ", $variants).
    "</div>";
  }

  $res .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\">".
      "<div class=\"line\">".locale_string("desc_heroes_pickban")."</div>".
      "<div class=\"line\">".locale_string("desc_pickban_ranks")."</div>".
      "<div class=\"line\">".locale_string("desc_pickban_roles")."</div>".
      "<div class=\"line\">".locale_string("desc_balance")."</div>".
    "</div>".
  "</details>";

  $res .= rg_generator_pickban("heroes-rolepickban", $pb, $report["random"], true, true);

  // $res .= rg_generator_uncontested($meta["heroes"], $report['pickban']);

  return $res;
}

