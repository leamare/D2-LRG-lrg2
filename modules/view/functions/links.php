<?php

function match_link($mid) {
  global $link_provider, $links_providers;

  if (empty($links_providers))
  return "<a href=\"https://$link_provider/matches/$mid\" target=\"_blank\" rel=\"noopener\">$mid</a>";
  
  $r = $mid." - ";
  foreach ($links_providers as $lpn => $lpl) {
    $r .= "<a target=\"_blank\" href=\"https://$lpl/matches/$mid\">".link_provider($lpn)."</a> ";
  }
  //$r .= " ] ";
  return $r;
}

function link_provider($lpn) {
  global $link_provider_icon;
  if (empty($link_provider_icon)) return $lpn;
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
