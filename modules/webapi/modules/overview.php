<?php 

include_once(__DIR__ . "/../functions/draft_accuracy_test.php");
include_once(__DIR__ . "/../functions/pickban_overview.php");
include_once(__DIR__ . "/../functions/overview_uncontested.php");
include_once(__DIR__ . "/../functions/overview_positions.php");
include_once(__DIR__ . "/../functions/overview_combos.php");

$endpoints['overview'] = function($mods, $vars, &$report) use (&$meta, &$endpoints) {
  $res = [];

  $descriptor = get_report_descriptor($report);

  if (isset($vars['region'])) {
    $context =& $report['regions_data'][ $vars['region'] ];
  } else {
    $context =& $report;
  }

  if (!isset($context['main'])) $context['main'] = $context['random'];

  if (!isset($context['settings']['limiter_higher'])) $context['settings']['limiter_higher'] = $context['settings']['limiter'];
  if (!isset($context['settings']['limiter_lower'])) $context['settings']['limiter_lower'] = $context['settings']['limiter_triplets'];
  if (!isset($context['settings']['limiter_graph'])) $context['settings']['limiter_graph'] = $context['settings']['limiter_combograph'];

  if (!isset($context['settings']['overview_last_match_winners']))
    $context['settings']['overview_last_match_winners'] = $report['settings']['overview_last_match_winners'];
  
  $context_total_matches = isset($context['main']['matches_total']) ? $context['main']['matches_total'] : $context['main']['matches'];

  if (isset($context['overview'])) {
    foreach($context['overview'] as $k => $v) {
      $context[$k] = $v;
    }
  }

  // simple stats

  $res['matches_total'] = $context_total_matches;

  if(isset($report['teams']))
    $res['teams_on_event'] = $context['main']['teams_on_event'];
  else
    $res['players_on_event'] = $context['main']['players_on_event'];

  $res['versions'] = $context['versions'];
  $res['game_modes'] = $context['modes'];

  if(isset($context['regions'])) {
    $regions_matches = [];
    $meta['clusters'];
    foreach ($context['regions'] as $mode => $data) {
      $region = $meta['clusters'][$mode] ?? 0;
      if(isset($regions_matches[$region])) $regions_matches[$region] += $data;
      else $regions_matches[$region] = $data;
    }
    $res['regions'] = $regions_matches;
    $res['clusters'] = $context['regions'];
  }

  if($report['settings']['overview_time_limits']) {
    if(isset($context['first_match']))
      $res['first_match'] = match_card($context['first_match']['mid']);
    if(isset($context['last_match']))
      $res['last_match'] = match_card($context['last_match']['mid']);
  }

  if($context['settings']['overview_last_match_winners'] || !isset($context['settings'])) {
    $res['last_match_radiant_win'] = $report['matches_additional'][ $context['last_match']['mid'] ]['radiant_win'];
  }

  $res['heroes_contested'] = [
    "picked_and_banned" => ($context['main']['heroes_banned']-$context['main']['heroes_contested']+$context['main']['heroes_picked']),
    "picked" => ($context['main']['heroes_contested']-$context['main']['heroes_banned']),
    "banned" => ($context['main']['heroes_contested']-$context['main']['heroes_picked']),
    "uncontested" => (sizeof($meta['heroes'])-$context['main']['heroes_contested'])
  ];

  if (isset($context['days'])) {
    $res['days'] = $context['days'];
    if (!$descriptor['matches_details']) {
      foreach ($res['days'] as &$day) {
        if (isset($day['matches'])) {
          $day['matches_num'] = sizeof($day['matches']);
          unset($day['matches']);
        }
      }
    }
  }

  $res['main'] = $context['main'];

  // notable participants

  $res['participants'] = [];

  if(isset($report['players_additional']) || isset($report["teams"])) {
    if (isset($report['teams']) && $context['settings']['overview_last_match_winners']) {
      if($report['matches_additional'][ $context['last_match']['mid'] ]['radiant_win']) {
          if (isset( $report['match_participants_teams'][ $context['last_match']['mid'] ]['radiant'] ))
              $tid = $report['match_participants_teams'][ $context['last_match']['mid'] ]['radiant'];
          else $tid = 0;
      } else {
          if (isset($report['match_participants_teams'][ $context['last_match']['mid'] ]['dire']) )
              $tid = $report['match_participants_teams'][ $context['last_match']['mid'] ]['dire'];
          else $tid = 0;
      }
      if ($tid) {
        $res['participants']['last_match_winner_team_id'] = [
          "team_id" => $tid,
          "team_name" => team_name($tid),
          "team_tag" => team_tag($tid),
        ];
      }
    }

    if (isset($report['teams'])) {
      $max_wr = 0;
      $max_matches = 0;
      foreach ($context['teams'] as $team_id => $team) {
        if(!isset($report['teams'][$team_id]['matches_total'])) continue; //FIXME
        if(!$max_matches || $report['teams'][$max_matches]['matches_total'] < $report['teams'][$team_id]['matches_total'] )
          $max_matches = $team_id;
        if($report['teams'][$team_id]['matches_total'] <= $context['settings']['limiter_higher']) continue;

        if($max_wr == 0) $max_wr = $team_id;
        else if(!$max_wr ||
                $report['teams'][$max_wr]['wins']/$report['teams'][$max_wr]['matches_total'] <
                  $report['teams'][$team_id]['wins']/$report['teams'][$team_id]['matches_total'] )
          $max_wr = $team_id;
      }

      $res['participants']['most_matches_team_id'] = [
        "team_id" => $max_matches,
        "team_name" => team_name($max_matches),
        "team_tag" => team_tag($max_matches),
        "value" => $report['teams'][$max_matches]['matches_total']
      ];

      if($max_wr) {
        $res['participants']['highest_winrate_team_id'] = [
          "team_id" => $max_wr,
          "team_name" => team_name($max_wr),
          "team_tag" => team_tag($max_wr),
          "value" => round($report['teams'][$max_wr]['wins']*100/$report['teams'][$max_wr]['matches_total'],2)
        ];
      }

      if (isset($context['records'])) {
        $res['participants']['widest_hero_pool_team'] = [
          "team_id" => $report['records']['widest_hero_pool_team']['playerid'],
          "team_name" => team_name($report['records']['widest_hero_pool_team']['playerid']),
          "team_tag" => team_tag($report['records']['widest_hero_pool_team']['playerid']),
          "value" => $report['records']['widest_hero_pool_team']['value']
        ];
        $res['participants']['smallest_hero_pool_team'] = [
          "team_id" => $report['records']['smallest_hero_pool_team']['playerid'],
          "team_name" => team_name($report['records']['smallest_hero_pool_team']['playerid']),
          "team_tag" => team_tag($report['records']['smallest_hero_pool_team']['playerid']),
          "value" => $report['records']['smallest_hero_pool_team']['value']
        ];
      }
    } else if (isset($report['players_additional']) && isset($context['players_summary'])) {
      $max_wr = 0;
      $max_matches = 0;
      foreach ($context['players_summary'] as $pid => $player) {
          if(!$max_matches || $report['players_additional'][$max_matches]['matches'] < $report['players_additional'][$pid]['matches'] )
            $max_matches = $pid;
          if($report['players_additional'][$pid]['matches'] <= $context['settings']['limiter_higher']) continue;
          if(!$max_wr || (
              $report['players_additional'][$max_wr]['won']/$report['players_additional'][$max_wr]['matches'] <
                $report['players_additional'][$pid]['won']/$report['players_additional'][$pid]['matches']) )
            $max_wr = $pid;
      }

      $res['participants']['most_matches_player_id'] = [
        "player_id" => $max_matches,
        "player_name" => player_name($max_matches),
        "value" => $report['players_additional'][$max_matches]['matches']
      ];

      if($max_wr)
        $res['participants']['most_matches_player_id'] = [
          "player_id" => $max_wr,
          "player_name" => player_name($max_wr),
          "value" => round($report['players_additional'][$max_wr]['won']*100/$report['players_additional'][$max_wr]['matches'],2)
        ];
    }

    if (isset($context['records'])) {
      $res['participants']['widest_hero_pool'] = [
        "player_id" => $context['records']['widest_hero_pool']['playerid'],
        "player_name" => player_name($context['records']['widest_hero_pool']['playerid']),
        "value" => $context['records']['widest_hero_pool']['value']
      ];
      $res['participants']['smallest_hero_pool'] = [
        "player_id" => $context['records']['smallest_hero_pool']['playerid'],
        "player_name" => player_name($context['records']['smallest_hero_pool']['playerid']),
        "value" => $context['records']['smallest_hero_pool']['value']
      ];
    }

    if (isset($context['averages_players']) && isset($context['averages_players']['diversity'])) {
      $res['participants']['diversity'] = [
        "player_id" => $context['averages_players']['diversity'][0]['playerid'],
        "player_name" => player_name($context['averages_players']['diversity'][0]['playerid']),
        "value" => round($context['averages_players']['diversity'][0]['value']*100,2)
      ];
    }
  }

  if (isset($context['records']) && isset($report['settings']['overview_include_records']) && $report['settings']['overview_include_records']) {
    $res['records'] = $endpoints['records']($mods, $vars, $report);
  }

  if(isset($context['teams']) && $report['settings']['overview_teams_summary_short']) {
    $mods_cpy = $mods;
    $mods_cpy[] = "teams";
    $res['teams_summary'] = $endpoints['summary']($mods_cpy, $vars, $report);
  }

  // heroes stats

  $res['draft_is_accurate'] = rgapi_draft_accuracy_test($context['pickban'], $context['draft']);

  if($report['settings']['overview_top_contested']) {
    $res['pickban_overview'] = rgapi_generator_pickban_overview($context['pickban'],
      $context_total_matches, 
      $report['settings']['overview_top_contested_count']
    );
  }

  if($report['settings']['overview_top_picked']) {
    $res['draft_top_picked'] = [];

    $workspace = $context['pickban'];
    uasort($workspace, function($a, $b) {
      if($a['matches_picked'] == $b['matches_picked']) {
        if($a['matches_total'] == $b['matches_total']) return 0;
        else return ($a['matches_total'] < $b['matches_total']) ? 1 : -1;
      } else return ($a['matches_picked'] < $b['matches_picked']) ? 1 : -1;
    });

    $counter = $report['settings']['overview_top_picked_count'];
    foreach($workspace as $hid => $hero) {
      if($counter == 0) break;
      $res['draft_top_picked'][] = [
        "hero_id" => $hid,
        "matches_total" => $hero['matches_total'],
        "matches_picked" => $hero['matches_picked'],
        "winrate_picked" => round($hero['winrate_picked']*100,2)
      ];
      $counter--;
    }
    unset($workspace);
  }

  if($report['settings']['overview_top_bans']) {
    $res['draft_top_banned'] = [];

    $workspace = $context['pickban'];
    uasort($workspace, function($a, $b) {
      if($a['matches_banned'] == $b['matches_banned']) {
        if($a['matches_total'] == $b['matches_total']) return 0;
        else return ($a['matches_total'] < $b['matches_total']) ? 1 : -1;
      } else return ($a['matches_banned'] < $b['matches_banned']) ? 1 : -1;
    });

    $counter = $report['settings']['overview_top_bans_count'];
    foreach($workspace as $hid => $hero) {
      if($counter == 0) break;
      $res['draft_top_picked'][] = [
        "hero_id" => $hid,
        "matches_total" => $hero['matches_total'],
        "matches_picked" => $hero['matches_banned'],
        "winrate_picked" => round($hero['winrate_banned']*100,2)
      ];
      $counter--;
    }
  }

  $uncontested = rgapi_generator_uncontested($meta['heroes'], $context['pickban'], true);
  $res[ $uncontested['type'] ] = $uncontested['data'];

  if($report['settings']['overview_top_draft']) {
    $res['draft_overview'] = [];

    for ($i=1; $i>=0; $i--) {
      for ($j=1; $j<4; $j++) {
        if($report['settings']["overview_draft_".$i."_".$j] && isset($context['draft']) && !empty($context['draft'][$i][$j])) {
          if (!isset($res['draft_overview'][$i])) $res['draft_overview'][$i] = [];
          $res['draft_overview'][$i][$j] = [];
          
          $counter = $report['settings']["overview_draft_".$i."_".$j."_count"];

          uasort($context['draft'][$i][$j], function($a, $b) {
            if($a['matches'] == $b['matches']) return 0;
            else return ($a['matches'] < $b['matches']) ? 1 : -1;
          });
          foreach($context['draft'][$i][$j] as $hero) {
            if($counter == 0) break;
            $res['draft_overview'][$i][$j][] = $hero;
            $counter--;
          }
        }
      }
    }
  }

  if(($report['settings']['overview_positions'] ?? false) && isset($context['hero_positions'])) {
    $res['positions_overview'] = rgapi_generator_overview_positions_section(
      $context['hero_positions'], 
      $context['pickban'],
      $report['settings']['overview_positions_count'] ?? $report['settings']['overview_top_picked_count'] ?? 5,
      $report['settings']['overview_positions_sort'] ?? "matches"
    );
  }

  if($report['settings']['overview_top_hero_pairs'] && isset($context['hero_pairs']) && !empty($context['hero_pairs'])) {
    $res['hero_pairs'] = rgapi_generator_overview_combos(
      $context['hero_pairs'],
      $report['settings']['overview_top_hero_pairs_count']
    );
  }

  if(!isset($report['teams']) && $report['settings']['overview_top_player_pairs'] && isset($context['player_pairs']) && !empty($context['player_pairs'])) {
    $res['player_pairs'] = rgapi_generator_overview_combos(
      $context['player_pairs'],
      $report['settings']['overview_top_player_pairs_count'],
      false
    );
  }

  // notable matches

  if($report['settings']['overview_matches']) {
    $res['notable_matches'] = [];

    if(($context['settings']['overview_first_match'] ?? true) && isset($context['first_match']))
      $res['notable_matches']['first_match'] = match_card($context['first_match']['mid']);
    if(($context['settings']['overview_last_match'] ?? true) && isset($context['last_match']))
      $res['notable_matches']['last_match'] = match_card($context['last_match']['mid']);

    if(isset($context['records'])) {
      if($report['settings']['overview_records_stomp'])
        $res['notable_matches']['match_stomp'] = match_card($context['records']['stomp']['matchid']);
      if($report['settings']['overview_records_comeback'])
        $res['notable_matches']['match_comeback'] = match_card($context['records']['comeback']['matchid']);
      if($report['settings']['overview_records_duration']) {
        $res['notable_matches']['longest_match'] = match_card($context['records']['longest_match']['matchid']);
        $res['notable_matches']['shortest_match'] = match_card($context['records']['shortest_match']['matchid']);
      }
    }
  }

  // settings

  $res['technical'] = [
    'limiter_lower' => $context['settings']['limiter_lower'],
    'limiter_higher' => $context['settings']['limiter_higher'],
    'limiter_graph' => $context['settings']['limiter_graph'],
    'version' => parse_ver($report['ana_version'])
  ];

  return $res;
};
