<?php

function match_link($mid, $team = null, $winner = null) {
  global $link_provider, $links_providers, $report;
  $midText = $mid;

  $team_result = "match-string";
  if ($winner !== null) {
    $team_result = "team-focus ".($winner ? "team-winner" : "team-loser");
  }

  if (!empty($report['match_parts_strings'])) {
    $midText .= ' - '.$report['match_parts_strings'][$mid];

    if ($team) {
      if ($team > 0) {
        $tn = team_tag($team);
      } else {
        $tn = $team == -1 ? locale_string("radiant") : locale_string("dire");
      }
  
      if ($team > 0) {
        $midText = str_replace(' '.$tn.' ', " <span class=\"$team_result\">".$tn."</span> ", $midText);
      }
    }
  } else {
    $midText = "<span class=\"$team_result\">".$midText."</span>";
  }

  if (empty($links_providers))
    return "<a href=\"https://$link_provider/matches/$mid\" target=\"_blank\" rel=\"noopener\" class=\"matchlink\">$midText</a>";
  
  $r = $midText." - ";
  foreach ($links_providers as $lpn => $lpl) {
    $r .= "<a class=\"matchlink\" target=\"_blank\" href=\"https://$lpl/matches/$mid\">".link_provider($lpn)."</a> ";
  }
  //$r .= " ] ";
  return $r;
}

function link_provider($lpn) {
  global $link_provider_icon;

  if (empty($link_provider_icon)) return $lpn;
  return "<img class=\"rf_icon\" src=\"".str_replace("%LPN%", strtolower($lpn), $link_provider_icon)."\" />";
}

function team_link($tid) {
  global $leaguetag, $linkvars;

  return "<a href=\"?league=".$leaguetag."&mod=teams-profiles-team".$tid.(empty($linkvars) ? "" : "&$linkvars")
    ."\" title=\"".team_name($tid)."\">".team_name($tid)." (".team_tag($tid).")</a>";
}

function region_link($rid) {
  global $leaguetag, $linkvars;

  return "<a href=\"?league=".$leaguetag."&mod=regions-region$rid".(empty($linkvars) ? "" : "&$linkvars")
    ."\" title=\"".locale_string("region$rid")."\">".locale_string("region$rid")."</a>";
}

function team_link_short($tid) {
  global $leaguetag, $linkvars;

  return "<a href=\"?league=".$leaguetag."&mod=teams-profiles-team".$tid.(empty($linkvars) ? "" : "&$linkvars")
    ."\" title=\"".team_name($tid)."\">".team_tag($tid)."</a>";
}


?>
