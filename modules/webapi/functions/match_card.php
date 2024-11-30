<?php

function match_card($mid) {
  global $vars;
  global $report;
  global $meta;

  if ($vars['simple_matchcard']) return match_card_min($mid);

  $match = [
    "match_id" => $mid
  ];
  
  if (empty($mid)) return null;

  if (!isset($report['matches'])) return $match;

  $match['string'] = isset($report['match_parts_strings']) ? $report['match_parts_strings'][$mid] ?? null : null;
  $match['series_num'] = isset($report['match_parts_series_num']) ? $report['match_parts_series_num'][$mid] ?? null : null;
  $match['game_num'] = isset($report['match_parts_game_num']) ? $report['match_parts_game_num'][$mid] ?? null : null;

  $clusters = $meta['clusters'];
  $regions = $meta['regions'];

  $m = $report['matches'][$mid];

  $match['players'] = [
    "radiant" => [],
    "dire" => []
  ];

  foreach ($m as $pl) {
    $player = [
      "player_id" => $pl['player'],
      "player_name" => player_name($pl['player']),
      "hero_id" => $pl['hero']
    ];
    if (!empty($pl['var'])) {
      $player['variant'] = $pl['var'];
    }
    $match['players'][$pl['radiant'] == 1 ? 'radiant' : 'dire'][] = $player;
  }

  $match['bans'] = null;
  if (isset($report['matches_additional'][$mid]['bans'])) {
    $match['bans'] = [];
    $match['bans']['radiant'] = $report['matches_additional'][$mid]['bans'][1];
    $match['bans']['dire'] = $report['matches_additional'][$mid]['bans'][0];
  }

  if (isset($report['matches_additional'][$mid]['order']) && !(
    $report['matches_additional'][$mid]['game_mode'] == 1 || $report['matches_additional'][$mid]['game_mode'] == 23 || 
    $report['matches_additional'][$mid]['game_mode'] == 4 || $report['matches_additional'][$mid]['game_mode'] == 11
  )) {
    $match['order'] = $report['matches_additional'][$mid]['order'];
  }
  
  if(isset($report['teams']) && isset($report['match_participants_teams'][$mid])) {
    $teams = [];
    if(isset($report['match_participants_teams'][$mid]['radiant']) &&
       isset($report['teams'][ $report['match_participants_teams'][$mid]['radiant'] ]['name']))
       $teams['radiant'] = team_card_min($report['match_participants_teams'][$mid]['radiant']);
    else $team_radiant = $teams['radiant'] = null;
    if(isset($report['match_participants_teams'][$mid]['dire']) &&
       isset($report['teams'][ $report['match_participants_teams'][$mid]['dire'] ]['name']))
      $teams['dire'] = team_card_min($report['match_participants_teams'][$mid]['dire']);
    else $team_dire = $teams['dire'] = null;

    $match['teams'] = $teams;
  }

  $match['score'] = [
    "radiant" => $report['matches_additional'][$mid]['radiant_score'],
    "dire" => $report['matches_additional'][$mid]['dire_score']
  ];
  $match['radiant_win'] = $report['matches_additional'][$mid]['radiant_win'];
  
  $match['networth'] = [
    "radiant" => $report['matches_additional'][$mid]['radiant_nw'],
    "dire" => $report['matches_additional'][$mid]['dire_nw']
  ];

  $match['duration'] = $report['matches_additional'][$mid]['duration'];

  $match['cluster'] = $report['matches_additional'][$mid]['cluster'];

  $match['region'] = $meta['clusters'][ $report['matches_additional'][$mid]['cluster'] ] ?? 0;

  $match['game_mode'] = $report['matches_additional'][$mid]['game_mode'];

  $match['date'] = $report['matches_additional'][$mid]['date'];

  if (isset($report['records'])) {
    $match_records = [];

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
        }

        if (!empty($context_records_ext)) {
          foreach ($context_records_ext[$rectag] ?? [] as $i => $rec) {
            if (empty($rec)) continue;
            if ($rec['matchid'] == $mid) {
              $rec['tag'] = $rectag;
              $rec['placement'] = $i+2;
              $rec['region'] = $reg;
              $match_records[] = $rec;
            }
          }
        }
      }
    }

    $match['records'] = $match_records;
  }

  return $match;
}

function match_card_min($match) {
  global $report;

  if(isset($report['teams']) && isset($report['match_participants_teams'][$match])) {
    $teams = [];
    if(isset($report['match_participants_teams'][$match]['radiant']) &&
       isset($report['teams'][ $report['match_participants_teams'][$match]['radiant'] ]['name']))
       $teams['radiant'] = $report['match_participants_teams'][$match]['radiant'];
    else $team_radiant = $teams['radiant'] = null;
    if(isset($report['match_participants_teams'][$match]['dire']) &&
       isset($report['teams'][ $report['match_participants_teams'][$match]['dire'] ]['name']))
      $teams['dire'] = $report['match_participants_teams'][$match]['dire'];
    else $team_dire = $teams['dire'] = null;
  }

  return [
    'match_id' => $match,
    'string' => isset($report['match_parts_strings']) ? $report['match_parts_strings'][$match] ?? null : null,
    'series_num' => isset($report['match_parts_series_num']) ? $report['match_parts_series_num'][$match] ?? null : null,
    'game_num' => isset($report['match_parts_game_num']) ? $report['match_parts_game_num'][$match] ?? null : null,
    'total_game_num' => isset($report['match_parts_total_game_num']) ? $report['match_parts_total_game_num'][$match] ?? null : null,
    'teams' => $teams ?? []
  ];
}