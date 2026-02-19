<?php 

// placeholders
function team_tag($team) {
  return $result['teams'][$team]['tag'] ?? $team;
}
function locale_string($string) {
  return $string;
}

include_once(__DIR__ . "/../commons/series.php");

if ((!empty($result['teams'])) && !empty($result['matches']) && !empty($result['match_participants_teams'])) {
  [ $series, $_report_add ] = generate_series_data($result);

  uasort($series, function($a, $b) {
    return $a['matches'][0] <=> $b['matches'][0];
  });

  $teams_series_win_streaks = [];
  $teams_series_loss_streaks = [];
  $last_outcome = [];

  $series_durations = [];
  $series_win_streaks = [];
  $series_loss_streaks = [];

  // I Could include this in the report itself, but it gives more flexibility to have it as a softgen feature

  // records: longerst series by playtime, shortest series by playtime
  // ...longest series winstreak, longest series lossstreak

  foreach ($series as $series_tag => $series_data) {
    $series_id = ($series_data['seriesid'] ?? 0) ? $series_data['seriesid'] : $series_tag;
    $matches_count = count($series_data['matches']);

    $playtime = 0;

    $scores = [];
    $winner = null;

    foreach ($series_data['matches'] as $match) {
      if (!isset($result['match_participants_teams'][$match])) continue;
      if (!isset($scores[$result['match_participants_teams'][$match]['radiant'] ?? 0]))  {
        $scores[$result['match_participants_teams'][$match]['radiant'] ?? 0] = 0;
      }
      $scores[$result['match_participants_teams'][$match]['radiant'] ?? 0] += $result['matches_additional'][$match]['radiant_win'] ? 1 : 0;
      if (!isset($scores[$result['match_participants_teams'][$match]['dire'] ?? 0]))  {
        $scores[$result['match_participants_teams'][$match]['dire'] ?? 0] = 0;
      }
      $scores[$result['match_participants_teams'][$match]['dire'] ?? 0] += $result['matches_additional'][$match]['radiant_win'] ? 0 : 1;


      $playtime += $result['matches_additional'][$match]['duration'];
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

    foreach ($teams as $team) {
      if (!isset($teams_series_win_streaks[$team])) {
        $teams_series_win_streaks[$team] = [
          'streak' => 0,
          'last_series_mid' => 0,
        ];
      }
      if (!isset($teams_series_loss_streaks[$team])) {
        $teams_series_loss_streaks[$team] = [
          'streak' => 0,
          'last_series_mid' => 0,
        ];
      }
      if (!isset($last_outcome[$team])) {
        $last_outcome[$team] = null;
      }

      if ($winner == $team || $winner === null) {
        $teams_series_win_streaks[$team]['streak']++;
        $teams_series_win_streaks[$team]['last_series_mid'] = $series_data['matches'][0];
        if ($last_outcome[$team] !== null && $last_outcome[$team] == 0) {
          $series_loss_streaks[] = [
            'streak' => $teams_series_loss_streaks[$team]['streak'],
            'last_series_mid' => $teams_series_loss_streaks[$team]['last_series_mid'],
            'team' => $team,
          ];
          $teams_series_loss_streaks[$team]['streak'] = 0;
        }
        $last_outcome[$team] = 2;
      } else {
        $teams_series_loss_streaks[$team]['streak']++;
        $teams_series_loss_streaks[$team]['last_series_mid'] = $series_data['matches'][0];
        if ($last_outcome[$team] !== null && $last_outcome[$team] == 2) {
          $series_win_streaks[] = [
            'streak' => $teams_series_win_streaks[$team]['streak'],
            'last_series_mid' => $teams_series_win_streaks[$team]['last_series_mid'],
            'team' => $team,
          ];
          $teams_series_win_streaks[$team]['streak'] = 0;
        }
        $last_outcome[$team] = 0;
      }
    }

    $series_durations[$series_tag] = $playtime;
  }

  foreach ($teams_series_win_streaks as $team => $data) {
    if ($data['streak'] > 0) {
      $series_win_streaks[] = [
        'streak' => $data['streak'],
        'last_series_mid' => $data['last_series_mid'],
        'team' => $team,
      ];
    }
  }

  foreach ($teams_series_loss_streaks as $team => $data) {
    if ($data['streak'] > 0) {
      $series_loss_streaks[] = [
        'streak' => $data['streak'],
        'last_series_mid' => $data['last_series_mid'],
        'team' => $team,
      ];
    }
  }

  arsort($series_durations);
  $limit = $lg_settings['ana']['records_extended'] ?? 6;
  $result['records_ext']['longest_series_duration'] = [];

  for ($i = 0; $i < $limit; $i++) {
    $st = array_keys($series_durations)[$i] ?? null;
    if (empty($st)) {
      if ($i > 0) $result['records_ext']['longest_series_duration'][] = null;
      continue;
    }
    $record = [
      'matchid'  => $series[$st]['matches'][0],
      'value'    => $series_durations[$st] / 60,
      'playerid' => 0,
      'heroid'   => 0,
    ];
    if ($i == 0) {
      $result['records']['longest_series_duration'] = $record;
    } else {
      $result['records_ext']['longest_series_duration'][] = $record;
    }
  }

  $result['records_ext']['keys'][] = 'longest_series_duration';
  $result['records_ext']['data'][] = array_values($result['records_ext']['longest_series_duration']);
  unset($result['records_ext']['longest_series_duration']);

  usort($series_win_streaks, function($a, $b) {
    return $b['streak'] <=> $a['streak'];
  });
  usort($series_loss_streaks, function($a, $b) {
    return $b['streak'] <=> $a['streak'];
  });
  
  $result['records_ext']['team_series_win_streak'] = [];
  for ($i = 0; $i < $limit; $i++) {
    $data = $series_win_streaks[$i] ?? null;
    if (empty($data)) {
      if ($i > 0) $result['records_ext']['team_series_win_streak'][] = null;
      continue;
    }

    $record = [
      'matchid'  => $data['last_series_mid'],
      'value'    => $data['streak'],
      'playerid' => $data['team'],
      'heroid'   => 0,
    ];
    if ($i == 0) {
      $result['records']['team_series_win_streak'] = $record;
    } else {
      $result['records_ext']['team_series_win_streak'][] = $record;
    }
  }

  $result['records_ext']['keys'][] = 'team_series_win_streak';
  $result['records_ext']['data'][] = array_values($result['records_ext']['team_series_win_streak']);
  unset($result['records_ext']['team_series_win_streak']);

  $result['records_ext']['team_series_loss_streak'] = [];
  for ($i = 0; $i < $limit; $i++) {
    $data = $series_loss_streaks[$i] ?? null;
    if (empty($data)) {
      if ($i > 0) $result['records_ext']['team_series_loss_streak'][] = null;
      continue;
    }
    
    $record = [
      'matchid'  => $data['last_series_mid'],
      'value'    => $data['streak'],
      'playerid' => $data['team'],
      'heroid'   => 0,
    ];
    if ($i == 0) {
      $result['records']['team_series_loss_streak'] = $record;
    } else {
      $result['records_ext']['team_series_loss_streak'][] = $record;
    }
  }

  $result['records_ext']['keys'][] = 'team_series_loss_streak';
  $result['records_ext']['data'][] = array_values($result['records_ext']['team_series_loss_streak']);
  unset($result['records_ext']['team_series_loss_streak']);
}

// general winstreaks records for teams / players

if (!empty($result['matches'])) {
  $win_streaks = [];
  $loss_streaks = [];
  
  if (!empty($result['teams'])) {
    $teams_win_streaks = [];
    $teams_loss_streaks = [];
    $last_outcome = [];
    
    foreach ($result['matches'] as $mid => $data) {
      if (empty($result['match_participants_teams'][$mid]['radiant']) || empty($result['match_participants_teams'][$mid]['dire'])) continue;
      $radiant_win = $result['matches_additional'][$mid]['radiant_win'];
      $radiant = $result['match_participants_teams'][$mid]['radiant'];
      $dire = $result['match_participants_teams'][$mid]['dire'];

      if (!isset($teams_win_streaks[$radiant])) {
        $teams_win_streaks[$radiant] = [
          'streak' => 0,
          'last_match_mid' => $mid,
        ];
      }
      if (!isset($teams_win_streaks[$dire])) {
        $teams_win_streaks[$dire] = [
          'streak' => 0,
          'last_match_mid' => $mid,
        ];
      }
      if (!isset($teams_loss_streaks[$radiant])) {
        $teams_loss_streaks[$radiant] = [
          'streak' => 0,
          'last_match_mid' => $mid,
        ];
      }
      if (!isset($teams_loss_streaks[$dire])) {
        $teams_loss_streaks[$dire] = [
          'streak' => 0,
          'last_match_mid' => $mid,
        ];
      }
      if (!isset($last_outcome[$radiant])) {
        $last_outcome[$radiant] = null;
      }
      if (!isset($last_outcome[$dire])) {
        $last_outcome[$dire] = null;
      }

      if ($radiant_win) {
        $teams_win_streaks[$radiant]['streak']++;
        $teams_win_streaks[$radiant]['last_match_mid'] = $mid;
        if ($last_outcome[$radiant] !== null && $last_outcome[$radiant] == 0) {
          $loss_streaks[] = [
            'streak' => $teams_loss_streaks[$radiant]['streak'],
            'last_match_mid' => $teams_loss_streaks[$radiant]['last_match_mid'],
            'team' => $radiant,
          ];
          $teams_loss_streaks[$radiant]['streak'] = 0;
        }

        $teams_loss_streaks[$dire]['streak']++;
        $teams_loss_streaks[$dire]['last_match_mid'] = $mid;
        if ($last_outcome[$dire] !== null && $last_outcome[$dire] == 2) {
          $win_streaks[] = [
            'streak' => $teams_win_streaks[$dire]['streak'],
            'last_match_mid' => $teams_win_streaks[$dire]['last_match_mid'],
            'team' => $dire,
          ];
          $teams_win_streaks[$dire]['streak'] = 0;
        }
        $last_outcome[$radiant] = 2;
        $last_outcome[$dire] = 0;
      } else {
        $teams_win_streaks[$dire]['streak']++;
        $teams_win_streaks[$dire]['last_match_mid'] = $mid;
        if ($last_outcome[$dire] !== null && $last_outcome[$dire] == 0) {
          $loss_streaks[] = [
            'streak' => $teams_loss_streaks[$dire]['streak'],
            'last_match_mid' => $teams_loss_streaks[$dire]['last_match_mid'],
            'team' => $dire,
          ];
          $teams_loss_streaks[$dire]['streak'] = 0;
        }

        $teams_loss_streaks[$radiant]['streak']++;
        $teams_loss_streaks[$radiant]['last_match_mid'] = $mid;
        if ($last_outcome[$radiant] !== null && $last_outcome[$radiant] == 2) {
          $win_streaks[] = [
            'streak' => $teams_win_streaks[$radiant]['streak'],
            'last_match_mid' => $teams_win_streaks[$radiant]['last_match_mid'],
            'team' => $radiant,
          ];
          $teams_win_streaks[$radiant]['streak'] = 0;
        }
        $last_outcome[$radiant] = 0;
        $last_outcome[$dire] = 2;
      }
    }

    foreach ($teams_win_streaks as $team => $data) {
      if ($data['streak'] > 0) {
        $win_streaks[] = [
          'streak' => $data['streak'],
          'last_match_mid' => $data['last_match_mid'],
          'team' => $team,
        ];
      }
    }

    foreach ($teams_loss_streaks as $team => $data) {
      if ($data['streak'] > 0) {
        $loss_streaks[] = [
          'streak' => $data['streak'],
          'last_match_mid' => $data['last_match_mid'],
          'team' => $team,
        ];
      }
    }

    usort($win_streaks, function($a, $b) {
      return $b['streak'] <=> $a['streak'];
    });
    usort($loss_streaks, function($a, $b) {
      return $b['streak'] <=> $a['streak'];
    });
    $win_streaks = array_values($win_streaks);

    $result['records_ext']['team_win_streak'] = [];
    for ($i = 0; $i < $limit; $i++) {
      $data = $win_streaks[$i] ?? null;
      if (empty($data)) {
        if ($i > 0) $result['records_ext']['team_win_streak'][] = null;
        continue;
      }

      $record = [
        'matchid'  => $data['last_match_mid'],
        'value'    => $data['streak'],
        'playerid' => $data['team'],
        'heroid'   => 0,
      ];
      if ($i == 0) {
        $result['records']['team_win_streak'] = $record;
      } else {
        $result['records_ext']['team_win_streak'][] = $record;
      }
    }

    $result['records_ext']['keys'][] = 'team_win_streak';
    $result['records_ext']['data'][] = array_values($result['records_ext']['team_win_streak']);
    unset($result['records_ext']['team_win_streak']);

    $result['records_ext']['team_loss_streak'] = [];
    for ($i = 0; $i < $limit; $i++) {
      $data = $loss_streaks[$i] ?? null;
      if (empty($data)) {
        if ($i > 0) $result['records_ext']['team_loss_streak'][] = null;
        continue;
      }
      
      $record = [
        'matchid'  => $data['last_match_mid'],
        'value'    => $data['streak'],
        'playerid' => $data['team'],
        'heroid'   => 0,
      ];
      if ($i == 0) {
        $result['records']['team_loss_streak'] = $record;
      } else {
        $result['records_ext']['team_loss_streak'][] = $record;
      }
    }

    $result['records_ext']['keys'][] = 'team_loss_streak';
    $result['records_ext']['data'][] = array_values($result['records_ext']['team_loss_streak']);
    unset($result['records_ext']['team_loss_streak']);
  } else {
    $players_win_streaks = [];
    $players_loss_streaks = [];
    $last_outcome = [];

    foreach ($result['matches'] as $mid => $data) {
      $radiant_win = $result['matches_additional'][$mid]['radiant_win'];
      foreach ($data as $player) {
        $is_win = $player['radiant'] == $radiant_win;
        
        if (!isset($players_win_streaks[$player['player']])) {
          $players_win_streaks[$player['player']] = [
            'streak' => 0,
            'last_match_mid' => $mid,
            'last_heroid' => $player['hero'],
          ];
        }
        if (!isset($players_loss_streaks[$player['player']])) {
          $players_loss_streaks[$player['player']] = [
            'streak' => 0,
            'last_match_mid' => $mid,
            'last_heroid' => $player['hero'],
          ];
        }

        if (!isset($last_outcome[$player['player']])) {
          $last_outcome[$player['player']] = null;
        }

        if ($is_win) {
          $players_win_streaks[$player['player']]['streak']++;
          $players_win_streaks[$player['player']]['last_match_mid'] = $mid;
          $players_win_streaks[$player['player']]['last_heroid'] = $player['hero'];

          if ($last_outcome[$player['player']] !== null && $last_outcome[$player['player']] == 0) {
            $loss_streaks[] = [
              'streak' => $players_loss_streaks[$player['player']]['streak'],
              'last_match_mid' => $mid,
              'last_heroid' => $player['hero'],
              'playerid' => $player['player'],
            ];
            $players_loss_streaks[$player['player']]['streak'] = 0;
          }
          $last_outcome[$player['player']] = 2;
        } else {
          $players_loss_streaks[$player['player']]['streak']++;
          $players_loss_streaks[$player['player']]['last_match_mid'] = $mid;
          $players_loss_streaks[$player['player']]['last_heroid'] = $player['hero'];

          if ($last_outcome[$player['player']] !== null && $last_outcome[$player['player']] == 2) {
            $win_streaks[] = [
              'streak' => $players_win_streaks[$player['player']]['streak'],
              'last_match_mid' => $mid,
              'last_heroid' => $player['hero'],
              'playerid' => $player['player'],
            ];
            $players_win_streaks[$player['player']]['streak'] = 0;
          }
          $last_outcome[$player['player']] = 0;
        }
      }
    }

    foreach ($players_win_streaks as $player => $data) {
      if ($data['streak'] > 0) {
        $win_streaks[] = [
          'streak' => $data['streak'],
          'last_match_mid' => $data['last_match_mid'],
          'playerid' => $player,
        ];
      }
    }
    foreach ($players_loss_streaks as $player => $data) {
      if ($data['streak'] > 0) {
        $loss_streaks[] = [
          'streak' => $data['streak'],
          'last_match_mid' => $data['last_match_mid'],
          'playerid' => $player,
        ];
      }
    }

    usort($win_streaks, function($a, $b) {
      return $b['streak'] <=> $a['streak'];
    });
    usort($loss_streaks, function($a, $b) {
      return $b['streak'] <=> $a['streak'];
    });
    $win_streaks = array_values($win_streaks);
    $loss_streaks = array_values($loss_streaks);

    $result['records_ext']['player_win_streak'] = [];
    for ($i = 0; $i < $limit; $i++) {
      $data = $win_streaks[$i] ?? null;
      if (empty($data)) {
        if ($i > 0) $result['records_ext']['player_win_streak'][] = null;
        continue;
      }

      $record = [
        'matchid'  => $data['last_match_mid'],
        'value'    => $data['streak'],
        'playerid' => $data['playerid'],
        'heroid'   => 0,
      ];
      if ($i == 0) {
        $result['records']['player_win_streak'] = $record;
      } else {
        $result['records_ext']['player_win_streak'][] = $record;
      }
    }

    $result['records_ext']['keys'][] = 'player_win_streak';
    $result['records_ext']['data'][] = array_values($result['records_ext']['player_win_streak']);
    unset($result['records_ext']['player_win_streak']);

    $result['records_ext']['player_loss_streak'] = [];
    for ($i = 0; $i < $limit; $i++) {
      $data = $loss_streaks[$i] ?? null;
      if (empty($data)) {
        if ($i > 0) $result['records_ext']['player_loss_streak'][] = null;
        continue;
      }
      
      $record = [
        'matchid'  => $data['last_match_mid'],
        'value'    => $data['streak'],
        'playerid' => $data['playerid'],
        'heroid'   => 0,
      ];
      if ($i == 0) {
        $result['records']['player_loss_streak'] = $record;
      } else {
        $result['records_ext']['player_loss_streak'][] = $record;
      }
    }

    $result['records_ext']['keys'][] = 'player_loss_streak';
    $result['records_ext']['data'][] = array_values($result['records_ext']['player_loss_streak']);
    unset($result['records_ext']['player_loss_streak']);
  }
}