<?php
function team_name($tid) {
  global $report;
  if($tid && isset($report['teams'][ $tid ]['name']))
    return htmlspecialchars($report['teams'][ $tid ]['name']);
  return "(no team)";
}

function team_tag($tid) {
  global $report;
  if($tid && isset($report['teams'][ $tid ]['tag']))
    return htmlspecialchars($report['teams'][ $tid ]['tag']);
  return "";
}

function team_logo($tid) {
  global $meta, $team_logo_provider;
  return "<img class=\"hero_portrait\" src=\"".str_replace('%TEAM%', $tid, $team_logo_provider)."\" alt=\"".team_name($tid)."\" />";
}