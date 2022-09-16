<?php
include_once "modules/view/generators/overview.php";

$modules['overview'] = "";

# It will be updated later to support team-centric reports
function rg_view_generate_overview() {
  global $report, $league_logo_banner_provider;

  $res = "<div class=\"content-header\">".locale_string("summary")."</div>";

  $res .= "<div class=\"block-content\">";

  $res .= locale_string("over-pregen-report");
  if ($report['league_id'] == null || $report['league_id'] == "custom") {
    $res .= " ".locale_string("over-custom-league")." ".$report['league_name']." — ".$report['league_desc'].".";
  } else {
    $res .= " ".$report['league_name']." (<a href=\"?lid=".$report['league_id'].(empty($linkvars) ? "" : "&".$linkvars)."\">".$report['league_id']."</a>) — ".$report['league_desc'].".";
  }

  $res .= "</div>";

  if ($report['league_id'] !== null && $report['league_id'] !== "custom") {
    $res .= "<div class=\"block-content tournament-banner\"><img src=\"".str_replace('%LID%', $report['league_id'], $league_logo_banner_provider)."\" alt=\"".$report['league_id']."\" /></div>";
  }

  if (!empty($report['sponsors'])) {
    $res .= "<div class=\"block-content\">".locale_string("sponsors").": ";
    $links = [];
    foreach($report['sponsors'] as $type => $link) {
      $links[] = "<a target=\"_blank\" href=\"".$link."\">".$type."</a>";
    }
    $res .= implode(", ", $links);
    $res .= "</div>";
  }

  if (!empty($report['links'])) {
    $res .= "<div class=\"block-content\">";
    $links = [];
    foreach($report['links'] as $type => $link) {
      $links[] = "<a target=\"_blank\" href=\"".$link."\">".$type."</a>";
    }
    $res .= implode(" - ", $links);
    $res .= "</div>";
  }

  if (!empty($report['orgs'])) {
    $res .= "<div class=\"block-content\"><a target=\"_blank\" href=\"".$report['orgs']."\">".locale_string("website_long")."</a></div>";
  }

  return rg_view_generator_overview("", $report, $res);
}
