<?php

function match_link($mid) {
  return "<a href=\"https://opendota.com/matches/$mid\" target=\"_blank\" rel=\"noopener\">$mid</a>";
}

function team_link($tid) {
  global $leaguetag;
  global $linkvars;

  return "<a href=\"?league=".$leaguetag."&mod=teams-profiles-team".$tid.(empty($linkvars) ? "" : "&$linkvars")
    ."\" title=\"".team_name($tid)."\">".team_name($tid)." (".team_tag($tid).")</a>";
}

function region_link($rid) {
  global $leaguetag;
  global $linkvars;

  return "<a href=\"?league=".$leaguetag."&mod=regions-regions$rid".(empty($linkvars) ? "" : "&$linkvars")
    ."\" title=\"".locale_string("region$rid")."\">".locale_string("region$rid")."</a>";
}

function team_link_short($tid) {
  global $leaguetag;
  global $linkvars;

  return "<a href=\"?league=".$leaguetag."&mod=teams-profiles-team".$tid.(empty($linkvars) ? "" : "&$linkvars")
    ."\" title=\"".team_name($tid)."\">".team_tag($tid)."</a>";
}


?>