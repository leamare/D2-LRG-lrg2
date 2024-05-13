<?php 

function fetch_valve_api($match_id): array {
  global $steamapikey, $api_cooldown_seconds, $meta;

  $json = @file_get_contents("https://api.steampowered.com/IDOTA2Match_570/GetMatchDetails/V001/?match_id={$match_id}&key=$steamapikey");
  sleep($api_cooldown_seconds * 2);

  $match = json_decode($json, true);

  if (empty($match) || empty($match['result']) || isset($match['result']['error'])) {
    return [];
  }

  // skipping items and skill builds... for now

  // parameters to look out for:
  // - ability_upgrades
  // - scaled_hero_damage
  // - scaled_tower_damage
  // - scaled_hero_healing
  // - gold (end game gold)
  // - gold_spent (total gold spent)
  // - item_N + backpack_N + item_neutral
  // - aghanims_scepter + aghanims_shard + moonshard
  // - pre_game_duration
  // - human_players
  // - first_blood_time
  // - barracks_status_radiant
  // - barracks_status_dire
  // - tower_status_radiant
  // - tower_status_dire
  // - radiant_captain
  // - dire_captain

  $res = [
    'matches' => [
      'matchid' => $match_id,
      'radiantWin' => $match['result']['radiant_win'],
      'duration' => $match['result']['duration'],
      'modeID' => $match['result']['game_mode'],
      'cluster' => $match['result']['cluster'],
      'start_date' => $match['result']['start_time'],
      'leagueID' => $match['result']['leagueid'],
      'version' => get_patchid($match['result']['start_time'], $meta),
      'analysis_status' => 0,
      'seriesid' => 0,
      'stomp' => 0,
      'comeback' => 0,
    ],
    'payload' => [
      'score_radiant' => $match['result']['radiant_score'],
      'score_dire' => $match['result']['dire_score'],
      'leavers' => 0
    ],
    'draft' => [],
    'players' => [],
    'matchlines' => [],
    'teams' => [],
    'teams_matches' => [],
  ];

  foreach ($match['result']['players'] as $pl) {
    if ($pl['account_id'] = 4294967295) {
      $pl['account_id'] = ($pl['hero_id'] * -1);
      $pl['name'] = "Anon ".$meta['heroes'][ $pl['hero_id'] ]['name']." player";
    }

    $ml = [];
    $ml['matchid'] = $match_id;
    $ml['playerid'] = $pl['account_id'];
    $ml['heroid'] = $pl['hero_id'];
    $ml['isRadiant'] = $pl['team_number'] == 0;
    $ml['level'] = $pl['level'];
    $ml['kills'] = $pl['kills'];
    $ml['deaths'] = $pl['deaths'];
    $ml['assists'] = $pl['assists'];
    $ml['networth'] = $pl['net_worth'];
    $ml['gpm'] = $pl['gold_per_min'];
    $ml['xpm'] = $pl['xp_per_min'];
    $ml['heal'] = $pl['hero_healing'];
    $ml['heroDamage'] = $pl['hero_damage'];
    $ml['towerDamage'] = $pl['tower_damage'];
    $ml['lastHits'] = $pl['last_hits'];
    $ml['denies'] = $pl['denies'];

    // leaver_status

    $res['matchlines'][] = $ml;

    $res['players'][] = [
      'playerID' => $pl['account_id'],
      'nickname' => $pl['name'] ?? "Steam Player ".$pl['account_id']
    ];
  }

  if (isset($match['result']['radiant_team_id'])) {
    $res['teams'][] = [
      'teamid' => $match['result']['radiant_team_id'],
      'name' => $match['result']['radiant_name'],
      'tag' => generate_tag($match['result']['radiant_name']),
    ];

    $res['teams_matches'][] = [
      'matchid' => $match_id,
      'teamid' => $match['result']['radiant_team_id'],
      'is_radiant' => 1,
    ];
  }

  if (isset($match['result']['dire_team_id'])) {
    $res['teams'][] = [
      'teamid' => $match['result']['dire_team_id'],
      'name' => $match['result']['dire_name'],
      'tag' => generate_tag($match['result']['dire_name']),
    ];

    $res['teams_matches'][] = [
      'matchid' => $match_id,
      'teamid' => $match['result']['dire_team_id'],
      'is_radiant' => 0,
    ];
  }

  // I should move this draft stages code elsewhere, shouldn't I
  if (!empty($match['result']['picks_bans'])) {
    $stage = 0;
    $last_stage_pick = null;

    foreach ($match['result']['picks_bans'] as $dr) {
      $d = [];

      $d['matchid'] = $match_id;
      $d['is_radiant'] = $dr['team'] == 0 ? 1 : 0;
      $d['is_pick'] = $dr['is_pick'] ? 1 : 0;
      $d['hero_id'] = $dr['hero_id'];

      if (empty($d['hero_id'])) continue;

      if ($match['result']['game_mode'] == 2 || $match['result']['game_mode'] == 9) {
        $last_stage_pick = null;
        if ($last_stage_pick != $d['is_pick'] && !$d['is_pick']) {
          $stage++;
        }
        $last_stage_pick = $d['is_pick'];
        $d['stage'] = $stage;
      } else if ($match['result']['game_mode'] == 16) {
        if ($dr['is_pick']) {
          if ($dr['order'] < 11) $d['stage'] = 1;
          else if ($dr['order'] < 15) $d['stage'] = 2;
          else $d['stage'] = 3;
        } else {
            $d['stage'] = 1;
        }
      } else if ($match['result']['game_mode'] == 22 || $match['result']['game_mode'] == 3) {
        if ($dr['is_pick']) {
          if ($dr['order'] < 4) $d['stage'] = 1;
          else if ($dr['order'] < 8) $d['stage'] = 2;
          else $d['stage'] = 3;
        } else $d['stage'] = 1;
      } else {
        $d['stage'] = 1;
      }

      $d['order'] = $dr['order'] ?? 0;

      $res['draft'][] = $d;
    }
  }

   return $res;
}