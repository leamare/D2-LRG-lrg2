<?php
function player_name($pid, $tt = true) {
  global $report;
  $res = "";
  if (isset($report['players'])) {
    if($pid && isset($report['players'][$pid])) {
      if($tt && isset($report['teams']) && isset($report['players_additional'][$pid]['team'])) {
        $res .= team_tag($report['players_additional'][$pid]['team']).".";
      }
      $res .= htmlspecialchars($report['players'][$pid]);
    } else $res = "null";
  } else if (isset($report['players_unset_nm']) && isset($report['players_unset_nm'][$pid])) {
    $res = $report['players_unset_nm'][$pid];
  } else {
    $res = "PID $pid";
  }
  
  return $res;
}

function player_link($pid, $tt = true) {
  global $link_provider, $links_providers;

  if ($pid < 0) return player_name($pid, $tt);

  if (empty($links_providers))
    return "<a target=\"_blank\" href=\"https://$link_provider/players/$pid\">".player_name($pid, $tt)."</a>";
  
  $r = player_name($pid, $tt)." - ";
  foreach ($links_providers as $lpn => $lpl) {
    $r .= "<a target=\"_blank\" href=\"https://$lpl/players/$pid\">".link_provider($lpn)."</a> ";
  }
  //$r .= " ] ";
  return $r;
}

