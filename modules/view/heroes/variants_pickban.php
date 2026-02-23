<?php
//echo dirname(__FILE__);
include_once($root."/modules/view/generators/pickban.php");

// $modules['heroes']['rolepickban'] = "";

function rg_view_generate_heroes_variants_pickban() {
  global $report, $meta, $leaguetag, $linkvars, $locale;

  $res = "";

  if (is_wrapped($report['hero_variants'])) $report['hero_variants'] = unwrap_data($report['hero_variants']);

  include_locale($locale, "facets");

  $pb = [];

  foreach ($report['hero_variants'] as $hvid => $stats) {
    [ $hid, $v ] = explode('-', $hvid);

    if (!isset($report['pickban'][$hid]) || !$stats['m']) continue;
    $pb[$hvid] = [
      'matches_picked' => $stats['m'],
      'winrate_picked' => $stats['w']/$stats['m'],
      'matches_banned' => round( $stats['f']*($report['pickban'][$hid]['matches_banned'] ?? 0) ),
      'winrate_banned' => $report['pickban'][$hid]['winrate_banned'] ?? 0,
      'ratio' => $stats['m'] ? $stats['m']/max(1, $report['pickban'][$hid]['matches_picked'] ?? 0) : 0,
    ];
    $pb[$hvid]['matches_total'] = $pb[$hvid]['matches_picked'] + $pb[$hvid]['matches_banned'];
    $pb[$hvid]['variant'] = +$v;
  }

  $variants = [];
  // 
  $variants[] = "<span class=\"selector\">".
    "<a href=\"?league=".$leaguetag."&mod=heroes-pickban".(empty($linkvars) ? "" : "&".$linkvars)."\">".
      locale_string("overview").
    "</a>".
  "</span>";

  if (!empty($report['hero_variants'])) {
    $variants[] = "<span class=\"selector active\">".
      "<a href=\"?league=".$leaguetag."&mod=heroes-variantspb".(empty($linkvars) ? "" : "&".$linkvars)."\">".
        locale_string("variantspb").
      "</a>".
    "</span>";
  }

  if (!empty($report['hero_positions'])) {
    $variants[] = "<span class=\"selector\">".
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
      "<div class=\"line\">".locale_string("desc_pickban_variants")."</div>".
      "<div class=\"line\">".locale_string("desc_balance")."</div>".
    "</div>".
  "</details>";

  $res .= rg_generator_pickban("heroes-variantspb", $pb, $report["random"], true, false, true);

  // $res .= rg_generator_uncontested($meta["heroes"], $report['pickban']);

  return $res;
}

