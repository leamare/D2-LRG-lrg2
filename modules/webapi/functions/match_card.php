<?php

function match_card($mid) {
  global $report;
  global $meta;

  $match = [
    "match_id" => $mid
  ];
  
  if (empty($mid)) return null;

  if (!isset($report['matches'])) return $match;

  $clusters = $meta['clusters'];
  $regions = $meta['regions'];

  $m = $report['matches'][$mid];

  $match['players'] = [
    "radiant" => [],
    "dire" => []
  ];

  foreach ($m as $pl) {
    $match['players'][$pl['radiant'] == 1 ? 'radiant' : 'dire'][] = [
      "player_id" => $pl['player'],
      "player_name" => player_name($pl['player']),
      "hero_id" => $pl['hero']
    ];
  }
  
  if(isset($report['teams']) && isset($report['match_participants_teams'][$mid])) {
    $teams = [];
    if(isset($report['match_participants_teams'][$mid]['radiant']) &&
       isset($report['teams'][ $report['match_participants_teams'][$mid]['radiant'] ]['name']))
       $teams['radiant'] = $report['match_participants_teams'][$mid]['radiant'];
    else $team_radiant = $teams['radiant'] = null;
    if(isset($report['match_participants_teams'][$mid]['dire']) &&
       isset($report['teams'][ $report['match_participants_teams'][$mid]['dire'] ]['name']))
      $teams['dire'] = $report['match_participants_teams'][$mid]['dire'];
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

  $match['region'] = $meta['clusters'][ $report['matches_additional'][$mid]['cluster'] ];

  $match['game_mode'] = $report['matches_additional'][$mid]['game_mode'];

  $match['date'] = $report['matches_additional'][$mid]['date'];

  return $match;
}

?>
