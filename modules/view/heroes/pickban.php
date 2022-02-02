<?php
//echo dirname(__FILE__);
include_once($root."/modules/view/generators/pickban.php");

$modules['heroes']['pickban'] = "";

function rg_view_generate_heroes_pickban() {
  global $report, $meta, $leaguetag, $linkvars;

  $res = "";

  $res .= "<div class=\"selector-modules-level-5\">".
    "<span class=\"selector active\">".
      "<a href=\"?league=".$leaguetag."&mod=heroes-pickban".(empty($linkvars) ? "" : "&".$linkvars)."\">".
        locale_string("overview").
      "</a>".
    "</span>".
    " | ".
    "<span class=\"selector\">".
      "<a href=\"?league=".$leaguetag."&mod=heroes-rolepickban".(empty($linkvars) ? "" : "&".$linkvars)."\">".
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

  $res .= rg_generator_pickban("heroes-pickban", $report['pickban'], $report["random"]);

  $res .= rg_generator_uncontested($meta["heroes"], $report['pickban']);

  return $res;
}

?>
