<?php

function match_card($mid) {
  global $report;
  global $meta;
  global $strings;
  global $linkvars;
  global $leaguetag;
  if (empty($mid)) return "";

  $output = "<div class=\"match-card\"><div class=\"match-id\">".match_link($mid)."</div>";
  $radiant = "<div class=\"match-team radiant\">";
  $dire = "<div class=\"match-team dire\">";

  $players_radi = ""; $players_dire = "";
  $heroes_radi = "";  $heroes_dire = "";

  $clusters = $meta['clusters'];
  $regions = $meta['regions'];

  $m = $report['matches'][$mid];

  foreach ($m as $pl) {
    if($pl['radiant'] == 1) {
      $players_radi .= "<div class=\"match-player\">".player_name($pl['player'], false)."</div>";
      $heroes_radi .= "<div class=\"match-hero\">".hero_portrait($pl['hero'])."</div>";
    } else {
      $players_dire .= "<div class=\"match-player\">".player_name($pl['player'], false)."</div>";
      $heroes_dire .= "<div class=\"match-hero\">".hero_portrait($pl['hero'])."</div>";
    }
  }
  if(isset($report['teams']) && isset($report['match_participants_teams'][$mid])) {
    if(isset($report['match_participants_teams'][$mid]['radiant']) &&
       isset($report['teams'][ $report['match_participants_teams'][$mid]['radiant'] ]['name']))
      $team_radiant = team_link($report['match_participants_teams'][$mid]['radiant']);
    else $team_radiant = locale_string("radiant");
    if(isset($report['match_participants_teams'][$mid]['dire']) &&
       isset($report['teams'][ $report['match_participants_teams'][$mid]['dire'] ]['name']))
      $team_dire = team_link($report['match_participants_teams'][$mid]['dire']);
    else $team_dire = locale_string("dire");
  } else {
    $team_radiant = locale_string("radiant");
    $team_dire = locale_string("dire");
  }

  $radiant_bans = ""; $dire_bans = "";
  if (isset($report['matches_additional'][$mid]['bans'])) {
    $radiant_bans .= "<div class=\"match-heroes-bans radiant\">";
    foreach ($report['matches_additional'][$mid]['bans'][0] as [$hero, $stage]) {
      $radiant_bans .= "<div class=\"match-hero\">".hero_portrait($hero)."</div>";
    }
    $radiant_bans .= "</div>";

    $dire_bans .= "<div class=\"match-heroes-bans dire\">";
    foreach ($report['matches_additional'][$mid]['bans'][1] as [$hero, $stage]) {
      $dire_bans .= "<div class=\"match-hero\">".hero_portrait($hero)."</div>";
    }
    $dire_bans .= "</div>";
  }

  $radiant .= "<div class=\"match-team-score\">".$report['matches_additional'][$mid]['radiant_score']."</div>".
              "<div class=\"match-team-name".($report['matches_additional'][$mid]['radiant_win'] ? " winner" : "")."\">".$team_radiant."</div>";
  $dire .= "<div class=\"match-team-score\">".$report['matches_additional'][$mid]['dire_score']."</div>".
           "<div class=\"match-team-name".($report['matches_additional'][$mid]['radiant_win'] ? "" : " winner")."\">".$team_dire."</div>";

  $radiant .= "<div class=\"match-players\">".$players_radi."</div><div class=\"match-heroes\">".$heroes_radi."</div>".$radiant_bans.
              "<div class=\"match-team-nw\">".$report['matches_additional'][$mid]['radiant_nw']."</div></div>";
  $dire .= "<div class=\"match-players\">".$players_dire."</div><div class=\"match-heroes\">".$heroes_dire."</div>".$dire_bans.
          "<div class=\"match-team-nw\">".$report['matches_additional'][$mid]['dire_nw']."</div></div>";


  $output .= $radiant.$dire;

  $duration = (int)($report['matches_additional'][$mid]['duration']/3600);

  $duration = $duration ? $duration.":".(
        (int)($report['matches_additional'][$mid]['duration']%3600/60) < 10 ?
        "0".(int)($report['matches_additional'][$mid]['duration']%3600/60) :
        (int)($report['matches_additional'][$mid]['duration']%3600/60)
      ) : ((int)($report['matches_additional'][$mid]['duration']%3600/60));

  $duration = $duration.":".(
    (int)($report['matches_additional'][$mid]['duration']%60) < 10 ?
    "0".(int)($report['matches_additional'][$mid]['duration']%60) :
    (int)($report['matches_additional'][$mid]['duration']%60)
  );

  $output .= "<div class=\"match-add-info\">
                <div class=\"match-info-line\"><span class=\"caption\">".locale_string("duration").":</span> ".
                  $duration."</div>
                <div class=\"match-info-line\"><span class=\"caption\">".locale_string("region").":</span> ".
                  ($meta['regions'][
                    $meta['clusters'][ $report['matches_additional'][$mid]['cluster'] ] ?? 0
                  ] ?? "unknown")."</div>
                <div class=\"match-info-line\"><span class=\"caption\">".locale_string("game_mode").":</span> ".
                  $meta['modes'][$report['matches_additional'][$mid]['game_mode']]."</div>
                  <div class=\"match-info-line\"><span class=\"caption\">".locale_string("winner").":</span> ".
                    ($report['matches_additional'][$mid]['radiant_win'] ? $team_radiant : $team_dire)."</div>
                  <div class=\"match-info-line\"><span class=\"caption\">".locale_string("date").":</span> ".
                    date(locale_string("time_format")." ".locale_string("date_format"), $report['matches_additional'][$mid]['date'] + $report['matches_additional'][$mid]['duration'])."</div>
              </div>";

  return $output."</div>";
}

?>
