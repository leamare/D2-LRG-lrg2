<?php

function team_card($tid, $full = false) {
  global $report;
  global $meta;
  global $strings;
  global $leaguetag;
  global $linkvars;

  if(!isset($report['teams'])) return null;

  $output = "<div class=\"team-card".($full ? " full" : "")."\"><div class=\"team-name\">".
            team_logo($tid).
            " <a href=\"?league=".$leaguetag."&mod=teams-profiles-team".$tid.
            (empty($linkvars) ? "" : "&$linkvars")
            ."\" title=\"".team_name($tid)."\">".team_name($tid)." (".team_tag($tid).")</a></div>";

  if(isset($report['teams'][$tid]['regions'])) {
    asort($report['teams'][$tid]['regions']);
    $region_line = region_link( array_keys($report['teams'][$tid]['regions'])[0] );
  }

  $output .= "<div class=\"team-info-block\">".
                "<div class=\"section-caption\">".locale_string("summary").":</div>".
                "<div class=\"team-info-line\"><span class=\"caption\">".locale_string("matches").":</span> ".$report['teams'][$tid]['matches_total']."</div>".
                ($report['teams'][$tid]['matches_total'] ?
                  "<div class=\"team-info-line\"><span class=\"caption\">".locale_string("winrate").":</span> ".
                      number_format($report['teams'][$tid]['wins']*100/$report['teams'][$tid]['matches_total'])."%</div>"
                    : ""
                  ).
                "<div class=\"team-info-line\"><span class=\"caption\">".locale_string("gpm").":</span> ".number_format($report['teams'][$tid]['averages']['gpm'] ?? 0)."</div>".
                "<div class=\"team-info-line\"><span class=\"caption\">".locale_string("xpm").":</span> ".number_format($report['teams'][$tid]['averages']['xpm'] ?? 0)."</div>".
                "<div class=\"team-info-line\"><span class=\"caption\">".locale_string("kda").":</span> ".number_format($report['teams'][$tid]['averages']['kills'] ?? 0).
                  "/".number_format($report['teams'][$tid]['averages']['deaths'] ?? 0)."/".number_format($report['teams'][$tid]['averages']['assists'] ?? 0)."</div>".
                (isset($region_line) ? "<div class=\"team-info-line\"><span class=\"caption\">".locale_string("main_region").":</span> ".$region_line."</div>" : "").
                  "</div>";

  $output .= "<div class=\"team-info-block\">".
                "<div class=\"section-caption\">".locale_string("active_roster").":</div>";
  $player_pos = [];
  foreach($report['teams'][$tid]['active_roster'] as $player) {
    if (!isset($report['players'][$player])) continue;
    $player_pos[$player] = reset($report['players_additional'][$player]['positions']);
  }
  uasort($report['teams'][$tid]['active_roster'], function($a, $b) use ($player_pos) {
    if (!isset($player_pos[$a]['core']) || !isset($player_pos[$b]['core'])) return 0;
    if ($player_pos[$a]['core'] > $player_pos[$b]['core']) return -1;
    if ($player_pos[$a]['core'] < $player_pos[$b]['core']) return 1;
    if ($player_pos[$a]['lane'] < $player_pos[$b]['lane']) return ($player_pos[$a]['core'] ? -1 : 1)*1;
    if ($player_pos[$a]['lane'] > $player_pos[$b]['lane']) return ($player_pos[$a]['core'] ? 1 : -1)*1;
    return 0;
  });
  foreach($report['teams'][$tid]['active_roster'] as $player) {
    if (!isset($report['players'][$player])) continue;
    $position = $player_pos[$player];
    $output .= "<div class=\"team-info-line\">".player_link($player, true, true).
      ((isset($report['teams'][$tid]['hero_positions']) || isset($report['hero_positions'])) && isset($position['core']) ?
        " (".($position['core'] ? locale_string("core") : locale_string("support")).
        ($position['lane'] ? " ".locale_string( "lane_".$position['lane'] ) : '').')' : ''
      )."</div>";
  }
  $output .= "</div>";

  if (isset($report['teams'][$tid]['pickban'])) {
    $heroes = $report['teams'][$tid]['pickban'];
    uasort($heroes, function($a, $b) {
      if($a['matches_picked'] == $b['matches_picked']) return 0;
      else return ($a['matches_picked'] < $b['matches_picked']) ? 1 : -1;
    });

    $output .= "<div class=\"team-info-block\">".
                  "<div class=\"section-caption\">".locale_string("top_pick_heroes").":</div>";
    $counter = 0;
    foreach($heroes as $hid => $stats) {
      if($counter > 3) break;
      $output .= "<div class=\"team-info-line\"><span class=\"caption\">".hero_full($hid).":</span> ";
      $output .= $stats['matches_picked']." - ".
                  number_format(
                    (isset($stats['wins_picked']) ?
                    $stats['wins_picked']/$stats['matches_picked'] :
                    $stats['winrate_picked'])*100, 2)."%</div>";
      $counter++;
    }
    $output .= "</div>";
  }

  if (isset($report['teams'][$tid]['hero_pairs'])) {
    $heroes = $report['teams'][$tid]['hero_pairs'];

    $output .= "<div class=\"team-info-block\">".
                  "<div class=\"section-caption\">".locale_string("top_pick_pairs").":</div>";
    $counter = 0;
    foreach($heroes as $stats) {
      if($counter > 2) break;
      $output .= "<div class=\"team-info-line\"><span class=\"caption\">".hero_full($stats['heroid1'])." + ".hero_full($stats['heroid2']).":</span> ";
      $output .= $stats['matches']." - ".number_format($stats['winrate']*100, 2)."%</div>";
      $counter++;
    }
    if (!$counter) $output .= "<div class=\"team-info-line\"><span class=\"caption\">".locale_string("none")."</span></div>";
    $output .= "</div>";

  }

  return $output."</div>";

}

?>
