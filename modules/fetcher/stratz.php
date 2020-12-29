<?php 

const ROSHAN = [133, 134, 135, 263, 324, 325, 326, 371, 593, 594, 595, 640];
const OBS = [110, 499, 768];
const SENTRY = [500, 769, 111];
const LEVELS_RESPAWN = [5,7,9,13,16,26,28,30,32,34,36,44,46,48,50,52,54,65,70,75,80,85,90,95,100,100,100,100,100,100];

function get_stratz_response($match) {
  global $stratztoken, $meta;

  $data = [
    'query' => <<<Q
{ match(id: $match) {
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
  }
}
Q
  ];
  $data['query'] = str_replace("  ", "", $data['query']);
  $data['query'] = str_replace("\n", " ", $data['query']);

  if (!empty($stratztoken)) $data['token'] = $stratztoken;
    
  $stratz_request = "https://api.stratz.com/graphql";

  $q = http_build_query($data);
    
  // $context  = stream_context_create([
  //   'https' => [
  //     'method' => 'POST',
  //     'header'  => 'Content-Type: application/x-www-form-urlencoded'. 
  //       "\r\ncontent-length: ".strlen($q)."\r\ncontent-type: application/json",
  //     'content' => $q
  //   ]
  // ]);

  // $json = file_get_contents($stratz_request, false, $context);
  $json = @file_get_contents($stratz_request.'?'.$q);
  
  if (empty($json)) return null;

  $stratz = json_decode($json, true);
  
  if (empty($stratz['data']) && !empty($stratz['errors'])) {
    return null;
  }

  $r = [];

  $r['matches'] = [];
  $r['matches']['matchid'] = $stratz['data']['match']['id'];
  $r['matches']['radiantWin'] = $stratz['data']['match']['didRadiantWin'];
  $r['matches']['duration'] = $stratz['data']['match']['durationSeconds'];
  $r['matches']['modeID'] = $stratz['data']['match']['gameMode'];
  $r['matches']['cluster'] = $stratz['data']['match']['clusterId'];
  $r['matches']['start_date'] = $stratz['data']['match']['startDateTime'];
  $r['matches']['leagueID'] = $stratz['data']['match']['leagueId'] ?? 0;
  $r['matches']['version'] = get_patchid($r['matches']['start_date'], convert_patch_id($r['matches']['start_date']), $meta);

  if ($stratz['data']['match']['statsDateTime']) {
    $throwVal = $stratz['data']['match']['didRadiantWin'] ? max($stratz['data']['match']['stats']['radiantNetworthLeads']) : min($stratz['data']['match']['stats']['radiantNetworthLeads']) * -1;
    $comebackVal = $stratz['data']['match']['didRadiantWin'] ? min($stratz['data']['match']['stats']['radiantNetworthLeads']) * -1 : max($stratz['data']['match']['stats']['radiantNetworthLeads']);
  
    $r['matches']['stomp'] = $stratz['data']['match']['didRadiantWin'] ? $throwVal : $comebackVal;
    $r['matches']['comeback'] = $stratz['data']['match']['didRadiantWin'] ? $comebackVal : $throwVal;
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
    $r['payload']['leavers'] += $pl['leaverStatus'] > 1 ? 1 : 0;

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
      
      $aml['lane'] = ($pl['lane'] > 3 || !$pl['lane']) ? 4 : $pl['lane'];

      if ($aml['lane'] == 4) $aml['isCore'] = 0;
      else $aml['isCore'] = $pl['roleBasic'] ? 0 : 1;
      
      $melee = (40 * 60);
      $ranged = (45 * 20);
      $siege = (74 * 2);
      $passive = (600 * 1.5);
      $starting = 625;
      $tenMinute = $melee + $ranged + $siege + $passive + $starting;
      $aml['efficiency_at10'] = $pl['stats']['networthPerMinute'][10] / $tenMinute;
      
      $aml['wards'] = count(
        array_filter($pl['stats']['wards'], function($a) { return !$a['type']; })
      );
      $aml['sentries'] = count(
        array_filter($pl['stats']['wards'], function($a) { return $a['type']; })
      );

      $aml['couriers_killed'] = count($pl['stats']['courierKills']);

      $aml['roshans_killed'] = 0;
      $aml['wards_destroyed'] = 0;

      foreach ($pl['stats']['farmDistributionReport'] as $f) {
        foreach ($f['creepType'] as $fc) {
          if (in_array($fc['id'], OBS)) $aml['wards_destroyed'] += $fc['count'];
          if (in_array($fc['id'], ROSHAN)) $aml['roshans_killed'] += $fc['count'];
        }
        foreach ($f['other'] as $fc) {
          if (in_array($fc['id'], ROSHAN)) $aml['roshans_killed'] += $fc['count'];
          if (in_array($fc['id'], OBS)) $aml['wards_destroyed'] += $fc['count'];
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
      
      $aml['stacks'] = max($pl['stats']['campStack']);
      
      $aml['time_dead'] = array_reduce($pl['stats']['deathEvents'], function($c, $a) { return $c + $a['timeDead']; }, 0);
      $aml['pings'] = $pl['stats']['actionReport']['pingUsed'] ?? 0;
      
      $aml['stuns'] = ($pl['stats']['heroDamageReport']['dealtTotal']['stunDuration'] + $pl['stats']['heroDamageReport']['dealtTotal']['disableDuration'])/100;

      $aml['teamfight_part'] = ($pl['kills']+$pl['assists']) / ( $pl['isRadiant'] ? array_sum($stratz['data']['match']['stats']['radiantKills']) : array_sum($stratz['data']['match']['stats']['direKills']));
      $aml['damage_taken'] = array_sum($pl['stats']['heroDamageReport']['receivedTotal']);

      $r['adv_matchlines'][] = $aml;

      $meta['items'];
      $meta['item_categories'];
      foreach ($pl['stats']['itemPurchases'] as $e) {
        if ($r['matches']['duration'] - $e['time'] < 60) continue;

        $it = [
          'matchid' => $stratz['data']['match']['id'],
          'playerid' => $pl['steamAccountId'],
          'hero_id' => $pl['heroId']
        ];

        $item_id = $e['itemId'];
        if (!$item_id) continue;

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

        $r['items'][] = $it;
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