<?php 

function series_matches_link($sid, $type="list") {
  global $leaguetag, $linkvars;

  if ($sid === null) {
    return " - ";
  }

  return "<a href=\"?league=$leaguetag&mod=matches-$type&gets=$sid".(empty($linkvars) ? "" : "&".$linkvars)."\">".locale_string("meet_num")." $sid</a>";
}
