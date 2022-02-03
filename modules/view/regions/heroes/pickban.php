<?php
include_once("$root/modules/view/generators/pickban.php");

function rg_view_generate_regions_heroes_pickban($region, $reg_report) {
  global $meta, $modules, $leaguetag, $linkvars;

  $res = "<div class=\"selector-modules-level-5\">".
    "<span class=\"selector active\">".
      "<a href=\"?league=".$leaguetag."&mod=regions-region$region-heroes-pickban".(empty($linkvars) ? "" : "&".$linkvars)."\">".
        locale_string("overview").
      "</a>".
    "</span>".
    " | ".
    "<span class=\"selector\">".
      "<a href=\"?league=".$leaguetag."&mod=regions-region$region-heroes-rolepickban".(empty($linkvars) ? "" : "&".$linkvars)."\">".
        locale_string("rolepickban").
      "</a>".
    "</span>".
  "</div>";

  $res .= "<details class=\"content-text explainer\"><summary>".locale_string("explain_summary")."</summary>".
    "<div class=\"explain-content\">".
      "<div class=\"line\">".locale_string("desc_heroes_pickban")."</div>".
      "<div class=\"line\">".locale_string("desc_pickban_ranks")."</div>".
      "<div class=\"line\">".locale_string("desc_balance")."</div>".
    "</div>".
  "</details>";

  $res .= rg_generator_pickban("region$region-heroes-pickban", $reg_report['pickban'], $reg_report['main']);
  $res .= rg_generator_uncontested($meta["heroes"], $reg_report['pickban']);

  return $res;
}


?>
