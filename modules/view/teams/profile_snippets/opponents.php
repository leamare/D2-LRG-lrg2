<?php 

$tvt = rg_generator_tvt_unwrap_data($report['tvt'], $report['teams']);

$tvt[0] = [];

if (!empty($report['series'])) {
  $team_series = [];
  foreach ($report['series'] as $st => $s) {
    foreach ($s['matches'] as $mid) {
      if (!isset($report['match_participants_teams'][$mid]['radiant'])) continue;
      if ($report['match_participants_teams'][$mid]['radiant'] == $tid) {
        $s['opponent'] = $report['match_participants_teams'][$mid]['dire'] ?? 0;
        $team_series[$st] = $s;
        break;
      }
      if (($report['match_participants_teams'][$mid]['dire'] ?? null) == $tid) {
        $s['opponent'] = $report['match_participants_teams'][$mid]['radiant'] ?? 0;
        $team_series[$st] = $s;
        break;
      }
    }
  }
}

foreach ($report['match_participants_teams'] as $mid => $teams) {
  if (empty($teams['dire'])) $teams['dire'] = 0;
  if (empty($teams['radiant'])) $teams['radiant'] = 0;

  if (isset($report['matches_additional'])) {
    if (!isset($tvt[$teams['dire']][$teams['radiant']])) {
      $tvt[$teams['dire']][$teams['radiant']] = [
        'matches' => 0,
        'wins' => 0,
        'matchids' => []
      ];
    }

    if (!$teams['radiant']) {
      $tvt[$teams['dire']][$teams['radiant']]['matches']++;
      if ($report['matches_additional'][$mid]['radiant_win']) 
        $tvt[$teams['dire']][$teams['radiant']]['wins']++;
    }

    if (!isset($tvt[$teams['radiant']][$teams['dire']])) {
      $tvt[$teams['radiant']][$teams['dire']] = [
        'matches' => 0,
        'wins' => 0,
        'matchids' => []
      ];
    }

    if (!$teams['dire']) {
      $tvt[$teams['radiant']][$teams['dire']]['matches']++;
      if (!$report['matches_additional'][$mid]['radiant_win']) 
        $tvt[$teams['radiant']][$teams['dire']]['wins']++;
    }
  }

  if (!isset($tvt[$teams['dire']][$teams['radiant']]['matchids'])) {
    $tvt[$teams['dire']][$teams['radiant']]['matchids'] = [];
  }
  $tvt[$teams['dire']][$teams['radiant']]['matchids'][] = $mid;

  if (!isset($tvt[$teams['radiant']][$teams['dire']]['matchids'])) {
    $tvt[$teams['radiant']][$teams['dire']]['matchids'] = [];
  }
  $tvt[$teams['radiant']][$teams['dire']]['matchids'][] = $mid;
}

if (!empty($team_series)) {
  foreach ($team_series as $st => $s) {
    if (!isset($tvt[$s['opponent']][$tid]['series'])) {
      $tvt[$s['opponent']][$tid]['series'] = 0;
      $tvt[$s['opponent']][$tid]['series_wins'] = 0;
      $tvt[$s['opponent']][$tid]['series_loss'] = 0;
      $tvt[$s['opponent']][$tid]['series_tie'] = 0;
    }
    if (!isset($tvt[$tid][$s['opponent']]['series'])) {
      $tvt[$tid][$s['opponent']]['series'] = 0;
      $tvt[$tid][$s['opponent']]['series_wins'] = 0;
      $tvt[$tid][$s['opponent']]['series_loss'] = 0;
      $tvt[$tid][$s['opponent']]['series_tie'] = 0;
    }
    $tvt[$s['opponent']][$tid]['series']++;
    $tvt[$tid][$s['opponent']]['series']++;

    $scores = [];
    $matches_count = count($s['matches']);
    foreach ($s['matches'] as $match) {
      if (!isset($report['match_participants_teams'][$match])) continue;
      if (!isset($scores[$report['match_participants_teams'][$match]['radiant'] ?? 0]))  {
        $scores[$report['match_participants_teams'][$match]['radiant'] ?? 0] = 0;
      }
      $scores[$report['match_participants_teams'][$match]['radiant'] ?? 0] += $report['matches_additional'][$match]['radiant_win'] ? 1 : 0;
      if (!isset($scores[$report['match_participants_teams'][$match]['dire'] ?? 0]))  {
        $scores[$report['match_participants_teams'][$match]['dire'] ?? 0] = 0;
      }
      $scores[$report['match_participants_teams'][$match]['dire'] ?? 0] += $report['matches_additional'][$match]['radiant_win'] ? 0 : 1;
    }

    $non_tie_factor = ($matches_count > 1 && ((array_sum($scores)/2) != max($scores))) || $matches_count == 1;

    if (!empty($scores) && $non_tie_factor) {
      $winner = array_search(max($scores), $scores);
    } else {
      $winner = null;
    }

    if ($winner == $tid) {
      $tvt[$s['opponent']][$tid]['series_loss']++;
      $tvt[$tid][$s['opponent']]['series_wins']++;
    } elseif ($winner == $s['opponent']) {
      $tvt[$s['opponent']][$tid]['series_wins']++;
      $tvt[$tid][$s['opponent']]['series_loss']++;
    } else {
      $tvt[$s['opponent']][$tid]['series_tie']++;
      $tvt[$tid][$s['opponent']]['series_tie']++;
    }
  }
}

uasort($tvt[$tid], function ($a, $b) {
  return $b['matches'] <=> $a['matches'];
});

global $leaguetag;

$res["team".$tid]['opponents'] .= search_filter_component("teams-$tid-opponents");

$res["team".$tid]['opponents'] .= "<table id=\"teams-$tid-opponents\" class=\"list sortable\">
  <thead><tr>".
    "<th></th>".
    "<th data-sortInitialOrder=\"asc\">".locale_string("team_name")."</th>".
    "<th>".locale_string("matches")."</th>".
    "<th>".locale_string("winrate")."</th>".
    (!empty($team_series) ? "<th>".locale_string("meet_num")."</th>".
      "<th>".locale_string("score")."</th>" : "").
    (isset($report['match_participants_teams']) ? "<th>".locale_string("matchlinks")."</th>" : "").
  "</tr></thead><tbody>";

foreach ($tvt[$tid] as $opid => $data) {
  if (!$data['matches']) continue;
  if (!isset($data['winrate'])) $data['winrate'] = $data['wins']/$data['matches'];

  $optag = $opid ? "optid$opid" : "unteamed";

  $res["team".$tid]['opponents'] .= "<tr>".
    "<td>".team_logo($opid)."</td>".
    "<td>".team_link($opid)."</td>".
    "<td>".$data['matches']."</td>".
    "<td>".number_format($data['winrate']*100,2)."%</td>".
    (!empty($team_series) ? "<td>".($data['series'] ?? 0)."</td>".
      "<td>".($data['series_wins'] ?? 0)."-".($data['series_tie'] ?? 0)."-".($data['series_loss'] ?? 0)."</td>" : "").
    // (isset($data['matchids']) ? "<td><a onclick=\"showModal('".
    //   htmlspecialchars(join_matches($data['matchids'])).
    //   "', '".locale_string("matches")." - ".addcslashes(team_name($tid)." vs. ".team_name($opid), "'")."');\">".
    //   locale_string("matches")."</a></td>" : ""
    // ).
    (isset($data['matchids']) 
      ? "<td><a href=\"?league=$leaguetag&mod=teams-profiles-team$tid-matches-opponents-$optag".(empty($linkvars) ? "" : "&".$linkvars)."\">".
        locale_string("matches")."</a>".
        (!empty($team_series) ? " / ".
          "<a href=\"?league=$leaguetag&mod=teams-profiles-team$tid-matches-series&opponent=$opid".(empty($linkvars) ? "" : "&".$linkvars)."\">".
          locale_string("series")."</a>" : 
        "").
      "</td>" : ""
    ).
  "</tr>";
}

$res["team".$tid]['opponents'] .= "</tbody></table>";