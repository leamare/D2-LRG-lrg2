<?php
function player_card($player_id) {
  global $report;
  global $meta;
  global $strings;
  $pname = player_name($player_id);
  $pinfo = $report['players_additional'][$player_id];

  if(isset($report['regions_data'])) {
    $region_line = "";
    foreach($report['regions_data'] as $rid => $region) {
      if(isset($region['players_summary'][$player_id])) {
        if(!empty($region_line)) $region_line .= ", ";
        $region_line .= region_link($rid)." (".$region['players_summary'][$player_id]['matches_s'].")";
      }
    }
  }

  $output = "<div class=\"player-card\"><div class=\"player-name\">".player_link($player_id)." (".$player_id.")</div>";
  if(isset($report['teams']) && isset($pinfo['team']) && isset($report['teams'][ $pinfo['team'] ]))
    $output .= "<div class=\"player-team\">".team_link($pinfo['team'])."</div>";
  $output .= "<div class=\"player-add-info\">".
                "<div class=\"player-info-line\"><span class=\"caption\">".locale_string("matches").":</span> ".($pinfo['matches'] ?? 0)." (".
                  ($pinfo['won'] ?? 0)." - ".(($pinfo['matches'] ?? 0) - ($pinfo['won'] ?? 0)).")</div>".
                "<div class=\"player-info-line\"><span class=\"caption\">".locale_string("winrate").":</span> ".number_format(($pinfo['won'] ?? 0)*100/($pinfo['matches'] ?? 1), 2)."%</div>".
                "<div class=\"player-info-line\"><span class=\"caption\">".locale_string("gpm").":</span> ".number_format($pinfo['gpm'] ?? 0,1)."</div>".
                "<div class=\"player-info-line\"><span class=\"caption\">".locale_string("xpm").":</span> ".number_format($pinfo['xpm'] ?? 0,1)."</div>".
                "<div class=\"player-info-line\"><span class=\"caption\">".locale_string("hero_pool").":</span> ".($pinfo['hero_pool_size'] ?? 0)."</div>".
                (isset($region_line) ? "<div class=\"player-info-line\"><span class=\"caption\">".locale_string("regions").":</span> ".$region_line."</div>" : "").
                "</div>";

  # heroes
  $output .= "<div class=\"player-heroes\"><div class=\"section-caption\">".locale_string("heroes")."</div><div class=\"section-lines\">";
  foreach($pinfo['heroes'] as $hero) {
    $output .= "<div class=\"player-info-line\"><span class=\"caption\">".hero_full($hero['heroid']).":</span> ";
    $output .= $hero['matches']." - ".number_format($hero['wins']*100/$hero['matches'], 2)."%</div>";
  }
  $output .= "</div></div>";

  # positions
  $output .= "<div class=\"player-positions\"><div class=\"section-caption\">".locale_string("positions")."</div><div class=\"section-lines\">";
  foreach($pinfo['positions'] as $position) {
    $output .= "<div class=\"player-info-line\"><span class=\"caption\">".locale_string("position_".$position["core"].".".$position["lane"]).":</span> ";
    $output .= $position['matches']." - ".number_format($position['wins']*100/$position['matches'], 2)."%</div>";
  }
  $output .= "</div></div>";


  return $output."</div>";
}
?>
