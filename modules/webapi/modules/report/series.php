<?php 

$repeatVars['series'] = ['team', 'optid'];

$endpoints['series'] = function($mods, $vars, &$report) use (&$meta) {
  if (empty($report['matches'])) 
    throw new Exception("No matches available for this report");

  if (!isset($report['series']))
    throw new Exception("No series available for this report");

  $res = [];

  if (isset($vars['team'])) {
    $context =& $report['teams'][ $vars['team'] ]['matches'];
  } else if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ]['matches'];
  } else {
    $context =& $report['matches'];
  }

  if ($vars['team'] ?? false)
    $res['card'] = team_card($vars['team']);

  $matches = [];

  foreach ($context as $id => $data) {
    if (isset($report['matches_additional']) && isset($vars['team']) && isset($vars['region'])) {
      $region = $meta['clusters'][ $report['matches_additional'][$id]['cluster'] ];
      if ($region != $vars['region']) continue;
    }

    if (isset($vars['optid']) && isset($report['match_participants_teams'])) {
      if (!in_array($vars['optid'], $report['match_participants_teams'][$id]) && !(!$vars['optid'] && count($report['match_participants_teams'][$id]) < 2)) {
        continue;
      }
    }

    if (isset($vars['playerid']) && isset($vars['heroid'])) {
      $found = false;
      foreach ($report['matches'][$id] as $pl) {
        if ($pl['player'] == $vars['playerid'] && $pl['hero'] == $vars['heroid']) {
          if (isset($vars['team']) && isset($report['match_participants_teams']) && ( $report['match_participants_teams'][$id][ $pl['radiant'] ? 'radiant' : 'dire' ] ?? null ) != $vars['team']) {
            continue;
          }
          if (!check_positions_matches($id, false)) continue;
          $found = true;
          break;
        }
      }
      if (!$found) continue;
    } else if (isset($vars['heroid'])) {
      $found = false;
      foreach ($report['matches'][$id] as $pl) {
        if ($pl['hero'] == $vars['heroid']) {
          if (isset($vars['variant']) && $pl['var'] != $vars['variant']) {
            continue;
          }
          if (isset($vars['team']) && isset($report['match_participants_teams']) && ( $report['match_participants_teams'][$id][ $pl['radiant'] ? 'radiant' : 'dire' ] ?? null ) != $vars['team']) {
            continue;
          }
          if (!check_positions_matches($id, true)) continue;
          $found = true;
          break;
        }
      }
      if (!$found) continue;
    } else if (isset($vars['playerid'])) {
      $found = false;
      foreach ($report['matches'][$id] as $slot => $pl) {
        if ($pl['player'] == $vars['playerid']) {
          if (isset($vars['team']) && isset($report['match_participants_teams']) && ( $report['match_participants_teams'][$id][ $pl['radiant'] ? 'radiant' : 'dire' ] ?? null ) != $vars['team']) {
            continue;
          }
          if (!check_positions_matches($id, false)) continue;
          $found = true;
          break;
        }
      }
      if (!$found) continue;
    }

    $matches[] = $id;
  }

  $series_filtered = [];

  foreach ($report['series'] as $sid => $series) {
    foreach ($series['matches'] as $mid) {
      if (in_array($mid, $matches)) {
        $series_filtered[$sid] = $series;
        break;
      }
    }
  }

  $res['series'] = [];
  foreach ($series_filtered as $st => $series_data) {
    $matches_count = count($series_data['matches']);

    $start_date = [];
    $end_date = [];
    $playtime = 0;

    $scores = [];
    $winner = null;
    $heroes_picks = [];
    $heroes_bans = [];
    $heroes_both = [];

    foreach ($series_data['matches'] as $match) {
      if (!isset($report['match_participants_teams'][$match])) continue;
      if (!isset($scores[$report['match_participants_teams'][$match]['radiant'] ?? 0]))  {
        $scores[$report['match_participants_teams'][$match]['radiant'] ?? 0] = 0;
      }
      $scores[$report['match_participants_teams'][$match]['radiant'] ?? 0] += $report['matches_additional'][$match]['radiant_win'] ? 1 : 0;
      if (!isset($scores[$report['match_participants_teams'][$match]['dire'] ?? 0]))  {
        $scores[$report['match_participants_teams'][$match]['dire'] ?? 0] = 0;
      }
      $scores[$report['match_participants_teams'][$match]['dire'] ?? 0] += $report['matches_additional'][$match]['radiant_win'] ? 0 : 1;

      foreach ($report['matches'][$match] as $l) {
        $heroes_picks[$l['hero']] = ($heroes_picks[$l['hero']] ?? 0) + 1;
      }
      foreach (($report['matches_additional'][$match]['bans'] ?? []) as $t) {
        foreach ($t as $b) {
          $heroes_bans[$b[0]] = ($heroes_bans[$b[0]] ?? 0) + 1;
        }
      }

      $playtime += $report['matches_additional'][$match]['duration'];
      $start_date[] = $report['matches_additional'][$match]['date'];
      $end_date[] = $report['matches_additional'][$match]['date'] + $report['matches_additional'][$match]['duration'];
    }

    $start_date_unix = !empty($start_date) ? min($start_date) : 0;
    $end_date_unix = !empty($end_date) ? max($end_date) : 0;
    $total_duration = $end_date_unix-$start_date_unix;

    if ($matches_count > 1) {
      $heroes_list = array_merge(array_keys($heroes_picks), array_keys($heroes_bans));
      $heroes_list = array_unique($heroes_list);
      foreach ($heroes_list as $hero) {
        if (($heroes_picks[$hero] ?? 0) + ($heroes_bans[$hero] ?? 0) == $matches_count && isset($heroes_bans[$hero]) && isset($heroes_picks[$hero])) {
          $heroes_both[$hero] = true;
        }
      }
      $heroes_picks = array_filter($heroes_picks, function($hero) use ($matches_count) {
        return $hero == $matches_count;
      });
      $heroes_bans = array_filter($heroes_bans, function($hero) use ($matches_count) {
        return $hero == $matches_count;
      });
    } else {
      $heroes_picks = [];
      $heroes_bans = [];
      $heroes_both = [];
    }

    $non_tie_factor = ($matches_count > 1 && ((array_sum($scores)/2) != max($scores))) || $matches_count == 1;

    if (!empty($scores) && $non_tie_factor) {
      $winner = array_search(max($scores), $scores);
    } else {
      $winner = null;
    }

    $teams = array_filter(array_keys($scores), function($team) use ($winner) {
      return $team != 0;
    });

    $res['series'][] = [
      'series_tag' => $st,
      'seriesid' => $series_data['seriesid'],
      'matches' => $series_data['matches'],
      'teams' => array_map(function($team) {
        return $team == 0 ? null : team_card_min($team);
      }, $teams),
      'winner' => $winner,
      'scores' => $scores,
      'shared_heroes' => [
        'picks' => array_keys($heroes_picks),
        'bans' => array_keys($heroes_bans),
        'both' => array_keys($heroes_both),
      ],
      'start_date' => $start_date_unix,
      'end_date' => $end_date_unix,
      'total_duration' => $total_duration,
      'playtime' => $playtime,
    ];
  }

  return $res;
};

// func check_positions_matches defined in matches.php