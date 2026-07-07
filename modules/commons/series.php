<?php 

require_once __DIR__ . '/roster_pair.php';

function generate_series_data(&$report) {
  $partCnts = [];
  $meetCnts = [];
  $totalCnt = [];
  $seriesIds = [];
  $seriesTt = [];
  $series = [];

  $sip = $report['settings']['series_id_priority'] ?? false;

  $_rep_add = [
    'match_parts_strings' => [],
    'match_parts_series_tag' => [],
    'match_parts_series_num' => [],
    'match_parts_game_num' => [],
    'match_parts_total_game_num' => [],
  ];

  $mids = array_keys($report['matches']);
  sort($mids);

  $pairCanon = ['aliases' => [], 'refs' => []];

  foreach ($mids as $i => $mid) {
    $teams = [ $report['match_participants_teams'][$mid]['radiant'] ?? 0, $report['match_participants_teams'][$mid]['dire'] ?? 0 ];
    $time = $report['matches_additional'][$mid]['date'];
    $duration = $report['matches_additional'][$mid]['duration'];
    $seriesid = $report['matches_additional'][$mid]['seriesid'] ?? null;
    $series_tag = ((int)$seriesid).'_'.($i+1);

    if ($teams[0] && $teams[1]) {
      $teamsStr = team_tag( min($teams) ).' '.locale_string('versus').' '.team_tag( max($teams) );
    } else {
      $teamsStr = ( $teams[0] ? team_tag($teams[0]) : locale_string('radiant') ).' '.locale_string('versus').' '.( $teams[1] ? team_tag($teams[1]) : locale_string('dire') );
    }

    sort($teams);
    $teamstag = series_resolve_meeting_key($report, $mid, series_report_team_pair_key($report, $mid), $pairCanon);

    if (!empty($seriesid)) {
      if (isset($seriesTt[$seriesid])) {
        $teamstag = $seriesTt[$seriesid];
      }
    }

    if ($teamstag == "0.0") {
      $teamsStr = "";
    }

    if (!isset($meetCnts[$teamstag])) {
      $meetCnts[$teamstag] = [
        0,    // meeting index for this team pair
        null, // previous match start time
        0,    // max inter-game gap seen
        0,    // previous match duration
      ];
    }
    // Gap between end of previous game and start of this one.
    $prevDuration = $meetCnts[$teamstag][3];
    $timeDiff = $meetCnts[$teamstag][1] ? $time - $meetCnts[$teamstag][1] - $prevDuration : 0;
    if ($meetCnts[$teamstag][1]) {
      $timeCondition = ($partCnts[$teamstag] < 2 && $timeDiff > 14400) || ($partCnts[$teamstag] >= 2 && $timeDiff > $meetCnts[$teamstag][2] * 3);
      $pairSeriesId = $seriesIds[$teamstag.'.'.$meetCnts[$teamstag][0]][0] ?? null;
      $seriesCondition = $pairSeriesId === null || ($pairSeriesId && $seriesid != $pairSeriesId);
      
      if ($seriesid && $pairSeriesId && $seriesid == $pairSeriesId) {
        $condition = false;
      } elseif ($seriesid && $pairSeriesId && $seriesid != $pairSeriesId) {
        // Nested / wrong series ids for the same meeting (e.g. games 1+3 share one id,
        // game 2 carries another). Trust inter-game timing, not the id mismatch alone.
        $condition = $timeCondition;
      } else {
        $condition = ($sip && $seriesid) ? $seriesCondition : $timeCondition;
      }
    } else {
      $condition = true;
    }
    if (!$meetCnts[$teamstag][1] || $condition) {
      $meetCnts[$teamstag][0]++;
      $partCnts[$teamstag] = 0;
      $seriesIds[$teamstag.'.'.$meetCnts[$teamstag][0]] = [ $seriesid, $series_tag ];
    }
    $meetCnts[$teamstag][1] = $time;
    $meetCnts[$teamstag][2] = max($timeDiff, 1700);
    $meetCnts[$teamstag][3] = $duration;
    $series_tag = $seriesIds[$teamstag.'.'.$meetCnts[$teamstag][0]][1];
    if (!isset($series[$series_tag])) {
      $series[$series_tag] = [
        'seriesid' => $seriesid,
        'matches' => []
      ];
    }

    if (!isset($partCnts[$teamstag])) {
      $partCnts[$teamstag] = 0;
      $totalCnt[$teamstag] = 0;
    }
    if (!isset($totalCnt[$teamstag])) {
      $totalCnt[$teamstag] = 0;
    }
    $partCnts[$teamstag]++;
    $totalCnt[$teamstag]++;
    $cnt = $partCnts[$teamstag];
    if (!$series[$series_tag]['seriesid'] && $seriesid) {
      $series[$series_tag]['seriesid'] = $seriesid;
      $seriesIds[$teamstag.'.'.$meetCnts[$teamstag][0]][0] = $seriesid;
    }
    if (!empty($seriesid)) {
      $seriesTt[$seriesid] = $teamstag;
    }
    $series[$series_tag]['matches'][] = $mid;
    $_rep_add['match_parts_series_tag'][$mid] = $series_tag;
    if (!empty($teamsStr)) {
      $_rep_add['match_parts_strings'][$mid] = $teamsStr
        .' - '.locale_string('meet_num').' '.$meetCnts[$teamstag][0]
        .' - '.locale_string('game_num').' '.$cnt;
    } else {
      $_rep_add['match_parts_strings'][$mid] = locale_string('meet_num').' '.$series_tag;
    }
    $_rep_add['match_parts_series_num'][$mid] = $meetCnts[$teamstag][0];
    $_rep_add['match_parts_game_num'][$mid] = $cnt;
    $_rep_add['match_parts_total_game_num'][$mid] = $totalCnt[$teamstag];
  }

  return [ $series, $_rep_add ];
}
