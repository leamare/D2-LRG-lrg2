<?php 

include_once __DIR__.'/comebacks.php';

const ROSHAN = [133, 134, 135, 263, 324, 325, 326, 371, 593, 594, 595, 640];
const OBS = [110, 499, 768];
const SENTRY = [500, 769, 111];
const LEVELS_RESPAWN = [5,7,9,13,16,26,28,30,32,34,36,44,46,48,50,52,54,65,70,75,80,85,90,95,100,100,100,100,100,100];

const STRATZ_GAME_MODE = [
  'NONE' => 0,
  'ALL_PICK' => 1,
  'CAPTAINS_MODE' => 2,
  'RANDOM_DRAFT' => 3,
  'SINGLE_DRAFT' => 4,
  'ALL_RANDOM' => 5,
  'INTRO' => 6,
  'THE_DIRETIDE' => 7,
  'REVERSE_CAPTAINS_MODE' => 8,
  'THE_GREEVILING' => 9,
  'TUTORIAL' => 10,
  'MID_ONLY' => 11,
  'LEAST_PLAYED' => 12,
  'NEW_PLAYER_POOL' => 13,
  'COMPENDIUM_MATCHMAKING' => 14,
  'CUSTOM' => 15,
  'CAPTAINS_DRAFT' => 16,
  'BALANCED_DRAFT' => 17,
  'ABILITY_DRAFT' => 18,
  'EVENT' => 19,
  'ALL_RANDOM_DEATH_MATCH' => 20,
  'SOLO_MID' => 21,
  'ALL_PICK_RANKED' => 22,
  'TURBO' => 23,
  'MUTATION' => 24,
];

const STRATZ_LOBBY_TYPE = [
  'UNRANKED' => 0,
  'PRACTICE' => 1,
  'TOURNAMENT' => 2,
  'TUTORIAL' => 3,
  'COOP_VS_BOTS' => 4,
  'TEAM_MATCH' => 5,
  'SOLO_QUEUE' => 6,
  'RANKED' => 7,
  'SOLO_MID' => 8,
  'BATTLE_CUP' => 9,
  'EVENT' => 12,
  'INVALID' => -1,
];

const STRATZ_LANE_TYPE = [
  'SAFE_LANE' => 1,
  'MID_LANE' => 2,
  'OFF_LANE' => 3,
  'JUNGLE' => 4,
  'ROAMING' => 4,
  'UNKNOWN' => 0,
];

const STRATZ_GRAPHQL_QUERY = "{
  clusterId
  gameMode
  gameVersionId
  statsDateTime
  startDateTime
  leagueId
  durationSeconds
  parsedDateTime
  sequenceNum
  replaySalt
  regionId
  lobbyType
  id
  isStats
  stats {
    matchId
    radiantNetworthLeads
    radiantKills
    direKills
    pickBans {
      bannedHeroId
      heroId
      isPick
      isRadiant
      order
      playerIndex
      wasBannedSuccessfully
      team
    }
  }
  league {
    name
  }
  numHumanPlayers
  didRadiantWin
  players {
    steamAccountId
    heroId
    level
    isRadiant
    leaverStatus
    stats {
      campStack
      heroDamageReport {
        receivedTotal {
          magicalDamage
          physicalDamage
          pureDamage
        }
        dealtTotal {
          stunDuration
          disableDuration
        }
      }
      deniesPerMinute
      courierKills {
        time
      }
      lastHitsPerMinute
      networthPerMinute
      wards {
        type
      }
      itemPurchases {
        time
        itemId
      }
      deathEvents {
        timeDead
        time
        goldFed
        byAbility
      }
      killEvents {
        time
      }
      itemPurchases {
        time
        itemId
      }
      inventoryReport {
        neutral0 {
          itemId
        }
        item0 {
          itemId
        }
        item1 {
          itemId
        }
        item2 {
          itemId
        }
        item3 {
          itemId
        }
        item4 {
          itemId
        }
        item5 {
          itemId
        }
        backPack0 {
          itemId
        }
        backPack1 {
          itemId
        }
        backPack2 {
          itemId
        }
      }
      matchPlayerBuffEvent {
        abilityId
        itemId
        stackCount
        time
      }
      farmDistributionReport {
        creepType {
          count
          id
        }
        other {
          count
          id
        }
      }
      wardDestruction {
        gold
        time
        experience
      }
      actionReport {
        pingUsed
      }
      level
    }
    assists
    deaths
    experiencePerMinute
    heroDamage
    heroHealing
    lane
    kills
    goldPerMinute
    gold
    goldSpent
    networth
    role
    numLastHits
    numDenies
    towerDamage
    roleBasic
    steamAccount {
      name
    }
  }
  direTeam {
    name
    tag
  }
  direTeamId
  radiantTeamId
  radiantTeam {
    name
    tag
  }
}";

function get_stratz_response($match) {
  global $stratztoken, $meta, $stratz_cache, $api_cooldown_seconds;

  if (isset($stratz_cache[ $match ])) {
    $stratz = [
      'data' => [
        'match' => $stratz_cache[ $match ]
      ]
    ];
  } else {
    $data = [
      'query' => "{ match(id: $match) ".STRATZ_GRAPHQL_QUERY."}"
    ];
  
    /* 
        playbackData {
          buyBackEvents {
            time
          }
          streakEvents {
            time
            type
            value
          }
        }
    */
  
    $data['query'] = str_replace("  ", "", $data['query']);
    $data['query'] = str_replace("\n", " ", $data['query']);
  
    if (!empty($stratztoken)) $data['key'] = $stratztoken;
      
    $stratz_request = "https://api.stratz.com/graphql";
  
    $q = http_build_query($data);

    sleep($api_cooldown_seconds);
      
    // $context  = stream_context_create([
    //   'https' => [
    //     'method' => 'POST',
    //     'header'  => 'Content-Type: application/x-www-form-urlencoded'. 
    //       "\r\ncontent-length: ".strlen($q)."\r\ncontent-type: application/json",
    //     'content' => $q
    //   ]
    // ]);
  
    // $json = file_get_contents($stratz_request, false, $context);
    $json = @file_get_contents($stratz_request.'?'.$q, false, stream_context_create([
      'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
      ]
    ]));
    
    if (empty($json)) return null;
  
    $stratz = json_decode($json, true);
    
    if (empty($stratz['data']) || empty($stratz['data']['match']) || !empty($stratz['errors'])) {
      return null;
    }
  }

  $r = [];

  $r['matches'] = [];
  $r['matches']['matchid'] = $stratz['data']['match']['id'];
  $r['matches']['radiantWin'] = $stratz['data']['match']['didRadiantWin'];
  $r['matches']['duration'] = $stratz['data']['match']['durationSeconds'];
  $r['matches']['modeID'] = STRATZ_GAME_MODE[ $stratz['data']['match']['gameMode'] ] ?? $stratz['data']['match']['gameMode'];
  $r['matches']['cluster'] = $stratz['data']['match']['clusterId'];
  $r['matches']['start_date'] = $stratz['data']['match']['startDateTime'];
  $r['matches']['leagueID'] = $stratz['data']['match']['leagueId'] ?? 0;
  $r['matches']['version'] = get_patchid($r['matches']['start_date'], convert_patch_id($r['matches']['start_date']), $meta);

  if ($stratz['data']['match']['statsDateTime']) {
    [ $r['matches']['stomp'], $r['matches']['comeback'] ] = find_comebacks($stratz['data']['match']['stats']['radiantNetworthLeads'], $stratz['data']['match']['didRadiantWin']);
  } else {
    $r['matches']['stomp'] = 0;
    $r['matches']['comeback'] = 0;
  }

  $r['payload'] = [
    'score_radiant' => 0,
    'score_dire' => 0,
    'leavers' => 0
  ];

  $r['matchlines'] = [];
  $r['adv_matchlines'] = [];
  $r['items'] = [];
  $r['players'] = [];

  foreach ($stratz['data']['match']['players'] as $i => $pl) {
    $r['payload']['score_radiant'] += $pl['isRadiant'] ? $pl['kills'] : 0;
    $r['payload']['score_dire'] += !$pl['isRadiant'] ? $pl['kills'] : 0;
    $r['payload']['leavers'] += (
      is_numeric($pl['leaverStatus']) 
      ? $pl['leaverStatus'] > 1
      : $pl['leaverStatus'] !== 'NONE'
    ) ? 1 : 0;

    $ml = [];
    $ml['matchid'] = $stratz['data']['match']['id'];
    $ml['playerid'] = $pl['steamAccountId'];
    $ml['heroid'] = $pl['heroId'];
    $ml['isRadiant'] = $pl['isRadiant'];
    $ml['level'] = $pl['level'];
    $ml['kills'] = $pl['kills'];
    $ml['deaths'] = $pl['deaths'];
    $ml['assists'] = $pl['assists'];
    $ml['networth'] = $pl['networth'];
    $ml['gpm'] = $pl['goldPerMinute'];
    $ml['xpm'] = $pl['experiencePerMinute'];
    $ml['heal'] = $pl['heroHealing'];
    $ml['heroDamage'] = $pl['heroDamage'];
    $ml['towerDamage'] = $pl['towerDamage'];
    $ml['lastHits'] = $pl['numLastHits'];
    $ml['denies'] = $pl['numDenies'];

    $r['matchlines'][] = $ml;

    $r['players'][] = [
      'playerID' => $pl['steamAccountId'],
      'nickname' => $pl['steamAccount']['name']
    ];

    if ($stratz['data']['match']['statsDateTime']) {
      $aml = [];

      $aml['matchid'] = $stratz['data']['match']['id'];
      $aml['playerid'] = $pl['steamAccountId'];
      $aml['heroid'] = $pl['heroId'];

      $aml['lh_at10'] = array_sum(
        array_slice($pl['stats']['lastHitsPerMinute'], 0, 10)
      );
      
      $aml['lane'] = is_numeric($pl['lane'])
        ? ( ($pl['lane'] > 3 || !$pl['lane']) ? 4 : $pl['lane'] )
        : STRATZ_LANE_TYPE[$pl['lane']];

      if ($aml['lane'] == 4 || !$aml['lane']) $aml['isCore'] = 0;
      else $aml['isCore'] = (is_numeric($pl['roleBasic']) ? $pl['roleBasic'] : $pl['roleBasic'] !== 'CORE') ? 0 : 1;
      
      $melee = (40 * 60);
      $ranged = (45 * 20);
      $siege = (74 * 2);
      $passive = (600 * 1.5);
      $starting = 625;
      $tenMinute = $melee + $ranged + $siege + $passive + $starting;
      $aml['efficiency_at10'] = $pl['stats']['networthPerMinute'][10] / $tenMinute;
      
      $aml['wards'] = count(
        array_filter($pl['stats']['itemPurchases'], function($a) { return $a['itemId'] == 42; })
      );
      $aml['sentries'] = count(
        array_filter($pl['stats']['itemPurchases'], function($a) { return $a['itemId'] == 43; })
      );

      $aml['couriers_killed'] = count($pl['stats']['courierKills'] ?? []);

      $aml['roshans_killed'] = 0;
      $aml['wards_destroyed'] = count($pl['stats']['wardDestruction']);

      foreach ($pl['stats']['farmDistributionReport'] as $f) {
        foreach ($f['creepType'] as $fc) {
          // if (in_array($fc['id'], OBS)) $aml['wards_destroyed'] += $fc['count'];
          if (in_array($fc['id'], ROSHAN)) $aml['roshans_killed'] += $fc['count'];
        }
        // foreach ($f['other'] as $fc) {
        //   if (in_array($fc['id'], ROSHAN)) $aml['roshans_killed'] += $fc['count'];
        //   if (in_array($fc['id'], OBS)) $aml['wards_destroyed'] += $fc['count'];
        // }
      }
      
      $kde = [];
      foreach ($pl['stats']['killEvents'] as $s) {
        $kde[] = [
          'time' => $s['time'],
          'kill' => true
        ];
      }
      foreach ($pl['stats']['deathEvents'] as $s) {
        if (!$s['goldFed']) continue;
        $kde[] = [
          'time' => $s['time'],
          'kill' => false
        ];
      }
      usort($kde, function($a, $b) { return $a['time'] <=> $b['time']; });

      if (!empty($pl['playbackData']) && !empty($pl['playbackData']['streakEvents'])) {
        $streaks = [];
        $multis = [];
        foreach ($pl['playbackData']['streakEvents'] as $s) {
          if ($s['type'] == 'MULTI_KILL')
            $multis[] = $s['value'];
          else
            $streaks[] = $s['value'];
        }
      } else {
        $streaks = [];
        $multis = [];
        $cur_streak = 0;
        $cur_multi = 1;
        $last = 0;
        foreach ($kde as $e) {
          if ($e['kill']) {
            $cur_streak++;

            if ($e['time'] - $last < 18) {
              $cur_multi++;
            } else {
              $multis[] = $cur_multi;
              $cur_multi = 1;
            }

            $last = $e['time'];
          } else {
            $streaks[] = $cur_streak;
            $cur_streak = 0;
          }
        }
        $streaks[] = $cur_streak;
        $multis[] = count($kde) ? $cur_multi : 0;
      }
      $aml['multi_kill'] = !empty($multis) ? max($multis) : 0;
      $aml['streak'] = !empty($streaks) ? max($streaks) : 0;
      
      if (!empty($pl['playbackData']) && isset($pl['playbackData']['buyBackEvents'])) {
        $aml['buybacks'] = count($pl['playbackData']['buyBackEvents']);
      } else {
        // This implementation is going to be replaced rather soon
        // This method of calculating buybacks is not reliable, but
        // it's all we have for now
        $aml['buybacks'] = 0;
        foreach ($pl['stats']['deathEvents'] as $s) {
          $level = 24;
          foreach ($pl['stats']['level'] as $i => $time) {
            if ($time > $s['time']) {
              $level = $i;
              break;
            }
          }
          $diff = $s['timeDead'] - LEVELS_RESPAWN[$level-1];
          if ($diff > 10 && ($s['byAbility'] !== 5161 || $diff > 5+ceil(($level - $level % 18) / 6)*10 )) {
            $aml['buybacks']++;
          }

          // implementation to be used later
          // when timeDead will be fixed
          // FIXME: 
          // $diff = $s['timeDead'] - LEVELS_RESPAWN[$level] - ($s['byAbility'] == 5161 ? $diff > 5+ceil(($level - $level % 18) / 6)*10 : 0 );
          // if ($diff < 0) {
          //   $aml['buybacks']++;
          // }
        }
      }
      
      $aml['stacks'] = $pl['stats']['campStack'] ? max($pl['stats']['campStack']) : 0;
      
      $aml['time_dead'] = array_reduce($pl['stats']['deathEvents'], function($c, $a) { return $c + $a['timeDead']; }, 0);
      $aml['pings'] = $pl['stats']['actionReport']['pingUsed'] ?? 0;
      
      $aml['stuns'] = (($pl['stats']['heroDamageReport']['dealtTotal']['stunDuration'] ?? 0) + ($pl['stats']['heroDamageReport']['dealtTotal']['disableDuration'] ?? 0))/100;

      $aml['teamfight_part'] = $pl['isRadiant'] ? array_sum($stratz['data']['match']['stats']['radiantKills'] ?? []) : array_sum($stratz['data']['match']['stats']['direKills'] ?? []);
      $aml['teamfight_part'] = $aml['teamfight_part'] ? ($pl['kills']+$pl['assists']) / $aml['teamfight_part'] : 0;
      $aml['damage_taken'] = array_sum($pl['stats']['heroDamageReport']['receivedTotal'] ?? []);

      $r['adv_matchlines'][] = $aml;

      $meta['items'];
      $meta['item_categories'];
      $travel_boots_state = 0;

      $items = [];
      $items_all = [];
      $items_cats  = [];

      foreach ($pl['stats']['itemPurchases'] as $e) {
        if ($r['matches']['duration'] - $e['time'] < 60) continue;

        $it = [
          'matchid' => $stratz['data']['match']['id'],
          'playerid' => $pl['steamAccountId'],
          'hero_id' => $pl['heroId']
        ];

        $item_id = $e['itemId'];
        if (!$item_id) continue;

        $items_all[$item_id] = $e['time'];

        // boots of travel workaround
        if ($item_id == 47 && $travel_boots_state == 0) { $item_id = 48; $travel_boots_state++; }
        if ($item_id == 48 && $travel_boots_state == 0) continue;
        if ($item_id == 219 && $travel_boots_state == 1) { $item_id = 220; $travel_boots_state++; }
        if ($item_id == 220 && $travel_boots_state == 1) continue;

        $category = "";

        foreach($meta['item_categories'] as $category_name => $items) {
          if (in_array($item_id, $items)) {
            $category = $category_name;
            break;
          }
        }

        // should I disable consumables?
        if (in_array($category, ['support', 'consumables', 'parts', 'recipes', 'event']) ) { //&& $e['time'] > 0) {
          continue;
        }

        $it['item_id'] = $item_id;
        $it['category_id'] = array_search($category, array_keys($meta['item_categories']));
        $it['time'] = $e['time'];

        $items[$item_id] = $e['time'];
        $items_cats[ $it['category_id'] ] = ($items_cats[ $it['category_id'] ] ?? 0) + 1;

        $r['items'][] = $it;
      }

      foreach($pl['stats']['matchPlayerBuffEvent'] as $e) {
        if (in_array($e['itemId'], [108, 271, 247, 609, 727, 725]) && !isset($items_all[ $e['itemId'] ])) {
          // rosh aghs
          if ($e['itemId'] == 725) $e['itemId'] = 609;
          if ($e['itemId'] == 727) $e['itemId'] = 271;
          
          $items_all[$item_id] = $e['time'];

          $r['items'][] = [
            'matchid' => $stratz['data']['match']['id'],
            'playerid' => $pl['steamAccountId'],
            'hero_id' => $pl['heroId'],
            'item_id' => $e['itemId'], 
            'category_id' => 0,
            'time' => $e['time']
          ];

          $items[ $e['itemId'] ] = $e['time'];
          $items_cats[ 0 ] = ($items_cats[ 0 ] ?? 0) + 1;
        }
      }

      asort($items_all);

      foreach($pl['stats']['inventoryReport'] as $t => $e) {
        $inventory = [];
        for($i = 0; $i < 6; $i++) {
          $inventory[] = $e['item'.$i] ? $e['item'.$i]['itemId'] : null;
        }
        for($i = 0; $i < 3; $i++) {
          $inventory[] = $e['backPack'.$i] ? $e['backPack'.$i]['itemId'] : null;
        }
        foreach($inventory as $item_id) {
          // rosh aghs
          if ($item_id == 725 || $item_id == 727)
            continue;
          // $item_id = 609;
          // if ($item_id == 727) $item_id = 271;

          $time = ($t-1)*60;

          // && abs($items_all[ $item_id ]-60) < 60)
          if (!$item_id || isset($items_all[ $item_id ]) )
            continue;

          foreach($meta['item_categories'] as $category_name => $items) {
            if (in_array($item_id, $items)) {
              $category = $category_name;
              break;
            }
          }

          $last = null;
          foreach ($items_all as $iid => $ita) {
            if (in_array($iid, $meta['item_categories']['consumables'])) continue;
            if ($ita < $time) $last = $ita;
            else break;
          }
          $time = $last && $time-$last < 30 ? $last : $time;

          $items_all[$item_id] = $time;

          if (in_array($category, ['support', 'consumables', 'parts', 'recipes', 'event']) || strpos($category, "neutral_tier_") !== FALSE ) { //&& $e['time'] > 0) {
            continue;
          }

          $category_id = array_search($category, array_keys($meta['item_categories']));

          $r['items'][] = [
            'matchid' => $stratz['data']['match']['id'],
            'playerid' => $pl['steamAccountId'],
            'hero_id' => $pl['heroId'],
            'item_id' => $item_id, 
            'category_id' => $category_id,
            'time' => $time
          ];

          $items[$item_id] = $time;
          $items_cats[ $category_id ] = ($items_cats[ $category_id ] ?? 0) + 1;
        }
      }

      $last = null; 
      //$neutrals = [];
      foreach($pl['stats']['inventoryReport'] as $i => $e) {
        if (!$e['neutral0'] || $e['neutral0']['itemId'] == $last) continue;
        $last = $e['neutral0']['itemId'];

        foreach($meta['item_categories'] as $category_name => $items) {
          if (in_array($last, $items)) {
            $category = $category_name;
            break;
          }
        }

        $r['items'][] = [
          'matchid' => $stratz['data']['match']['id'],
          'playerid' => $pl['steamAccountId'],
          'hero_id' => $pl['heroId'],
          'item_id' => $last, 
          'category_id' => array_search($category, array_keys($meta['item_categories'])),
          'time' => ($i-1)*60
        ];
      } 
    }
  }

  $r['draft'] = [];
  if (!empty($stratz['data']['match']['stats']['pickBans'])) {
    $stage = 0;
    $last_stage_pick = null;

    foreach ($stratz['data']['match']['stats']['pickBans'] as $dr) {
      $d = [];

      $d['matchid'] = $match;
      $d['is_radiant'] = $dr['isRadiant'] ? 1 : 0;
      $d['is_pick'] = $dr['isPick'] ? 1 : 0;
      $d['hero_id'] = $dr['isPick'] || !isset($dr['heroId']) ? $dr['heroId'] : $dr['bannedHeroId'];
      if (empty($d['hero_id'])) continue;

      if ($r['matches']['modeID'] == 2 || $r['matches']['modeID'] == 9) {
        $last_stage_pick = null;
        if ($last_stage_pick != $d['is_pick'] && !$d['is_pick']) {
          $stage++;
        }
        $last_stage_pick = $d['is_pick'];
        $d['stage'] = $stage;
      } else if ($r['matches']['modeID'] == 16) {
        if ($dr['isPick']) {
          if ($dr['order'] < 11) $d['stage'] = 1;
          else if ($dr['order'] < 15) $d['stage'] = 2;
          else $d['stage'] = 3;
        } else {
            $d['stage'] = 1;
        }
      } else if ($r['matches']['modeID'] == 22 || $r['matches']['modeID'] == 3) {
        if ($dr['isPick']) {
          if ($dr['order'] < 4) $d['stage'] = 1;
          else if ($dr['order'] < 8) $d['stage'] = 2;
          else $d['stage'] = 3;
        } else $d['stage'] = 1;
      } else {
        $d['stage'] = 1;
      }

      $r['draft'][] = $d;
    }
  } else {
    foreach($stratz['data']['match']['players'] as $draft_instance) {
      if (!isset($draft_instance['heroId']) || !$draft_instance['heroId'])
        continue;
      $d['matchid'] = $match;
      $d['is_radiant'] = $draft_instance['isRadiant'];
      $d['is_pick'] = 1;
      $d['hero_id'] = $draft_instance['heroId'];
      $d['stage'] = 1;
      
      $r['draft'][] = $d;
    }
  }

  if (!empty($stratz['data']['match']['radiantTeamId']) || !empty($stratz['data']['match']['direTeamId'])) {
    $r['teams_matches'] = [];
    $r['teams'] = [];

    if (!empty($stratz['data']['match']['direTeamId']) && $stratz['data']['match']['direTeamId'] > 0) {
      $r['teams_matches'][] = [
        'matchid' => $stratz['data']['match']['id'],
        'teamid' => $stratz['data']['match']['direTeamId'],
        'is_radiant' => 0
      ];

      $r['teams'][] = [
        'teamid' => $stratz['data']['match']['direTeamId'],
        'name' => $stratz['data']['match']['direTeam']['name'] ?? "Team ".$stratz['data']['match']['direTeamId'],
        'tag' => $stratz['data']['match']['direTeam']['tag'] ?? generate_tag($stratz['data']['match']['direTeam']['name'] ?? "Team ".$stratz['data']['match']['direTeamId']),
      ];
    }

    if (!empty($stratz['data']['match']['radiantTeamId']) && $stratz['data']['match']['radiantTeamId'] > 0) {
      $r['teams_matches'][] = [
        'matchid' => $stratz['data']['match']['id'],
        'teamid' => $stratz['data']['match']['radiantTeamId'],
        'is_radiant' => 1
      ];

      $r['teams'][] = [
        'teamid' => $stratz['data']['match']['radiantTeamId'],
        'name' => $stratz['data']['match']['radiantTeam']['name'] ?? "Team ".$stratz['data']['match']['radiantTeamId'],
        'tag' => $stratz['data']['match']['radiantTeam']['tag'] ?? generate_tag($stratz['data']['match']['radiantTeam']['name'] ?? "Team ".$stratz['data']['match']['radiantTeamId']),
      ];
    }
  }

  return $r;
}

function get_stratz_multiquery($group) {
  global $stratztoken, $meta, $stratz_cache, $api_cooldown_seconds;

  $gr = [];
  foreach ($group as $match) {
    if (empty($match) || $match[0] == "#" || strlen($match) < 2) continue;
    $match_rules = processRules($match);

    $gr[] = $match;
  }

  if (empty($gr)) return null;

  $data = [
    'query' => "{ matches(ids: [".implode(',', $gr)."]) ".STRATZ_GRAPHQL_QUERY."}"
  ];

  $data['query'] = str_replace("  ", "", $data['query']);
  $data['query'] = str_replace("\n", " ", $data['query']);

  if (!empty($stratztoken)) $data['key'] = $stratztoken;
    
  $stratz_request = "https://api.stratz.com/graphql";

  $q = http_build_query($data);
  
  $json = @file_get_contents($stratz_request.'?'.$q, false, stream_context_create([
    'ssl' => [
      'verify_peer' => false,
      'verify_peer_name' => false,
    ]
  ]));
  //$json = @file_get_contents($stratz_request.'?'.$q);
  
  if (empty($json)) return null;

  $stratz = json_decode($json, true);

  if (empty($stratz) || empty($stratz['data'])) return null;

  foreach ($stratz['data']['matches'] as $match) {
    if (empty($match)) continue;
    $stratz_cache[ $match['id'] ] = $match;
  }

  sleep($api_cooldown_seconds);

  return $stratz_cache;
}