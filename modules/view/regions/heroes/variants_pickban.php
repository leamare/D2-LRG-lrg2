<?php
include_once("$root/modules/view/generators/pickban.php");

function rg_view_generate_regions_heroes_variantspb($region, &$reg_report) {
  global $meta, $leaguetag, $linkvars, $locale;

  $res = "";

  if (is_wrapped($reg_report['hvariants'])) $reg_report['hvariants'] = unwrap_data($reg_report['hvariants']);

  include_locale($locale, "facets");

  $pb = [];

  foreach ($reg_report['hvariants'] as $hvid => $stats) {
    [ $hid, $v ] = explode('-', $hvid);

    $pb[$hvid] = [
      'matches_picked' => $stats['m'],
      'winrate_picked' => $stats['m'] ? $stats['w']/$stats['m'] : 0,
      'matches_banned' => isset($reg_report['pickban'][$hid]) ? round( $stats['f']*$reg_report['pickban'][$hid]['matches_banned'] ) : 0,
      'winrate_banned' => $reg_report['pickban'][$hid]['winrate_banned'] ?? 0,
      'ratio' => ($stats['m'] && isset($reg_report['pickban'][$hid])) ? $stats['m']/($reg_report['pickban'][$hid]['matches_picked'] ?: 1) : 0,
    ];
    $pb[$hvid]['matches_total'] = $pb[$hvid]['matches_picked'] + $pb[$hvid]['matches_banned'];
    $pb[$hvid]['variant'] = +$v;
  }

  $res .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\">".
      "<div class=\"line\">".locale_string("desc_heroes_pickban")."</div>".
      "<div class=\"line\">".locale_string("desc_pickban_ranks")."</div>".
      "<div class=\"line\">".locale_string("desc_pickban_variants")."</div>".
      "<div class=\"line\">".locale_string("desc_balance")."</div>".
    "</div>".
  "</details>";

  $res .= rg_generator_pickban("region$region-heroes-variantspb", $pb, $reg_report["main"], true, false, true);

  // $res .= rg_generator_uncontested($meta["heroes"], $reg_report['pickban']);

  return $res;
}

