<?php

function match_card($mid) {
  global $report, $meta, $match_card_records_cnt;
  if (empty($mid)) return "";

  $output = "<div class=\"match-card\"><div class=\"match-id\">".match_link($mid)."</div>";
  $radiant = "<div class=\"match-team radiant\">";
  $dire = "<div class=\"match-team dire\">";

  $players_radi = ""; $players_dire = "";
  $heroes_radi = "";  $heroes_dire = "";

  $clusters = $meta['clusters'];
  $regions = $meta['regions'];

  $m = $report['matches'][$mid];

  if (isset($report['matches_additional'][$mid]) && !empty($report['matches_additional'][$mid]['order']) && $report['matches_additional'][$mid]['game_mode'] != 1) {
    $orders = array_flip( $report['matches_additional'][$mid]['order'] );
    usort($m, function($a, $b) use (&$orders) {
      return $orders[ $a['hero'] ] <=> $orders[ $b['hero'] ];
    });
  }

  foreach ($m as $pl) {
    $order = empty($orders) ? 0 : $orders[ $pl['hero'] ]+1;
    if($pl['radiant'] == 1) {
      $players_radi .= "<div class=\"match-player\">".player_name($pl['player'], false)."</div>";
      $heroes_radi .= "<div class=\"match-hero\" ".($order ? "data-order=\"$order\"" : "").">".hero_portrait($pl['hero'])."</div>";
    } else {
      $players_dire .= "<div class=\"match-player\">".player_name($pl['player'], false)."</div>";
      $heroes_dire .= "<div class=\"match-hero\" ".($order ? "data-order=\"$order\"" : "").">".hero_portrait($pl['hero'])."</div>";
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
    if (!empty($orders)) {
      foreach ($report['matches_additional'][$mid]['bans'] as $i => &$bns) {
        usort($bns, function($a, $b) use (&$orders) {
          return $orders[ $a[0] ] <=> $orders[ $b[0] ];
        });
      }
    }

    $radiant_bans .= "<div class=\"match-heroes-bans radiant\">";
    foreach ($report['matches_additional'][$mid]['bans'][1] as [$hero, $stage]) {
      $order = empty($orders) ? 0 : $orders[ $hero ]+1;
      $radiant_bans .= "<div class=\"match-hero\" ".($order ? "data-order=\"$order\"" : "").">".hero_portrait($hero)."</div>";
    }
    $radiant_bans .= "</div>";

    $dire_bans .= "<div class=\"match-heroes-bans dire\">";
    foreach ($report['matches_additional'][$mid]['bans'][0] as [$hero, $stage]) {
      $order = empty($orders) ? 0 : $orders[ $hero ]+1;
      $dire_bans .= "<div class=\"match-hero\" ".($order ? "data-order=\"$order\"" : "").">".hero_portrait($hero)."</div>";
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

  if (isset($report['records'])) {
    $match_records = [];
    $reccnt = 0;

    $tags = isset($report['regions_data']) ? array_keys($report['regions_data']) : [];
    array_unshift($tags, null);

    foreach ($tags as $reg) {
      if (!$reg) {
        $context_records = $report['records'];
        $context_records_ext = $report['records_ext'] ?? [];
      } else {
        $context_records = $report['regions_data'][$reg]['records'];
        $context_records_ext = $report['regions_data'][$reg]['records_ext'] ?? [];
      }

      if (is_wrapped($context_records_ext)) {
        $context_records_ext = unwrap_data($context_records_ext);
      }

      foreach ($context_records as $rectag => $record) {
        if (strpos($rectag, "_team") !== false) continue;
  
        if ($record['matchid'] == $mid) {
          $record['tag'] = $rectag;
          $record['placement'] = 1;
          $record['region'] = $reg;
          $match_records[] = $record;
          $reccnt++;
        }
      }
      if (!empty($context_records_ext)) {
        foreach ($context_records as $rectag => $record) {
          if (strpos($rectag, "_team") !== false) continue;

          foreach ($context_records_ext[$rectag] ?? [] as $i => $rec) {
            if ($rec['matchid'] == $mid) {
              if (empty($rec)) continue;
              $rec['tag'] = $rectag;
              $rec['placement'] = $i+2;
              $rec['region'] = $reg;
              $match_records[] = $rec;
              $reccnt++;
            }
          }
        }
      }
    }

    if (!empty($match_records)) {
      usort($match_records, function($a, $b) {
        if ((!$a['region'] || !$b['region']) && ($a['region'] != $b['region'])) {
          return !$a['region'] ? -1 : ( !$b['region'] ? 1 : 0 );
        }

        if ((!$a['playerid'] || !$b['playerid']) && ($a['playerid'] != $b['playerid'])) {
          return !$a['playerid'] ? -1 : ( !$b['playerid'] ? 1 : 0 );
        }

        return $a['placement'] <=> $b['placement'];
      });
      $match_records = array_slice($match_records, 0, $match_card_records_cnt);

      $output .= "<div class=\"match-records-container\">".
        "<div class=\"match-records-header\">".locale_string('records')."</div>".
        "<div class=\"match-records-subheader\">".locale_string('total').": $reccnt</div>".
      "<div class=\"match-records-list\">";
      foreach ($match_records as $record) {
        $output .= "<div class=\"match-record-element\"><label>".
            ( isset($record['item_id']) ? item_full_link($record['item_id']) : locale_string($record['tag']) ).
            ($record['placement'] == 1 ? '' : ' #'.$record['placement']).
            ($record['region'] ? " (".locale_string("region".$record['region']).")" : '').
          "</label>: ".(
            strpos($record['tag'], "duration") !== FALSE || strpos($record['tag'], "_len") !== FALSE ||
            strpos($record['tag'], "_time") !== FALSE ||
            strpos($record['tag'], "shortest") !== FALSE || strpos($record['tag'], "longest") !== FALSE ?
            convert_time($record['value']) :
            ( $record['value'] - floor($record['value']) != 0 ? number_format($record['value'], 2) : number_format($record['value'], 0) )
          ).($record['heroid'] ? " @ ".hero_icon($record['heroid']) : '').(!empty($record['playerid']) ? " ".player_link($record['playerid'])."" : "").
        "</div>";
      }
      if ($reccnt > $match_card_records_cnt) {
        $output .= "<div class=\"match-record-element\">...</div>";
      }
      $output .= "</div></div>";
    }
  }

  return $output."</div>";
}
