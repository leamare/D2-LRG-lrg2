<?php 

include_once __DIR__.'/comebacks.php';
include_once __DIR__.'/skillPriority.php';
include_once __DIR__.'/match_ext.php';

const ROSHAN = [133, 134, 135, 263, 324, 325, 326, 371, 593, 594, 595, 640];
const TORMENTOR = [861, 890];
const OBS = [110, 499, 768];
const SENTRY = [500, 769, 111];
const LEVELS_RESPAWN = [5,7,9,13,16,26,28,30,32,34,36,44,46,48,50,52,54,65,70,75,80,85,90,95,100,100,100,100,100,100];

const STRATZ_NPC_OBJECTIVE_IDS = [
  // type -- CHAT_MESSAGE_FIRSTBLOOD
  // type -- CHAT_MESSAGE_MINIBOSS_KILL
  // type -- CHAT_MESSAGE_ROSHAN_KILL
  // type -- CHAT_MESSAGE_AEGIS
  // type -- CHAT_MESSAGE_COURIER_LOST

  // building_kill  
  16 => 'npc_dota_goodguys_tower1_top',
  17 => 'npc_dota_goodguys_tower1_mid',
  18 => 'npc_dota_goodguys_tower1_bot',
  19 => 'npc_dota_goodguys_tower2_top',
  20 => 'npc_dota_goodguys_tower2_mid',
  21 => 'npc_dota_goodguys_tower2_bot',
  22 => 'npc_dota_goodguys_tower3_top',
  23 => 'npc_dota_goodguys_tower3_mid',
  24 => 'npc_dota_goodguys_tower3_bot',
  25 => 'npc_dota_goodguys_tower4',
  26 => 'npc_dota_badguys_tower1_top',
  27 => 'npc_dota_badguys_tower1_mid',
  28 => 'npc_dota_badguys_tower1_bot',
  29 => 'npc_dota_badguys_tower2_top',
  30 => 'npc_dota_badguys_tower2_mid',
  31 => 'npc_dota_badguys_tower2_bot',
  32 => 'npc_dota_badguys_tower3_top',
  33 => 'npc_dota_badguys_tower3_mid',
  34 => 'npc_dota_badguys_tower3_bot',
  35 => 'npc_dota_badguys_tower4',
  36 => 'npc_dota_goodguys_fillers',
  37 => 'npc_dota_badguys_fillers',
  38 => 'npc_dota_goodguys_melee_rax_top',
  39 => 'npc_dota_goodguys_melee_rax_mid',
  40 => 'npc_dota_goodguys_melee_rax_bot',
  41 => 'npc_dota_goodguys_range_rax_top',
  42 => 'npc_dota_goodguys_range_rax_mid',
  43 => 'npc_dota_goodguys_range_rax_bot',
  44 => 'npc_dota_badguys_melee_rax_top',
  45 => 'npc_dota_badguys_melee_rax_mid',
  46 => 'npc_dota_badguys_melee_rax_bot',
  47 => 'npc_dota_badguys_range_rax_top',
  48 => 'npc_dota_badguys_range_rax_mid',
  49 => 'npc_dota_badguys_range_rax_bot',
  50 => 'npc_dota_goodguys_fort',
  51 => 'npc_dota_badguys_fort',
  254 => 'npc_dota_badguys_healers',
  255 => 'npc_dota_goodguys_healers',

  // others, not used for objectives, but might come in handy later
  822 => 'npc_dota_watch_tower',
  857 => 'npc_dota_mango_tree',
  864 => 'npc_dota_unit_twin_gate',
  865 => 'npc_dota_lantern',
  868 => 'npc_dota_unit_roshans_banner',
  887 => 'npc_dota_building_generic',
  890 => 'npc_dota_miniboss_minion',
];

const STRATZ_RUNE_CODES = [
  'DOUBLE_DAMAGE' => 0,
  'DOUBLEDAMAGE' => 0,
  'HASTE' => 1,
  'ILLUSION' => 2,
  'INVISIBILITY' => 3,
  'INVIS' => 3,
  'REGENERATION' => 4,
  'REGEN' => 4,
  'BOUNTY' => 5,
  'ARCANE' => 6,
  'WATER' => 7,
  'WISDOM' => 8,
  'XP' => 8,
  'SHIELD' => 9,
];

function stratz_npc_objective($npcId): ?array {
  $npc = STRATZ_NPC_OBJECTIVE_IDS[(int)$npcId] ?? null;
  if ($npc === null || $npc === '') return null;
  if (!preg_match('/npc_dota_(goodguys|badguys)_(.+)/i', $npc, $m)) return null;
  $kind = strtolower($m[2]);
  if ($kind === 'fort') return null;
  if ($kind === 'fillers') $kind = 'healers';
  return [
    'key' => 'building_' . $kind,
    'target_is_radiant' => strtolower($m[1]) === 'goodguys',
  ];
}

function stratz_trim_zero_delta($series): ?array {
  if (!is_array($series) || $series === []) return null;
  $out = array_values($series);
  $n = count($out);
  while ($n > 1 && (float)$out[$n - 1] == (float)$out[$n - 2]) {
    array_pop($out);
    $n--;
  }
  return $out;
}

function stratz_rune_code($name): ?int {
  if ($name === null || $name === '') return null;
  if (is_numeric($name)) return (int)$name;
  $key = strtoupper(str_replace([' ', '-'], '_', (string)$name));
  return STRATZ_RUNE_CODES[$key] ?? null;
}

function stratz_rune_is_pickup($action): bool {
  $a = strtoupper(trim((string)$action));
  if (strpos($a, 'BOTTLE') !== false || strpos($a, 'DENY') !== false) return false;
  if ($a === 'DROP' || $a === 'LOST' || $a === 'ACTIVATE') return false;
  return true;
}

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

const STRATZ_LEAVER_STATUS = [
  'NONE' => 0,
  'DISCONNECTED' => 1,
  'ABANDONED' => 2,
  'DISCONNECTED_TOO_LONG' => 2,
];

// Removed blocks
// farmDistributionReport {
//   creepType {
//     count
//     id
//   }
//   other {
//     count
//     id
//   }
// }
// wardDestruction {
//   isWard
//   time
// }

// match: 
// playbackData {
//   wardEvents {
//     action
//     fromPlayer
//     indexId
//     playerDestroyed
//     positionX
//     positionY
//     time
//     wardType
//   }
// }

const STRATZ_GRAPHQL_QUERY = "fragment MatchInfo on MatchType {
  clusterId
  gameMode
  gameVersionId
  statsDateTime
  startDateTime
  leagueId
  seriesId
  durationSeconds
  parsedDateTime
  sequenceNum
  replaySalt
  actualRank
  barracksStatusDire
  barracksStatusRadiant
  towerStatusDire
  towerStatusRadiant
  towerDeaths {
    attacker
    isRadiant
    npcId
    time
  }
  regionId
  lobbyType
  id
  isStats
  radiantNetworthLeads
  radiantExperienceLeads
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
  }
  league {
    name
  }
  numHumanPlayers
  didRadiantWin
  players {
    steamAccountId
    playerSlot
    heroId
    variant
    level
    isRadiant
    leaverStatus
    abilities {
      level
      time
      abilityId
    }
    stats {
      campStack
      heroDamageReceivedPerMinute
      heroDamageReport {
        receivedTotal {
          magicalDamage
          physicalDamage
          pureDamage
        }
        dealtTotal {
          stunDuration
          disableDuration
          magicalDamage
          physicalDamage
          pureDamage
          selfHeal
          allyHeal
          slowDuration
        }
      }
      runes {
        action
        gold
        rune
        time
      }
      allTalks {
        message
        time
        pausedTick
      }
      chatWheels {
        chatWheelId
        time
        pauseTick
      }
      farmDistributionReport {
        creepType {
          id
          count
        }
      }
      courierKills {
        time
      }
      lastHitsPerMinute
      networthPerMinute
      experiencePerMinute
      goldPerMinute
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
      inventoryReport {
        neutral0 {
          itemId
        }
        item5 {
          itemId
        }
        item4 {
          itemId
        }
        item3 {
          itemId
        }
        item2 {
          itemId
        }
        item1 {
          itemId
        }
        item0 {
          itemId
        }
      }
      matchPlayerBuffEvent {
        abilityId
        itemId
        stackCount
        time
      }
      actionReport {
        pingUsed
      }
      wards {
        positionX
        positionY
        time
        type
      }
      level
      wardDestruction {
        time
        isWard
      }
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
    position
    steamAccount {
      name
      seasonRank
      seasonLeaderboardDivisionId
      seasonLeaderboardRank
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
  bottomLaneOutcome
  topLaneOutcome
  midLaneOutcome
}";

function get_stratz_response($match) {
  global $stratztoken, $meta, $stratz_cache, $api_cooldown_seconds, $stratz_user_agent;

  if (isset($stratz_cache[ $match ])) {
    $stratz = [
      'data' => [
        'match' => $stratz_cache[ $match ]
      ]
    ];
  } else {
    $data = [
      'query' => "{ match(id: $match) { ...MatchInfo } }\n\n".STRATZ_GRAPHQL_QUERY
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
  
    $json = @file_get_contents($stratz_request.'?'.$q, false, stream_context_create([
      'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
      ],
      'http' => [
        'method' => 'POST',
        'header'  => "Content-Type: application/json\r\nKey: $stratztoken\r\nUser-Agent: $stratz_user_agent\r\n",
        'content' => json_encode($data),
        'timeout' => 60,
      ]
    ]));

    if (empty($json)) return null;
    
    $stratz = json_decode($json, true);
    
    if (!empty($stratz['errors'])) {
      throw new Exception(json_encode($stratz['errors'], JSON_PRETTY_PRINT));
    }

    if (empty($stratz['data']) || empty($stratz['data']['match'])) {
      return null;
    }
  }

  $slot_pids = [];

  $r = [];

  $r['matches'] = [];
  $r['matches']['matchid'] = $stratz['data']['match']['id'];
  $r['matches']['radiantWin'] = $stratz['data']['match']['didRadiantWin'];
  $r['matches']['duration'] = $stratz['data']['match']['durationSeconds'];
  $r['matches']['modeID'] = STRATZ_GAME_MODE[ $stratz['data']['match']['gameMode'] ] ?? $stratz['data']['match']['gameMode'];
  $r['matches']['cluster'] = $stratz['data']['match']['clusterId'];
  $r['matches']['seq_num'] = $stratz['data']['match']['sequenceNum'] ?? null;
  $r['matches']['avg_rank'] = $stratz['data']['match']['actualRank'] ?? null;
  $r['matches']['tower_status_radiant'] = $stratz['data']['match']['towerStatusRadiant'] ?? null;
  $r['matches']['tower_status_dire'] = $stratz['data']['match']['towerStatusDire'] ?? null;
  $r['matches']['barracks_status_radiant'] = $stratz['data']['match']['barracksStatusRadiant'] ?? null;
  $r['matches']['barracks_status_dire'] = $stratz['data']['match']['barracksStatusDire'] ?? null;
  $r['matches']['start_date'] = $stratz['data']['match']['startDateTime'];
  $r['matches']['leagueID'] = $stratz['data']['match']['leagueId'] ?? 0;
  $r['matches']['version'] = get_patchid($r['matches']['start_date'], $meta);

  $r['matches']['analysis_status'] = $stratz['data']['match']['parsedDateTime'] ? 1 : 0;
  $r['matches']['seriesid'] = $stratz['data']['match']['seriesId'] ?? null;
  
  if ($stratz['data']['match']['statsDateTime'] && !empty($stratz['data']['match']['radiantNetworthLeads'])) {
    [ $r['matches']['stomp'], $r['matches']['comeback'] ] = find_comebacks($stratz['data']['match']['radiantNetworthLeads'], $stratz['data']['match']['didRadiantWin']);
  } else {
    $r['matches']['stomp'] = 0;
    $r['matches']['comeback'] = 0;
  }

  $r['matches_ext'] = [
    'nw_t' => $stratz['data']['match']['radiantNetworthLeads'] ?? null,
    'gold_t' => null,
    'xp_t' => stratz_trim_zero_delta($stratz['data']['match']['radiantExperienceLeads'] ?? null),
    'teamfights' => null,
  ];

  $r['payload'] = [
    'score_radiant' => 0,
    'score_dire' => 0,
    'leavers' => 0
  ];

  $r['matchlines'] = [];
  $r['adv_matchlines'] = [];
  $r['items'] = [];
  $r['players'] = [];
  $r['skill_builds'] = [];
  $r['starting_items'] = [];
  $r['wards'] = [];
  $r['runes'] = [];
  $r['objectives'] = [];
  $chat_lines = [];
  $aegis_pickups = [];
  $match_id = (int)$stratz['data']['match']['id'];

  foreach ($stratz['data']['match']['players'] as $i => $pl) {
    $r['payload']['score_radiant'] += $pl['isRadiant'] ? $pl['kills'] : 0;
    $r['payload']['score_dire'] += !$pl['isRadiant'] ? $pl['kills'] : 0;

    if (!is_numeric($pl['leaverStatus'])) {
      $pl['leaverStatus'] = STRATZ_LEAVER_STATUS[ $pl['leaverStatus'] ] ?? 0;
    }
    if ($pl['leaverStatus'] > 1) $r['payload']['leavers']++;

    $slot_pids[ $pl['playerSlot'] ] = $pl['steamAccountId'];

    $ml = [];
    $ml['matchid'] = $stratz['data']['match']['id'];
    $ml['playerid'] = $pl['steamAccountId'];
    $ml['seasonRank'] = $pl['steamAccount']['seasonRank'];
    $ml['heroid'] = $pl['heroId'];
    $ml['variant'] = $pl['variant'];
    $ml['isRadiant'] = $pl['isRadiant'];
    $ml['player_slot'] = $pl['playerSlot'] ?? null;
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

    foreach ($pl['stats']['runes'] ?? [] as $rn) {
      if (!stratz_rune_is_pickup($rn['action'] ?? '')) continue;
      $code = stratz_rune_code($rn['rune'] ?? null);
      if ($code === null) continue;
      $timing = (int)($rn['time'] ?? 0);
      $r['runes'][] = [
        'matchid' => $match_id,
        'playerid' => (int)$pl['steamAccountId'],
        'rune_code' => $code,
        'timing' => $timing,
      ];
      if ($code === 8) {
        $r['objectives'][] = lrg_objective_row(
          $match_id,
          'rune_wisdom_shrine',
          $timing,
          $pl['steamAccountId'],
          empty($pl['isRadiant'])
        );
      }
    }

    foreach ($pl['stats']['allTalks'] ?? [] as $talk) {
      $msg = (string)($talk['message'] ?? '');
      if ($msg === '') continue;
      $chat_lines[] = [
        'type' => 'chat',
        'key' => $msg,
        'time' => (int)($talk['time'] ?? 0),
        'playerid' => (int)$pl['steamAccountId'],
        'player_slot' => $pl['playerSlot'] ?? null,
      ];
    }
    foreach ($pl['stats']['chatWheels'] ?? [] as $cw) {
      if (!isset($cw['chatWheelId']) || $cw['chatWheelId'] === '' || $cw['chatWheelId'] === null) continue;
      $chat_lines[] = [
        'type' => 'chatwheel',
        'key' => (string)$cw['chatWheelId'],
        'time' => (int)($cw['time'] ?? 0),
        'playerid' => (int)$pl['steamAccountId'],
        'player_slot' => $pl['playerSlot'] ?? null,
      ];
    }

    foreach ($pl['stats']['courierKills'] ?? [] as $ck) {
      $r['objectives'][] = lrg_objective_row(
        $match_id,
        'unit_courier_kill',
        (int)($ck['time'] ?? 0),
        $pl['steamAccountId'],
        empty($pl['isRadiant'])
      );
    }

    $pickup_times = [];
    $buff117 = [];
    foreach ($pl['stats']['matchPlayerBuffEvent'] ?? [] as $e) {
      if ((int)($e['itemId'] ?? 0) === 117) $buff117[] = (int)$e['time'];
    }
    sort($buff117);
    $has_aegis = false;
    foreach ($pl['stats']['inventoryReport'] ?? [] as $k => $rep) {
      $ids = [];
      foreach ($rep as $it) {
        if (!empty($it['itemId'])) $ids[] = (int)$it['itemId'];
      }
      $now = in_array(117, $ids, true);
      if ($now && !$has_aegis) {
        $t = ((int)$k > 120) ? (int)$k : (int)$k * 60;
        foreach ($buff117 as $b) {
          if ($b >= $t - 90 && $b <= $t + 90) { $t = $b; break; }
        }
        $pickup_times[] = $t;
      }
      $has_aegis = $now;
    }
    if ($pickup_times === [] && $buff117 !== []) {
      $last_buff = -9999;
      foreach ($buff117 as $b) {
        if ($b - $last_buff > 330) $pickup_times[] = $b;
        $last_buff = $b;
      }
    }
    foreach ($pickup_times as $t) {
      $aegis_pickups[] = [
        'time' => $t,
        'playerid' => (int)$pl['steamAccountId'],
        'isRadiant' => !empty($pl['isRadiant']),
      ];
    }

    if ($stratz['data']['match']['statsDateTime'] && !empty($pl['stats']['lastHitsPerMinute'])) {
      $aml = [];

      $aml['matchid'] = $stratz['data']['match']['id'];
      $aml['playerid'] = $pl['steamAccountId'];
      $aml['heroid'] = $pl['heroId'];

      $lm = $r['matches']['modeID'] == 23 ? 5 : 10;
      $aml['lh_at10'] = array_sum(
        array_slice($pl['stats']['lastHitsPerMinute'], 0, $lm)
      );
      
      $aml['lane'] = is_numeric($pl['lane'])
        ? ( ($pl['lane'] > 3 || !$pl['lane']) ? 4 : $pl['lane'] )
        : STRATZ_LANE_TYPE[$pl['lane']];

      if ($aml['lane'] == 4 || !$aml['lane']) $aml['isCore'] = 0;
      else $aml['isCore'] = (is_numeric($pl['roleBasic']) ? $pl['roleBasic'] : $pl['roleBasic'] !== 'CORE') ? 0 : 1;

      $aml['role'] = (int)str_replace("POSITION_", "", $pl['position']);

      if (($aml['lane'] == 1 && $ml['isRadiant']) || ($aml['lane'] == 3 && !$ml['isRadiant'])) {
        // bottom lane
        $aml['lane_won'] = $stratz['data']['match']['bottomLaneOutcome'] == "TIE" ? 1 : (
          $stratz['data']['match']['bottomLaneOutcome'] == "RADIANT_VICTORY" ? ($ml['isRadiant'] ? 2 : 0) : ($ml['isRadiant'] ? 0 : 2)
        );
      } else if ($aml['lane'] == 2) {
        $aml['lane_won'] = $stratz['data']['match']['midLaneOutcome'] == "TIE" ? 1 : (
          $stratz['data']['match']['midLaneOutcome'] == "RADIANT_VICTORY" ? ($ml['isRadiant'] ? 2 : 0) : ($ml['isRadiant'] ? 0 : 2)
        );
      } else {
        // top lane
        $aml['lane_won'] = $stratz['data']['match']['topLaneOutcome'] == "TIE" ? 1 : (
          $stratz['data']['match']['topLaneOutcome'] == "RADIANT_VICTORY" ? ($ml['isRadiant'] ? 2 : 0) : ($ml['isRadiant'] ? 0 : 2)
        );
      }
      
      $melee = (40 * (60 + 8));
      $ranged = (45 * 20);
      $siege = (74 * 2);
      $passive = (600 * 1.275);
      $starting = 625;
      $tenMinute = $starting + $lm * ($melee + $ranged + $siege + $passive)/10;
      $aml['efficiency_at10'] = (
        count($pl['stats']['networthPerMinute']) > ($lm-1) ? 
        $pl['stats']['networthPerMinute'][$lm-1] : 
        end($pl['stats']['networthPerMinute'])
      ) / $tenMinute;

      $aml['nw_t'] = $pl['stats']['networthPerMinute'] ?? null;
      $aml['gold_t'] = null;
      if (!empty($pl['stats']['goldPerMinute']) && is_array($pl['stats']['goldPerMinute'])) {
        $aml['gold_t'] = [];
        foreach (array_values($pl['stats']['goldPerMinute']) as $gi => $gv) {
          $aml['gold_t'][] = (int)round((float)$gv * ($gi + 1));
        }
      }
      $aml['lh_t'] = lrg_prefix_sum_series($pl['stats']['lastHitsPerMinute'] ?? null);
      $aml['xp_t'] = stratz_trim_zero_delta($pl['stats']['experiencePerMinute'] ?? null);
      $recv = $pl['stats']['heroDamageReport']['receivedTotal'] ?? [];
      $deal = $pl['stats']['heroDamageReport']['dealtTotal'] ?? [];
      if ($recv !== [] || $deal !== []) {
        $aml['damage_breakdown'] = [
          'r' => [
            'm' => (int)($recv['magicalDamage'] ?? 0),
            'ph' => (int)($recv['physicalDamage'] ?? 0),
            'p' => (int)($recv['pureDamage'] ?? 0),
          ],
          'd' => [
            'm' => (int)($deal['magicalDamage'] ?? 0),
            'ph' => (int)($deal['physicalDamage'] ?? 0),
            'p' => (int)($deal['pureDamage'] ?? 0),
          ],
          'cc' => [
            'ss' => (int)($deal['stunDuration'] ?? 0),
            'sls' => (int)($deal['slowDuration'] ?? 0),
            'd' => (int)($deal['disableDuration'] ?? 0),
          ],
          'h' => [
            'sh' => (int)($deal['selfHeal'] ?? 0),
            'ah' => (int)($deal['allyHeal'] ?? 0),
          ],
        ];
      }
      
      if (!empty($pl['stats']['wards'])) {
        // only includes wards placed
        $aml['wards'] = count(
          array_filter($pl['stats']['wards'], function($a) { return $a['type'] == 0; })
        );
        $aml['sentries'] = count(
          array_filter($pl['stats']['wards'], function($a) { return $a['type'] == 1; })
        );
      } else {
        $aml['wards'] = count(
          array_filter($pl['stats']['itemPurchases'], function($a) { return $a['itemId'] == 42; })
        );
        $aml['sentries'] = count(
          array_filter($pl['stats']['itemPurchases'], function($a) { return $a['itemId'] == 43; })
        );
      }

      $aml['couriers_killed'] = count($pl['stats']['courierKills'] ?? []);

      $aml['roshans_killed'] = 0;
      $aml['tormentors_killed'] = 0;
      $aml['wards_destroyed'] = count($pl['stats']['wardDestruction'] ?? []);

      if (isset($pl['stats']['farmDistributionReport'])) {
        foreach ($pl['stats']['farmDistributionReport']['creepType'] ?? [] as $fc) {
          // if (in_array($fc['id'], OBS)) $aml['wards_destroyed'] += $fc['count'];
          if (in_array($fc['id'], ROSHAN)) $aml['roshans_killed'] += $fc['count'];
          if (in_array($fc['id'], TORMENTOR)) $aml['tormentors_killed'] += $fc['count'];
        }
        // foreach ($f['other'] as $fc) {
        //   if (in_array($fc['id'], ROSHAN)) $aml['roshans_killed'] += $fc['count'];
        //   if (in_array($fc['id'], OBS)) $aml['wards_destroyed'] += $fc['count'];
        // }
      } else {
        $hasAegis = false;
        foreach ($pl['stats']['inventoryReport'] as $time => $rep) {
          foreach ($rep as $slot => $item) {
            if (empty($item)) continue;
            if ($item['itemId'] == 117 && !$hasAegis) {
              $aml['roshans_killed']++;
              $hasAegis = true;
            } elseif ($hasAegis && !in_array(117, array_column($rep, 'itemId'))) {
              $hasAegis = false;
            }
          }
        }
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
      
      // limiting Stratz stuns values only to stunDuration
      // god only knows what counts as stuns and what doesn't
      // $aml['stuns'] = (($pl['stats']['heroDamageReport']['dealtTotal']['stunDuration'] ?? 0) + ($pl['stats']['heroDamageReport']['dealtTotal']['disableDuration'] ?? 0))/100;
      $aml['stuns'] = (($pl['stats']['heroDamageReport']['dealtTotal']['stunDuration'] ?? 0))/100;

      $aml['teamfight_part'] = $pl['isRadiant'] ? array_sum($stratz['data']['match']['radiantKills'] ?? []) : array_sum($stratz['data']['match']['direKills'] ?? []);
      $aml['teamfight_part'] = $aml['teamfight_part'] ? ($pl['kills']+$pl['assists']) / $aml['teamfight_part'] : 0;
      // $aml['damage_taken'] = array_sum($pl['stats']['heroDamageReceivedPerMinute'] ?? []);
      $aml['damage_taken'] = max($pl['stats']['heroDamageReceivedPerMinute'] ?? [0]);

      $r['adv_matchlines'][] = $aml;

      $skillbuild = [];
      foreach ($pl['abilities'] as $e) {
        $skillbuild[] = $e['abilityId'];
      }

      if (!empty($skillbuild)) {
        $sti = skillPriority($skillbuild, $pl['heroId'], $pl['heroId'] == 74);
        $r['skill_builds'][] = [
          'matchid' => $stratz['data']['match']['id'],
          'playerid' => $pl['steamAccountId'],
          'hero_id' => $pl['heroId'],
          'skill_build' => addslashes(\json_encode($skillbuild)),
          'first_point_at' => addslashes(\json_encode($sti['firstPointAt'])),
          'maxed_at' => addslashes(\json_encode($sti['maxedAt'])),
          'priority' => addslashes(\json_encode($sti['priority'])),
          'talents' => addslashes(\json_encode($sti['talents'])),
          'attributes' => addslashes(\json_encode($sti['attributes'])),
          'ultimate' => $sti['ultimate'],
        ];
      }

      $meta['items'];
      $meta['item_categories'];
      $travel_boots_state = 0;

      $items = [];
      $items_all = [];
      $items_cats  = [];
      $items_starting = [];
      $consumables = [
        'all' => [],
        '5m' => [],
        '10m' => [],
      ];

      foreach ($pl['stats']['itemPurchases'] as $e) {
        if ($r['matches']['duration'] - $e['time'] < 60) continue;

        if ($e['time'] < -20) {
          // Startz Starting items seem to be broken
          // so I'll be skipping them here and only recording the
          // minute 0 inventory snapshot

          // $items_starting[] = $e['itemId'];
          continue;
        }

        if (in_array($e['itemId'], $meta['item_categories']['consumables'])) {
          if (!isset($consumables['all'][ $e['itemId'] ])) {
            $consumables['all'][ $e['itemId'] ] = 0;
          }
          $consumables['all'][ $e['itemId'] ]++;
  
          if ($e['time'] < 600) {
            if (!isset($consumables['10m'][ $e['itemId'] ])) {
              $consumables['10m'][ $e['itemId'] ] = 0;
            }
            $consumables['10m'][ $e['itemId'] ]++;
          }
  
          if ($e['time'] < 300) {
            if (!isset($consumables['5m'][ $e['itemId'] ])) {
              $consumables['5m'][ $e['itemId'] ] = 0;
            }
            $consumables['5m'][ $e['itemId'] ]++;
          }
        }

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

      // there should be inventory report for all item slots as additional means of recording some items
      // but it's gone now, RIP
      foreach($pl['stats']['inventoryReport'] as $t => $e) {
        $inventory = [];
        for($i = 0; $i < 6; $i++) {
          $inventory[] = $e['item'.$i] ? $e['item'.$i]['itemId'] : null;
        }
        // for($i = 0; $i < 3; $i++) {
        //   $inventory[] = $e['backPack'.$i] ? $e['backPack'.$i]['itemId'] : null;
        // }

        if (!$t) {
          // Startz Starting items seem to be broken
          // so I'll be skipping them here and only recording the
          // minute 0 inventory snapshot
          $items_starting = $inventory;
        }

        foreach($inventory as $item_id) {
          // rosh aghs
          if ($item_id == 725 || $item_id == 727)
            continue;
          // $item_id = 609;
          // if ($item_id == 727) $item_id = 271;

          $time = $t ? ($t-1)*60 : -80;

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

      $r['starting_items'][] = [
        'matchid' => $stratz['data']['match']['id'],
        'playerid' => $pl['steamAccountId'],
        'hero_id' => $pl['heroId'],
        'starting_items' => addslashes(\json_encode($items_starting)),
        'consumables' => addslashes(\json_encode($consumables)),
      ];

      $last = null; 
      $neutrals = [];
      foreach($pl['stats']['inventoryReport'] as $i => $e) {
        if (!$e['neutral0'] || $e['neutral0']['itemId'] == $last) continue;
        $last = $e['neutral0']['itemId'];
        if (in_array($last, $neutrals)) {
          continue;
        }

        foreach($meta['item_categories'] as $category_name => $items) {
          if (in_array($last, $items)) {
            $category = $category_name;
            break;
          }
        }

        if (in_array($category, ['support', 'consumables', 'parts', 'recipes', 'event'])) {
          continue;
        }

        $r['items'][] = [
          'matchid' => $stratz['data']['match']['id'],
          'playerid' => $pl['steamAccountId'],
          'hero_id' => $pl['heroId'],
          'item_id' => $last, 
          'category_id' => array_search($category, array_keys($meta['item_categories'])),
          'time' => ($i-1)*60
        ];
        $neutrals[] = $last;
      } 
    }
  }

  $gold_adv = null;
  foreach ($r['adv_matchlines'] as $i => $aml) {
    $gold = $aml['gold_t'] ?? null;
    if (!is_array($gold) || $gold === []) continue;
    if ($gold_adv === null) $gold_adv = [];
    $rad = !empty($r['matchlines'][$i]['isRadiant']);
    foreach (array_values($gold) as $m => $g) {
      if (!isset($gold_adv[$m])) $gold_adv[$m] = 0;
      $gold_adv[$m] += $rad ? (int)$g : -(int)$g;
    }
  }
  $r['matches_ext']['gold_t'] = $gold_adv;

  $fb = null;
  foreach ($stratz['data']['match']['players'] as $pl) {
    foreach ($pl['stats']['killEvents'] ?? [] as $ke) {
      $t = (int)($ke['time'] ?? 0);
      if ($fb === null || $t < $fb['time']) {
        $fb = [
          'time' => $t,
          'playerid' => (int)$pl['steamAccountId'],
          'isRadiant' => !empty($pl['isRadiant']),
        ];
      }
    }
  }
  if ($fb !== null) {
    $r['objectives'][] = lrg_objective_row(
      $match_id,
      'hero_first_blood',
      $fb['time'],
      $fb['playerid'],
      empty($fb['isRadiant'])
    );
  }

  $t4_count = [1 => 0, 2 => 0];
  foreach ($stratz['data']['match']['towerDeaths'] ?? [] as $td) {
    $npc = stratz_npc_objective($td['npcId'] ?? 0);
    if ($npc === null) continue;
    $key = $npc['key'];
    $target_rad = $npc['target_is_radiant'];
    $t4 = 1;
    if ($key === 'building_tower4') {
      $side = $target_rad ? 1 : 2;
      $t4_count[$side]++;
      $t4 = $t4_count[$side] ?: 1;
    }
    $attacker = $td['attacker'] ?? null;
    $killer = null;
    if ($attacker !== null && $attacker !== '' && (int)$attacker !== -1) {
      $attacker = (int)$attacker;
      if (isset($slot_pids[$attacker])) {
        $killer = $slot_pids[$attacker];
      } else {
        foreach ($stratz['data']['match']['players'] as $apl) {
          if ((int)($apl['playerSlot'] ?? -999) === $attacker || (int)$apl['steamAccountId'] === $attacker) {
            $killer = $apl['steamAccountId'];
            break;
          }
        }
      }
    }
    $r['objectives'][] = lrg_objective_row($match_id, $key, (int)($td['time'] ?? 0), $killer, $target_rad, $t4);
  }

  usort($aegis_pickups, fn($a, $b) => $a['time'] <=> $b['time']);
  $last_rosh = -9999;
  foreach ($aegis_pickups as $ap) {
    $is_steal = $last_rosh >= 0 && ($ap['time'] - $last_rosh) <= 180;
    $target = !empty($ap['isRadiant']) ? 0 : 1;
    if (!$is_steal) {
      $r['objectives'][] = lrg_objective_row($match_id, 'unit_roshan_kill', $ap['time'], $ap['playerid'], $target);
      $last_rosh = $ap['time'];
    }
    $r['objectives'][] = lrg_objective_row(
      $match_id,
      $is_steal ? 'unit_roshan_aegis_stolen' : 'unit_roshan_aegis_pickup',
      $ap['time'],
      $ap['playerid'],
      $target
    );
  }

  usort($r['objectives'], fn($a, $b) => $a['timing'] <=> $b['timing']);
  $r['chat_report'] = lrg_extract_chat_report($match_id, [
    'chat' => $chat_lines,
    'matchlines' => $r['matchlines'],
    'players' => $r['players'],
  ], $r['matchlines']);

  // type 0 is obs
  // currently lacks information about ward killer
  if (!empty($stratz['data']['match']['playbackData']) && isset($stratz['data']['match']['playbackData']['wardEvents'])) {
    $wards_log = [];
    $sentries_log = [];
    $wards_destruction_log = [];

    foreach ($stratz['data']['match']['playbackData']['wardEvents'] as $ward) {
      $pid = $slot_pids[ $ward['fromPlayer'] ];

      switch ($ward['action'].'.'.$ward['wardType']) {
        case "SPAWN.OBSERVER":
        case "SPAWN.WARD":
          if (!isset($wards_log[$pid])) $wards_log[$pid] = [];
          if (isset($wards_log[ $pid ][ $ward['indexId'] ])) break;

          $wards_log[ $pid ][ $ward['indexId'] ] = [
            'x_c' => $ward['positionX'],
            'y_c' => $ward['positionY'],
            'time' => $ward['time'],
            'alive' => 600,
            // 'owner' => $pid, 
            'destroyed_at' => null,
            'destroyed_by' => null,
          ];
          break;
        case "SPAWN.SENTRY":
          if (!isset($sentries_log[$pid])) $sentries_log[$pid] = [];
          if (isset($sentries_log[ $pid ][ $ward['indexId'] ])) break;

          $sentries_log[ $pid ][ $ward['indexId'] ] = [
            'x_c' => $ward['positionX'],
            'y_c' => $ward['positionY'],
            'time' => $ward['time'],
          ];
          break;
        case "DESPAWN.WARD":
          if ($wards_log[ $pid ][ $ward['indexId'] ]['destroyed_at'] !== null) {
            break;
          }

          $wards_log[ $pid ][ $ward['indexId'] ]['destroyed_at'] = $ward['time'];
          $wards_log[ $pid ][ $ward['indexId'] ]['destroyed_by'] = $ward['playerDestroyed'] ? $slot_pids[ $ward['playerDestroyed'] ] : null;
          $wards_log[ $pid ][ $ward['indexId'] ]['alive'] = $ward['time'] - $wards_log[ $pid ][ $ward['indexId'] ]['time'];

          if ($ward['playerDestroyed']) {
            $d_pid = $slot_pids[ $ward['playerDestroyed'] ];

            $wards_destruction_log[ $d_pid ][ $ward['indexId'] ] = [
              'x_c' => $ward['positionX'],
              'y_c' => $ward['positionY'],
              'time' => $ward['time'],
            ];
          }
          break;
      }
    }

    foreach ($stratz['data']['match']['players'] as $pl) {
      $r['wards'][] = [
        'matchid' => $match,
        'playerid' => $pl['steamAccountId'],
        'heroid' => $pl['heroId'],
        'wards_log' => addslashes(\json_encode($wards_log[ $pl['steamAccountId'] ] ?? [])),
        'sentries_log' => addslashes(\json_encode($sentries_log[ $pl['steamAccountId'] ] ?? [])),
        'destroyed_log' => addslashes(\json_encode($wards_destruction_log[ $pl['steamAccountId'] ] ?? [])),
      ];
    }
  } else {
    foreach ($stratz['data']['match']['players'] as $pl) {
      if (empty($pl['stats']['wards'])) continue;
      $wards_log = [];
      $sentries_log = [];
      $wards_destruction_log = [];
      foreach($pl['stats']['wards'] as $ward) {
        if ($ward['type'] == 0) {
          $wards_log[] = [
            'x_c' => $ward['positionX'],
            'y_c' => $ward['positionY'],
            'time' => $ward['time'],
            'alive' => 600, //TODO:
            'destroyed_at' => null,
            'destroyed_by' => null,
          ];
        } else {
          $sentries_log[] = [
            'x_c' => $ward['positionX'],
            'y_c' => $ward['positionY'],
            'time' => $ward['time'],
          ];
        }
      }
      // foreach($pl['stats']['wardDestruction'] as $ward) {
      //   $wards_destruction_log[] = [
  
      //   ]
      // }
      $r['wards'][] = [
        'matchid' => $match,
        'playerid' => $pl['steamAccountId'],
        'heroid' => $pl['heroId'],
        'wards_log' => addslashes(\json_encode($wards_log)),
        'sentries_log' => addslashes(\json_encode($sentries_log)),
        'destroyed_log' => addslashes(\json_encode($wards_destruction_log)),
      ];
    }
  }

  $r['draft'] = [];
  if ($r['matches']['modeID'] == 18) {
    foreach ($stratz['data']['match']['players'] as $draft_instance) {
      if (empty($draft_instance['heroId'])) {
        continue;
      }
      $r['draft'][] = [
        'matchid' => $match,
        'is_radiant' => $draft_instance['isRadiant'] ? 1 : 0,
        'is_pick' => 1,
        'hero_id' => (int)$draft_instance['heroId'],
        'stage' => 1,
        'order' => 0,
      ];
    }
    $r['matches']['radiant_opener'] = $r['draft'][0]['is_radiant'] ?? null;
  } else if (!empty($stratz['data']['match']['pickBans'])) {
    $stage = 0;
    $last_stage_pick = null;

    foreach ($stratz['data']['match']['pickBans'] as $dr) {
      $d = [];

      $d['matchid'] = $match;
      $d['is_radiant'] = $dr['isRadiant'] ? 1 : 0;
      $d['is_pick'] = $dr['isPick'] ? 1 : 0;
      $d['hero_id'] = $dr['isPick'] || !isset($dr['heroId']) ? $dr['heroId'] : $dr['bannedHeroId'];
      if (empty($d['hero_id'])) continue;

      if ($r['matches']['modeID'] == 2 || $r['matches']['modeID'] == 8) {
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

      $d['order'] = $dr['order'] ?? 0;

      $r['draft'][] = $d;
    }

    $r['matches']['radiant_opener'] = $r['draft'][0]['is_radiant'];
  } else {
    foreach($stratz['data']['match']['players'] as $draft_instance) {
      if (!isset($draft_instance['heroId']) || !$draft_instance['heroId'])
        continue;
      $d['matchid'] = $match;
      $d['is_radiant'] = $draft_instance['isRadiant'];
      $d['is_pick'] = 1;
      $d['hero_id'] = $draft_instance['heroId'];
      $d['stage'] = 1;
      $d['order'] = 0;
      
      $r['draft'][] = $d;
    }

    $r['matches']['radiant_opener'] = null;
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
  global $stratztoken, $meta, $stratz_cache, $api_cooldown_seconds, $stratz_user_agent;

  $gr = [];
  foreach ($group as $match) {
    if (empty($match) || $match[0] == "#" || strlen($match) < 2) continue;
    $match_rules = processRules($match);

    $gr[] = $match;
  }

  if (empty($gr)) return null;

  $data = [
    'query' => "{
      ".implode(',', array_map(function($m, $i) {
        return "BASE_MATCH_INFO_$i: match(id: $m) {
          ...MatchInfo
        }\n";
      }, $gr, array_keys($gr)))."
    }\n\n".STRATZ_GRAPHQL_QUERY
  ];

  $data['query'] = str_replace("  ", "", $data['query']);
  $data['query'] = str_replace("\n", " ", $data['query']);

  if (!empty($stratztoken)) $data['key'] = $stratztoken;
    
  $stratz_request = "https://api.stratz.com/graphql";

  $json = @file_get_contents($stratz_request, false, stream_context_create([
    'ssl' => [
      'verify_peer' => false,
      'verify_peer_name' => false,
    ],
    'http' => [
      'method' => 'POST',
      'header'  => "Content-Type: application/json\r\nKey: $stratztoken\r\nUser-Agent: $stratz_user_agent\r\n",
      'content' => json_encode($data),
      'timeout' => 60,
    ]
  ]));

  // $json = @file_get_contents($stratz_request.'?'.$q);

  // var_dump($json);
  
  if (empty($json)) {
    return null;
  }

  $stratz = json_decode($json, true);

  $empty_data = 0;
  foreach ($stratz['data'] as $d) {
    if (empty($d)) {
      $empty_data++;
    }
  }


  if (!empty($stratz['errors'])) {
    echo "ERROR: ".json_encode($stratz['errors']);
    if ($empty_data <= 1) return null;

    // throw new \Exception(json_encode($stratz['errors'], JSON_PRETTY_PRINT));
  }

  if (empty($stratz) || empty($stratz['data'])) return null;

  foreach ($stratz['data'] as $match) {
    if (empty($match)) continue;
    $stratz_cache[ $match['id'] ] = $match;
  }

  sleep($api_cooldown_seconds);

  return $stratz_cache;
}