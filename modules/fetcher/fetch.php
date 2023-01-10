<?php 

/**
 * God bless your soul, the brave one.
 * Even I would rather not look inside of what's hidden here.
 */

include_once __DIR__.'/comebacks.php';
include_once __DIR__.'/skillPriority.php';

function conn_restart() {
  global $conn, $lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db;
  $conn->close();
  $conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);
  $conn->set_charset('utf8mb4');
}

function fetch($match) {
  global $opendota, $conn, $rnum, $matches, $failed_matches, $scheduled, $scheduled_stratz, $t_teams, $t_players, $use_stratz, $require_stratz,
  $request_unparsed, $meta, $stratz_timeout_retries, $force_adding, $cache_dir, $lg_settings, $lrg_use_cache, $first_scheduled,
  $use_full_stratz, $scheduled_wait_period, $steamapikey, $force_await, $players_list, $rank_limit, $stratztoken, $ignore_stratz,
  $update_unparsed, $request_unparsed_players, $stratz_graphql, $api_cooldown_seconds, $update_names, $updated_names, $rewrite_existing,
  $ignore_abandons, $lastversion, $schema;

  $t_match = [];
  $t_matchlines = [];
  $t_adv_matchlines = [];
  $t_draft = [];
  $t_items = null;
  $t_new_players = [];
  $t_starting_items = [];
  $t_skill_builds = [];
  // $t_runes = [];
  $bad_replay = false;

  $players_update = false;
  $match_skip = false;

  if ($lg_settings['main']['teams']) {
    $t_team_matches = [];
  }

  $conn->ping();

  if (empty($match) || $match[0] == "#" || strlen($match) < 2) return true;

  $match_rules = processRules($match);

  echo("[$rnum\t] Match $match: ");
  $rnum++;

  $query = $conn->query("SELECT matchid FROM matches WHERE matchid = ".$match.";");

  if (isset($query->num_rows) && $query->num_rows) {
    if ($update_unparsed) {
      $query = $conn->query("SELECT matchid FROM adv_matchlines WHERE matchid = ".$match.";");
      $match_parsed = isset($query->num_rows) && $query->num_rows;
      if ($lg_settings['main']['items'] && $match_parsed) {
        $query = $conn->query("SELECT matchid FROM items WHERE matchid = ".$match.";");
        $match_parsed = isset($query->num_rows) && $query->num_rows;
      }
    }
    if ($request_unparsed_players) {
      $query = $conn->query("SELECT matchid FROM matchlines WHERE matchid = ".$match." and playerid < 0;");
      $match_parsed = $match_parsed && !(isset($query->num_rows) && $query->num_rows);
      $players_update = true;
      if (!$match_parsed) {
        echo("Players data is incomplete, updating...");
      }
    }

    if (!$rewrite_existing && (!$update_unparsed || $match_parsed)) {
      echo("Already in database, skipping\n");
      return true;
    } else {
      echo("Match exists, rewriting...");
    }

    $match_exists = true;
  } else {
    $match_exists = false;
  }

  $data = [
    'query' => "query MatchPlayers {
      match(id: $match) { 
        parsedDateTime, players { steamAccount { id, isAnonymous, name, seasonRank, proSteamAccount { name } } },
        pickBans {
          heroId,
          bannedHeroId,
          order,
          playerIndex,
          isPick,
          isRadiant,
          wasBannedSuccessfully
        }
      } 
    }",
  ];
  $data['query'] = str_replace("  ", "", $data['query']);
  $data['query'] = str_replace("\n", " ", $data['query']);
  if (!empty($stratztoken)) $data['key'] = $stratztoken;
  
  $stratz_request = "https://api.stratz.com/graphql?".http_build_query($data);

  if($lrg_use_cache && file_exists("$cache_dir/".$match.".lrgcache.json")  && filesize("$cache_dir/".$match.".lrgcache.json") && (!$players_update || !empty($match_rules))) {
    echo("Reusing LRG cache.");
    $json = file_get_contents("$cache_dir/".$match.".lrgcache.json");
    $matchdata = json_decode($json, true);
    $matchdata['iscache'] = true;

    $t_match = $matchdata['matches'];
    $t_matchlines = $matchdata['matchlines'];
    $t_draft = $matchdata['draft'];
    $t_adv_matchlines = $matchdata['adv_matchlines'];
    $t_items = $matchdata['items'] ?? [];
    $t_starting_items = $matchdata['starting_items'] ?? [];
    $t_skill_builds = $matchdata['skill_builds'] ?? [];

    if (empty($t_adv_matchlines)) {
      $bad_replay = true;
      echo "..WARNING: bad replay.";
    }

    foreach($matchdata['players'] as $p) {
      if(!isset($t_players[$p['playerID']]) || ($update_names && !isset($updated_names[$p['playerID']])) ) {
        $t_new_players[$p['playerID']] = $p['nickname'];
      }
    }
    if (isset($t_team_matches) && isset($matchdata['teams'])) {
      $t_team_matches = $matchdata['teams_matches'];
      foreach($matchdata['teams'] as $t) {
        if (!isset($t_teams[$t['teamid']])) {
          $t_teams[ $t['teamid'] ] = array(
            "name" => $t['name'],
            "tag" => $t['tag'],
            "added" => false
          );
        }
      }
    }

    if (!empty($lg_settings['cluster_allowlist'])) {
      if (!in_array($matchdata['matches']['cluster'] ?? 0, $lg_settings['cluster_allowlist'])) {
        echo("..Cluster ".($matchdata['matches']['cluster'] ?? 0)." is not in allowlist, skipping...\n");
        return true;
      }
    }
    if (!empty($lg_settings['cluster_denylist'])) {
      if (in_array($matchdata['matches']['cluster'] ?? 0, $lg_settings['cluster_denylist'])) {
        echo("..Cluster ".($matchdata['matches']['cluster'] ?? 0)." is in denylist, skipping...\n");
        return true;
      }
    }
  } elseif($lrg_use_cache && file_exists("$cache_dir/".$match.".json") && !$players_update) {
    echo("Reusing cache.");
    $json = file_get_contents("$cache_dir/".$match.".json");
    $matchdata_od = json_decode($json, true);
    $matchdata = $matchdata_od;
  // } else if($lrg_use_cache && file_exists("$cache_dir/".$match.".json") && file_exists("$cache_dir/unparsed_".$match.".json") && $force_adding) {
  //   echo("Reusing unparsed cache.");
  //   $json = file_get_contents("$cache_dir/unparsed_".$match.".json");
  //   $matchdata = json_decode($json, true);
  //   $bad_replay = true;
  }
  
  if(empty($matchdata) && $stratz_graphql) {
    echo("Requesting STRATZ GraphQL.");
    $matchdata = get_stratz_response($match);
    $matchdata['isstratz'] = true;

    global $stratz_graphql_group, $stratz_graphql_group_counter;
    if ($stratz_graphql_group) $stratz_graphql_group_counter--;

    $stratz_request = null;
    if (!empty($matchdata)) {
      if($matchdata['matches']['duration'] < 600) {
        echo("..Duration is less than 10 minutes, skipping...\n");
        return true;
      }
      if($matchdata['payload']['score_radiant'] < 5 && $matchdata['payload']['score_dire'] < 5) {
        echo("..Low score, skipping.\n");
        return true;
      }
      if ($matchdata['payload']['leavers']) {
        echo("..Abandon detected, skipping.\n");
        return true;
      }

      if (!empty($lg_settings['cluster_allowlist'])) {
        if (!in_array($matchdata['matches']['cluster'] ?? 0, $lg_settings['cluster_allowlist'])) {
          echo("..Cluster ".($matchdata['matches']['cluster'] ?? 0)." is not in allowlist, skipping...\n");
          return true;
        }
      }
      if (!empty($lg_settings['cluster_denylist'])) {
        if (in_array($matchdata['matches']['cluster'] ?? 0, $lg_settings['cluster_denylist'])) {
          echo("..Cluster ".($matchdata['matches']['cluster'] ?? 0)." is in denylist, skipping...\n");
          return true;
        }
      }

      if (empty($matchdata['adv_matchlines'])) {
        echo "..Incomplete stratz data, requesting OD...";
        $bad_replay = true;

        // 14*24*3600 = two weeks
        if($request_unparsed && !in_array($match, $scheduled) && !empty($match) && (time() - $matchdata['matches']['start_date'] < 1209600)) {
          // @file_get_contents($request);
          `php tools/replay_request_stratz.php -m$match`;
          echo "..Requested and scheduled $match\n";
          $first_scheduled[$match] = time();
          $scheduled_stratz[] = $match;
        }

        //unset($matchdata);
      }

      if (!empty($matchdata)) { //  && !$bad_replay
        $t_match = $matchdata['matches'];
        $t_matchlines = $matchdata['matchlines'];
        $t_draft = $matchdata['draft'];
        $t_adv_matchlines = $matchdata['adv_matchlines'];
        $t_items = $matchdata['items'];
        $t_starting_items = $matchdata['starting_items'];
        $t_skill_builds = $matchdata['skill_builds'];

        foreach($matchdata['players'] as $p) {
          if(!isset($t_players[$p['playerID']]) || ($update_names && !isset($updated_names[$p['playerID']]) )) {
            $t_new_players[$p['playerID']] = $p['nickname'];
          }
        }
        if (isset($t_team_matches) && isset($matchdata['teams'])) {
          $t_team_matches = $matchdata['teams_matches'];
          foreach($matchdata['teams'] as $t) {
            if (!isset($t_teams[$t['teamid']])) {
              $t_teams[ $t['teamid'] ] = array(
                "name" => $t['name'],
                "tag" => $t['tag'],
                "added" => false
              );
            }
          }
        }
      } else {
        $match_players = $matchdata['players'];
        foreach($matchdata['players'] as $p) {
          if(!isset($t_players[$p['playerID']])) {
            $t_new_players[$p['playerID']] = $p['nickname'];
          }
        }
        unset($matchdata);
      }
    } else {
      if($request_unparsed && !in_array($match, $scheduled) && !empty($matchdata)) {
        //`php tools/replay_request_stratz.php -m$match`;
        echo "..Rescheduled $match STRATZ\n";
        $first_scheduled[$match] = time();
        $scheduled_stratz[] = $match;
        $scheduled[] = $match;
        return false;
      }
    }
  }
  
  if (empty($matchdata) || ( empty($matchdata['items']) && !$bad_replay ) || ( $bad_replay && !$force_adding )) {
    echo("Requesting.");

    if (!$ignore_stratz && !$stratz_graphql && (!empty($players_list) || !empty($rank_limit))) {
      $json = false;
      do {
        $json = file_get_contents($stratz_request);
      } while (!$json);
      $stratz = empty($json) ? [] : json_decode($json, true);

      $players = $stratz['players'];
      foreach ($players as $pl) {
        if (!empty($players_list) && !in_array($pl['steamAccount']['id'], $players_list)) {
          echo "Player(s) are not in allow list, skipping.\n";
          return true;
        }
        if (!empty($lg_settings['players_denylist']) && in_array($pl['steamAccount']['id'], $lg_settings['players_denylist'])) {
          echo "Player(s) are in deny list, skipping.\n";
          return true;
        }
        if (!empty($rank_limit) && isset($pl['steamAccount']['seasonRank']) && $pl['steamAccount']['seasonRank'] < $rank_limit) {
          echo "Rank lower than required, skipping.\n";
          return true;
        }
      }
    }

    $matchdata_stratz = $matchdata ?? null;
    $matchdata = empty($matchdata_od) ? $opendota->match($match) : $matchdata_od;
    echo("..OK.");
    if (empty($matchdata) || empty($matchdata['duration']) || empty($matchdata['players'])) {
      if (empty($matchdata_stratz)) {
        echo("..ERROR: Unable to read JSON skipping.\n");
        //if (!isset($matchdata['duration'])) var_dump($matchdata);

        if($request_unparsed && !in_array($match, $scheduled)) {
          $opendota->request_match($match);
          echo "[\t] Requested and scheduled $match\n";
          $first_scheduled[$match] = time();
          $scheduled[] = $match;
          return false;
        } else { //if (in_array($match, $scheduled) && !$force_adding) {
          return null;
        }
      } else {
        $matchdata = $matchdata_stratz;
      }
    } else {
      if($matchdata['duration'] < 600) {
          echo("..Duration is less than 10 minutes, skipping...\n");
          // Initially it used to be 5 minutes, but sice a lot of stuff is hardly
          // binded with 10 min mark, it's better to use 10 min as a benchmark.
          return true;
      }
      if (!$matchdata['radiant_score']) {
        $matchdata['radiant_score'] = 0;
        $n = round(sizeof($matchdata['players'])/2);
        for ($i=0; $i<$n; $i++) $matchdata['radiant_score'] += $matchdata['players'][$i]['kills'];
      }
      if (!$matchdata['dire_score']) {
        $matchdata['dire_score'] = 0;
        $n = sizeof($matchdata['players']);
        for ($i=5; $i<$n; $i++) $matchdata['dire_score'] += $matchdata['players'][$i]['kills'];
      }
      if($matchdata['radiant_score'] < 5 && $matchdata['dire_score'] < 5) {
          echo("..Low score, skipping.\n");
          return true;
      }

      $abandon = false;
      for($i=0; $i<10; $i++) {
          if($matchdata['players'][$i]['abandons'] ?? 0) {
              $abandon = true;
              break;
          }
      }

      if(!$ignore_abandons && $abandon) {
          echo("..Abandon detected, skipping.\n");
          return true;
      }

      if (!empty($lg_settings['cluster_allowlist'])) {
        if (!in_array($matchdata['cluster'] ?? 0, $lg_settings['cluster_allowlist'])) {
          echo("..Cluster ".($matchdata['cluster'] ?? 0)." is not in allowlist, skipping...\n");
          return true;
        }
      }

      if (!empty($lg_settings['cluster_denylist'])) {
        if (in_array($matchdata['cluster'] ?? 0, $lg_settings['cluster_denylist'])) {
          echo("..Cluster ".($matchdata['cluster'] ?? 0)." is in denylist, skipping...\n");
          return true;
        }
      }

      if ($matchdata['players'][0]['lh_t'] == null) {
        if($request_unparsed && !in_array($match, $scheduled)) {
          $opendota->request_match($match);
          echo "..Unparsed. Requested and scheduled $match\n";
          $first_scheduled[$match] = time();
          $scheduled[] = $match;
          return false;
        }

        if(!$force_adding) {
          echo("..ERROR: Replay isn't parsed.\n");
          return null;
        } else {
          echo("..WARNING: Replay isn't parsed.");
          $bad_replay = true;
        }
      }
    }
  }

  if (!empty($players_list)) {
    $players = $matchdata[0]['players'];
    foreach ($players as $pl) {
      if (!in_array($pl['account_id'], $players_list)) {
        return true;
      }
    }
  }

  if (!empty($stratz_request) && ( (!file_exists("$cache_dir/".$match.".lrgcache.json") && !file_exists("$cache_dir/".$match.".json")) 
        || ( $bad_replay && !$force_adding && !($matchdata['iscache'] || $matchdata['isstratz']) )
        || $players_update) ) {

    if(!isset($matchdata['lobby_type']) || $players_update || ($matchdata['lobby_type'] != 1 && $matchdata['lobby_type'] != 2 && $use_stratz && !$ignore_stratz)) {
      echo("..Requesting STRATZ.");

      if (empty($stratz)) {
        $json = @file_get_contents($stratz_request, false, stream_context_create([
          'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
          ]
        ]));

        $stratz = empty($json) ? [] : json_decode($json, true)['data']['match'];
      }

      if (!empty($stratz['players'])) {
        for($i=0, $j=0, $sz=sizeof($matchdata['players']); $i<$sz; $i++) {
          if(!isset($matchdata['players'][$i]['hero_id']) || !$matchdata['players'][$i]['hero_id'] || $j>9) {
            unset($matchdata['players'][$i]);
            continue;
          }
          if(!isset($matchdata['players'][$i]['account_id']) || !$matchdata['players'][$i]['account_id']) {
            $matchdata['players'][$i]['account_id'] = $stratz['players'][$j]['steamAccount']['id'];

            // TODO:
            if (!isset($t_players[ $matchdata['players'][$i]['account_id'] ])) {
              //$tmp = $opendota->player($matchdata['players'][$i]['account_id']);

              $matchdata['players'][$i]["name"] = !empty($stratz['players'][$j]['steamAccount']['proSteamAccount']) ? 
                $stratz['players'][$j]['steamAccount']['name'] : $stratz['players'][$j]['steamAccount']['name'] ?? null;
              $matchdata['players'][$i]["personaname"] = $stratz['players'][$j]['steamAccount']['name'] ?? null;
            } else {
              $matchdata['players'][$i]["name"] = $t_players[ $matchdata['players'][$i]['account_id'] ];
            }
          }
          $j++;
        }
      }

      if(empty($stratz['parsedDateTime'])) {
        unset($stratz);

        if($request_unparsed && !in_array($match, $scheduled_stratz)) {
          // @file_get_contents($request);
          `php tools/replay_request_stratz.php -m$match`;
          echo "..Requested and scheduled $match\n";
          $first_scheduled[$match] = time();
          $scheduled_stratz[] = $match;
          return false;
        }
      }

      // this block of code is outdated and is supposed to work only in emergency situations
      // I'll still change it to use graphql though
      $full_request = false;
      if(($matchdata['game_mode'] == 22 || $matchdata['game_mode'] == 3 || empty($matchdata['picks_bans'])) && 
          (!in_array($match, $failed_matches))) {
        $stratz_retries = $stratz_timeout_retries+1;
        
        while ((empty($stratz['stats']['pickBans']) || empty($stratz['stats']['pickBans']) || empty($stratz)) && $use_full_stratz) {
          $stratz_retries--;
          echo "..STRATZ ERROR";
          sleep(5);
          echo ", retrying.";

          if (empty($stratz['stats']['pickBans'])) {
              // $request = "https://api.stratz.com/api/v1/match/$match".(!empty($stratztoken) ? "?key=$stratztoken" : "");
              $full_request = true;
          }

          $json = @file_get_contents($stratz_request, false, stream_context_create([
            'ssl' => [
              'verify_peer' => false,
              'verify_peer_name' => false,
            ]
          ]));
          $stratz = json_decode($json, true);

          if($full_request && empty($stratz) || !$stratz_retries) {
              echo("..ERROR: Missing STRATZ analysis, skipping.\n");

              if($request_unparsed && !in_array($match, $scheduled_stratz)) {
                @file_get_contents($stratz_request, false, stream_context_create([
                  'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                  ]
                ]));
                `php tools/replay_request_stratz.php -m$match`;
                echo "[\t] Requested and scheduled $match\n";
                $first_scheduled[$match] = time();
                $scheduled_stratz[] = $match;
                unset($stratz);
                break;
              } else if ($require_stratz) {
                echo("..ERROR: Missing STRATZ analysis, skipping.\n");
                $failed_matches[sizeof($failed_matches)] = $match;
                break;
              }
          } else {
            if ($full_request) $stratz = [ $stratz['data']['match'] ];
          }
        }

        if(empty($stratz) && $require_stratz) {
          echo "..Problems when requesting Stratz.\n";
          return null;
        }

        if(!empty($stratz['stats']['pickBans'])) {
          $matchdata['picks_bans_stratz'] = $stratz['stats']['pickBans'];
        } 
      }
      
      $matchdata['players'] = array_values($matchdata['players']);
      
      if (!empty($matchdata['picks_bans_stratz'])) {
        echo("..Stratz data merged.");
        unset($stratz);
      } else {
        echo("..Missing full stratz analysis, merged players.");
      }
    }

    if($lg_settings['main']['teams'] && (!isset($matchdata['radiant_team']['team_id']) || !isset($matchdata['dire_team']['team_id'])) ) {
        $json = @file_get_contents("https://api.steampowered.com/IDOTA2Match_570/GetMatchDetails/V001/?match_id=$match&key=$steamapikey");
        $tmp = json_decode($json, true);
        unset($json);
        if(!isset($matchdata['radiant_team']['team_id'])) {
          if(isset($tmp['result']['radiant_team_id'])) {
            if(isset($t_teams[$tmp['result']['radiant_team_id']]) ) {
              $matchdata['radiant_team']['team_id'] = $tmp['result']['radiant_team_id'];
              $matchdata['radiant_team']['name'] = $t_teams[$tmp['result']['radiant_team_id']]['name'];
              $matchdata['radiant_team']['tag'] = $t_teams[$tmp['result']['radiant_team_id']]['tag'];
            } else {
              $matchdata['radiant_team']['team_id'] = $tmp['result']['radiant_team_id'];
              $matchdata['radiant_team']['name'] = $tmp['result']['radiant_name'];

              $json = @file_get_contents('https://api.steampowered.com/IDOTA2Match_570/GetTeamInfoByTeamID/v001/?key='.$steamapikey.'&teams_requested=1&start_at_team_id='.$matchdata['radiant_team']['team_id']);
              $team = json_decode($json, true);

              if( !isset($team['result']['teams'][0]['tag']) || $team['result']['teams'][0]['tag'] == null )
                  $matchdata['radiant_team']['tag'] = generate_tag($tmp['result']['radiant_name']);
              else
                  $matchdata['radiant_team']['tag'] = $team['result']['teams'][0]['tag'];
            }
          }
        }
        if(!isset($matchdata['dire_team']['team_id'])) {
          if(isset($tmp['result']['dire_team_id'])) {
            if(isset($t_teams[$tmp['result']['dire_team_id']]) ) {
              $matchdata['dire_team']['team_id'] = $tmp['result']['dire_team_id'];
              $matchdata['dire_team']['name'] = $t_teams[$tmp['result']['dire_team_id']]['name'];
              $matchdata['dire_team']['tag'] = $t_teams[$tmp['result']['dire_team_id']]['tag'];
            } else {
              $matchdata['dire_team']['team_id'] = $tmp['result']['dire_team_id'];
              $matchdata['dire_team']['name'] = $tmp['result']['dire_name'];

              $json = @file_get_contents('https://api.steampowered.com/IDOTA2Match_570/GetTeamInfoByTeamID/v001/?key='.$steamapikey.'&teams_requested=1&start_at_team_id='.$matchdata['dire_team']['team_id']);
              $team = json_decode($json, true);

              if( !isset($team['result']['teams'][0]['tag']) || $team['result']['teams'][0]['tag'] == null )
                  $matchdata['dire_team']['tag'] = generate_tag($tmp['result']['dire_name']);
              else
                  $matchdata['dire_team']['tag'] = $team['result']['teams'][0]['tag'];
            }
          }
        }
    }

    unset($matchdata['chat']);
    unset($matchdata['cosmetics']);

    // $json = json_encode($matchdata);
    // if(!empty($cache_dir) && $lrg_use_cache) {
    //   $f = fopen("$cache_dir/".($bad_replay ? "unparsed_" : "").$match.".json", "w");
    //   fwrite($f, $json);
    //   fclose($f);

    //   echo("..Saved to cache.");
    // }
  }

  // unset($json);

  if (empty($t_match)) {
    for($i=0; $i<2; $i++, $teamid = null) {
      $tag = $i ? 'radiant_team' : 'dire_team';
      $teamid = $matchdata[$tag.'_id'] ?? null;
    
      if (!empty($teamid) && !empty($match_rules['team']) && isset($match_rules['team'][ $teamid ]))
        $teamid = (int)$match_rules['team'][ $teamid ];
    
      if (isset($match_rules['side'][ $tag ]) || isset($match_rules['side'][ $i ? 'radiant' : 'dire' ]) || isset($match_rules['side'][ $i ]))
        $teamid = (int) ($match_rules['side'][ $tag ] ?? $match_rules['side'][ $i ? 'radiant' : 'dire' ] ?? $match_rules['side'][ $i ] ?? $teamid);
    
      if(empty($teamid)) continue;

      $json = file_get_contents('https://api.steampowered.com/IDOTA2Match_570/GetTeamInfoByTeamID/v001/?key='.$steamapikey.'&teams_requested=1&start_at_team_id='.$teamid);
      $team = json_decode($json, true);

      $matchdata[$tag.'_id'] = $teamid;
      $matchdata[$tag] = [
        'team_id' => $teamid,
        'name' => $team['result']['teams'][0]['name'],
        'tag' => $team['result']['teams'][0]['tag'] ?? generate_tag($team['result']['teams'][0]['name']),
      ];
    }
  } else {
    if (!empty($t_team_matches))
      foreach ($t_team_matches as $i => $tm) {
        $tag = $tm['is_radiant'] ? 'radiant_team' : 'dire_team';
        
        if (!empty($match_rules['team']) && isset($match_rules['team'][ $tm['teamid'] ])) {
          $t_team_matches[$i]['teamid'] = (int)$match_rules['team'][ $tm['teamid'] ];
        }
      
        if (!empty($match_rules['side']) && (isset($match_rules['side'][ $tag ]) || isset($match_rules['side'][ $tm['is_radiant'] ? 'radiant' : 'dire' ]) || isset($match_rules['side'][ $tm['is_radiant'] ])))
          $t_team_matches[$i]['teamid'] = (int) ($match_rules['side'][ $tag ] ?? $match_rules['side'][ $tm['is_radiant'] ? 'radiant' : 'dire' ] ?? $match_rules['side'][ $tm['is_radiant'] ] ?? $tm['teamid']);

        if (!isset($t_teams[ $tm['teamid'] ])) {
          $json = file_get_contents('https://api.steampowered.com/IDOTA2Match_570/GetTeamInfoByTeamID/v001/?key='.$steamapikey.'&teams_requested=1&start_at_team_id='.$tm['teamid']);
          $team = json_decode($json, true);

          $t_teams[ $tm['teamid'] ] = [
            'name' => $team['result']['teams'][0]['name'],
            'tag' => $team['result']['teams'][0]['tag'] ?? generate_tag($team['result']['teams'][0]['name']),
            'added' => false
          ];
        }
      }
    else $t_team_matches = [];
  }

  if (empty($t_match)) {
    $t_match['matchid'] = $match;
    if (empty($matchdata['start_time'])) return true;
    $t_match['version'] = get_patchid($matchdata['start_time'], $matchdata['patch'], $meta);
    $t_match['radiantWin'] = $matchdata['radiant_win'];
    $t_match['duration'] = $matchdata['duration'];
    $t_match['modeID'] = $matchdata['game_mode'];
    $t_match['leagueID'] = $matchdata['leagueid'];
    $t_match['seriesid'] = $matchdata['series_id'] ?? null;
    $t_match['cluster']  = $matchdata['cluster'] ?? null;
    $t_match['start_date'] = $matchdata['start_time'];
    $t_match['analysis_status'] = $bad_replay ? 0 : 1;
    $t_match['radiant_opener'] = null; // wait until draft is populated to find out who has the first stage

    // if (isset($matchdata['stomp']))
    //     $t_match['stomp'] = $matchdata['stomp'];
    // else $t_match['stomp'] = $bad_replay ? 0 : $matchdata['loss'];
    // if (isset($matchdata['comeback']))
    //     $t_match['comeback'] = $matchdata['comeback'];
    // else $t_match['comeback'] = $bad_replay ? 0 : $matchdata['throw'];

    if (!$bad_replay) {
      add_networths($matchdata);
      [ $t_match['stomp'], $t_match['comeback'] ] = find_comebacks($matchdata['radiant_nw_adv'] ?? $matchdata['radiant_gold_adv'], $matchdata['radiant_win']);
    } else {
      $t_match['stomp'] = 0;
      $t_match['comeback'] = 0;
    }

    if ($lg_settings['main']['teams'] && (isset($matchdata['radiant_team']) || isset($matchdata['dire_team']))) {
      for($i=0; $i<2; $i++) {
        $tag = !$i ? 'dire_team' : 'radiant_team';
        if(!isset($matchdata[$tag])) continue;

        $t_team_matches[] = array(
          "matchid" => $match,
          "teamid"  => $matchdata[$tag]['team_id'],
          "is_radiant" => $i,
        );
        if (!isset($t_teams[$matchdata[$tag]['team_id']])) {
          $t_teams[$matchdata[$tag]['team_id']] = array(
            "name" => $matchdata[$tag]['name'],
            "tag" => $matchdata[$tag]['tag'],
            "added" => false
          );
        }
      }
    }
  }

  // teams / players allow/deny lists block 

  if ($lg_settings['main']['teams']) {
    if (!empty($lg_settings['teams_allowlist'])) {
      if (empty($t_team_matches)) {
        echo("..No teams to allow, skipping...\n");
        return true;
      }
      foreach ($t_team_matches as $tm) {
        if (!in_array($tm['teamid'], $lg_settings['teams_allowlist'])) {
          echo("..Team ".$tm['teamid']." is not in allowlist, skipping...\n");
          return true;
        }
      }
    }
    if (!empty($lg_settings['teams_denylist'])) {
      if (!empty($t_team_matches)) {
        echo("..No teams to deny, skipping...\n");
        return true;
      }
      foreach ($t_team_matches as $tm) {
        if (in_array($tm['teamid'], $lg_settings['teams_denylist'])) {
          echo("..Team ".$tm['teamid']." is in denylist, skipping...\n");
          return true;
        }
      }
    } 
  }
  
  if (empty($t_matchlines)) {
    if (!$bad_replay) {
      $teams_players = [[],[]];
      $team_roles = [[],[]];
      $laning_raw = [];
      foreach ($matchdata['players'] as $player) {
        $team = $player['isRadiant'] ? 1 : 0;
        $p = [
          'hid' => $player['hero_id'],
          'gpm' => $player['gold_per_min'],
          'xpm' => $player['xp_per_min'],
          'roaming' => $player['is_roaming'],
          'lane' => $player['lane_role'],
          'eff' => $player['lane_efficiency'],
        ];
        $laning_raw[$p['hid']] = $p['eff'];
        $teams_players[$team][] = $p;
      }
      
      foreach($teams_players as $i => $tm) {
        $roles = [];
        $supports = [];
        $lanes = [];
        foreach ($tm as $p) {
          if (!isset($lanes[$p['lane']])) $lanes[$p['lane']] = [];
          $lanes[$p['lane']][] = $p;
        }
        ksort($lanes);
        $laning_raw[$i] = $lanes;
        foreach($lanes as $lane => $players) {
          if ($lane > 3) {
            if (count($roles) == 3) {
              $supports = array_merge($supports, $players);
              continue;
            }
            $lane = 1;
            foreach ($roles as $rid => $data) {
              if ($rid == $lane) $lane++;
            }
          }
          usort($players, function($a, $b) {
            return $b['gpm'] <=> $a['gpm'];
          });
          $roles[$lane] = array_shift($players);
          $supports = array_merge($supports, $players);
        }
        usort($supports, function($a, $b) {
          return $b['gpm'] <=> $a['gpm'];
        });

        foreach ($supports as $p) {
          $roles[] = $p;
        }

        $team_roles[$i] = [];
        foreach ($roles as $rid => $p) $team_roles[$i][ $p['hid'] ] = $rid;
      }

      $tie_factor = 0.075;
      $laning = [];

      foreach([1,2,3] as $lane) {
        $opp_lane = 4-$lane;

        $max_eff_self = array_reduce($laning_raw[0][$lane] ?? [], function($carry, $item) {
          return max($carry, $item['eff']);
        }, 0.7);

        $max_eff_opp = array_reduce($laning_raw[1][$opp_lane] ?? [], function($carry, $item) {
          return max($carry, $item['eff']);
        }, 0);

        $diff = $max_eff_self - $max_eff_opp;
        $lane_state = abs($diff) > $tie_factor ? ( $diff < 1 ? 0 : 2 ) : 1;

        foreach($players as $p) {
          $laning[$p['hid']] = $lane_state;
        }
        $lane_state = 2-$lane_state;
        foreach($laning_raw[1][$opp_lane] as $p) {
          $laning[$p['hid']] = $lane_state;
        }
      }
      foreach ($team_roles as $i => $roles) {
        foreach ($roles as $hid => $role) {
          if (isset($laning[$hid])) continue;
          
          $opp = array_flip($team_roles[1-$i])[$role];
          if (isset($laning[$opp])) {
            $laning[$hid] = 2-$laning[$opp];
          } else {
            $diff = $laning_raw[$hid] - $laning_raw[$opp];
            $laning[$hid] = abs($diff) > $tie_factor ? ( $diff < 1 ? 0 : 2 ) : 1;
            $laning[$opp] = abs($laning[$hid]-2);
          }
        }
      }
    }

    $i = sizeof($t_matchlines);
    for ($j=0, $sz=10; $j<$sz; $j++) {
        $t_matchlines[$i]['matchid'] = $match;

        # for wrong numbers of players in opendota response
        if (!isset($matchdata['players'][$j]['hero_id'])) {
          $sz++;
          continue;
        }

        if (isset($match_players)) {
          $matchdata['players'][$j]['account_id'] = $match_players[$i]['playerID'];
          $matchdata['players'][$j]["name"] = $match_players[$i]['nickname'];
        }
        
        # support for botmatches
        if ($matchdata['players'][$j]['account_id'] != null)
          $t_matchlines[$i]['playerid'] = $matchdata['players'][$j]['account_id'];
        else {
          if (isset($matchdata['radiant_team'])) {
            if($matchdata['players'][$j]['isRadiant'])
                $matchdata['players'][$j]['account_id'] = $matchdata['radiant_team']['team_id'];
            else
                $matchdata['players'][$j]['account_id'] = $matchdata['dire_team']['team_id'];
          } else $matchdata['players'][$j]['account_id'] = 1;

          $matchdata['players'][$j]['account_id'] *= (-1)*$matchdata['players'][$j]['hero_id'];
          $t_matchlines[$i]['playerid'] = $matchdata['players'][$j]['account_id'];
        }

        $pid = (int)$matchdata['players'][$j]['account_id'];
        if(!isset($t_players[$pid]) || ($update_names && !isset($updated_names[$pid]))) {
          if ($pid < 0) {
            $t_new_players[$pid] = $meta['heroes'][$matchdata['players'][$j]['hero_id']]['name']." Player";
          } else {
            if (isset($matchdata['players'][$j]["name"]) && $matchdata['players'][$j]["name"] != null) {
              $t_new_players[$pid] = $matchdata['players'][$j]["name"];
            } else if (isset($matchdata['players'][$j]["personaname"])) {
              $t_new_players[$pid] = $matchdata['players'][$j]["personaname"];
            } else
              $t_new_players[$pid] = "Player ".$pid;
          }

        }

        // using fetcher rules
        if (isset($match_rules['player'][ $t_matchlines[$i]['playerid'] ])) {
          $t_matchlines[$i]['playerid'] = (int)$match_rules['player'][ $t_matchlines[$i]['playerid'] ];
          $player_info = $opendota->player($t_matchlines[$i]['playerid']);
          $t_new_players[ $t_matchlines[$i]['playerid'] ] = $player_info['profile']['name'] ?? $player_info['profile']['personaname'] ?? "Player ".$t_matchlines[$i]['playerid'];
        }

        if (isset($match_rules['pslot'][$i])) {
          $t_matchlines[$i]['playerid'] = (int)$match_rules['pslot'][$i];
          $player_info = $opendota->player($t_matchlines[$i]['playerid']);
          $t_new_players[ $t_matchlines[$i]['playerid'] ] = $player_info['profile']['name'] ?? $player_info['profile']['personaname'] ?? "Player ".$t_matchlines[$i]['playerid'];
        }



        $t_matchlines[$i]['heroid'] = $matchdata['players'][$j]['hero_id'];
        $t_matchlines[$i]['isRadiant'] = $matchdata['players'][$j]['isRadiant'];
        $t_matchlines[$i]['level'] = $matchdata['players'][$j]['level'];
        $t_matchlines[$i]['kills'] = $matchdata['players'][$j]['kills'];
        $t_matchlines[$i]['deaths'] = $matchdata['players'][$j]['deaths'];
        $t_matchlines[$i]['assists'] = $matchdata['players'][$j]['assists'];
        $t_matchlines[$i]['networth'] = $matchdata['players'][$j]['net_worth'] ?? $matchdata['players'][$j]['total_gold'];
        $t_matchlines[$i]['gpm'] = $matchdata['players'][$j]['gold_per_min'];
        $t_matchlines[$i]['xpm'] = $matchdata['players'][$j]['xp_per_min'];
        $t_matchlines[$i]['heal'] = $matchdata['players'][$j]['hero_healing'];
        $t_matchlines[$i]['heroDamage'] = $matchdata['players'][$j]['hero_damage'];
        $t_matchlines[$i]['towerDamage'] = $matchdata['players'][$j]['tower_damage'];
        $t_matchlines[$i]['lastHits'] = $matchdata['players'][$j]['last_hits'];
        $t_matchlines[$i]['denies'] = $matchdata['players'][$j]['denies'];


        $t_adv_matchlines[$i]['matchid'] = $match;
        $t_adv_matchlines[$i]['playerid'] = $matchdata['players'][$j]['account_id'];
        $t_adv_matchlines[$i]['heroid'] = $matchdata['players'][$j]['hero_id'];

        if (!$bad_replay) {
          if (empty($matchdata['players'][$j]['lh_t'])) $matchdata['players'][$j]['lh_t'] = [0];
          $t_adv_matchlines[$i]['lh_at10'] = $matchdata['players'][$j]['lh_t'][10] ?? end($matchdata['players'][$j]['lh_t']);
          if ($matchdata['players'][$j]['lane_role'] == 5)
              $matchdata['players'][$j]['lane_role'] = 4; # we don't care about different jungles
          //if ($matchdata['players'][$j]['is_roaming'])
          //    $matchdata['players'][$j]['lane_role'] = 5;
          $t_adv_matchlines[$i]['lane'] = $matchdata['players'][$j]['lane_role'];
        }

        # trying to decide, is it a core
        $support_indicators = 0;
        //if ($matchdata['players'][$j]['lane_role'] == 4) $support_indicators+=2;
        if (!$bad_replay) {
          if ($matchdata['players'][$j]['lh_t'][5] <= 6) $support_indicators++;
          if ($matchdata['players'][$j]['lh_t'][3] <= 2) $support_indicators++;

          if ($matchdata['players'][$j]['obs_placed'] > 0) $support_indicators++;
          if ($matchdata['players'][$j]['obs_placed'] > 3) $support_indicators++;
          if ($matchdata['players'][$j]['obs_placed'] > 8) $support_indicators++;
          if ($matchdata['players'][$j]['obs_placed'] > 12) $support_indicators++;
          if ($matchdata['players'][$j]['sen_placed'] > 6) $support_indicators++;

          if (empty($matchdata['players'][$j]['lane_efficiency'])) $matchdata['players'][$j]['lane_efficiency'] = $t_adv_matchlines[$i]['lh_at10']/42;

          if ($matchdata['players'][$j]['lane_efficiency'] < 0.55 && $matchdata['players'][$j]['win']) $support_indicators++;
          if ($matchdata['players'][$j]['lane_efficiency'] < 0.50) $support_indicators++;
          if ($matchdata['players'][$j]['lane_efficiency'] < 0.35) $support_indicators++;
          if ($matchdata['players'][$j]['is_roaming'] || $matchdata['players'][$j]['lane_role'] == 4) $support_indicators+=5;
        }

        if ($matchdata['players'][$j]['gold_per_min'] < 420 && $matchdata['players'][$j]['win']) $support_indicators++;
        if ($matchdata['players'][$j]['gold_per_min'] < 355) $support_indicators++;
        if ($matchdata['players'][$j]['gold_per_min'] < 290) $support_indicators++;

        if ($matchdata['players'][$j]['hero_damage']*60/$matchdata['duration'] < 375 && $matchdata['players'][$j]['win']) $support_indicators++;
        if ($matchdata['players'][$j]['hero_damage']*60/$matchdata['duration'] < 275 && !$matchdata['players'][$j]['win']) $support_indicators++;

        if ($matchdata['players'][$j]['last_hits']*60/$matchdata['duration'] < 2.5) $support_indicators++;

        # TODO compare to teammates on the same lane/role

        if (!$bad_replay && $support_indicators > 4) $t_adv_matchlines[$i]['isCore'] = 0;
        else if ($bad_replay && $support_indicators > 2) $t_adv_matchlines[$i]['isCore'] = 0;
        else $t_adv_matchlines[$i]['isCore'] = 1;

        if (!$bad_replay) {
          if ($t_adv_matchlines[$i]['isCore'] && $matchdata['players'][$j]['is_roaming'])
            $t_adv_matchlines[$i]['lane'] = $matchdata['players'][$j]['lane_role'];
          else if (!$t_adv_matchlines[$i]['isCore'] && $matchdata['players'][$j]['is_roaming'])
            $t_adv_matchlines[$i]['lane'] = 5;
          # Gonna put roaming cores into junglers for now
        } else {
          # We can't determine hero's lane, so we're going to set it as the most popular value for that hero
          # if it's core. Supports are going to be roaming.
          if ($t_adv_matchlines[$i]['isCore']) {
            #$t_adv_matchlines[$i]['heroid']
            $sql = "SELECT a.lane, COUNT(DISTINCT matchid) mc FROM".
                    "(select * from `adv_matchlines` WHERE heroid=".$t_adv_matchlines[$i]['heroid']." limit 35) a ".
                    "GROUP BY a.lane ORDER BY mc DESC";

            if ($conn->multi_query($sql) !== TRUE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

            $query_res = $conn->store_result();

            $row = $query_res->fetch_row();
            if (!empty($row)) {
              $t_adv_matchlines[$i]['lane'] = $row[0];
              if($row[0] == 5 || $row[0] == 4)
                $t_adv_matchlines[$i]['isCore'] = 0;
            } else {
              $t_adv_matchlines[$i]['lane'] = 0;
              $t_adv_matchlines[$i]['isCore'] = 0;
            }

            $query_res->free_result();
            # It's not ideal, but it works for now.
          } else {
            $t_adv_matchlines[$i]['lane'] = 4;
          }

        }


        if (!$bad_replay) {
          $t_adv_matchlines[$i]['role'] = $team_roles[ $matchdata['players'][$j]['isRadiant'] ? 1 : 0 ][ $matchdata['players'][$j]['hero_id'] ];
          $t_adv_matchlines[$i]['lane_won'] = $laning[ $matchdata['players'][$j]['hero_id'] ];
          $t_adv_matchlines[$i]['efficiency_at10'] = $matchdata['players'][$j]['lane_efficiency'];
          $t_adv_matchlines[$i]['wards'] = $matchdata['players'][$j]['obs_placed'];
          $t_adv_matchlines[$i]['sentries'] = $matchdata['players'][$j]['sen_placed'];
          $t_adv_matchlines[$i]['couriers_killed'] = $matchdata['players'][$j]['courier_kills'];
          $t_adv_matchlines[$i]['roshans_killed'] = $matchdata['players'][$j]['roshan_kills'];
          $t_adv_matchlines[$i]['wards_destroyed'] = $matchdata['players'][$j]['observer_kills'];
          if (count($matchdata['players'][$j]['multi_kills']) == 0) $t_adv_matchlines[$i]['multi_kill'] = 0;
          else {
              $tmp = array_keys($matchdata['players'][$j]['multi_kills']);
              $t_adv_matchlines[$i]['multi_kill'] = end($tmp);
              unset($tmp);
          }
          if (count($matchdata['players'][$j]['kill_streaks']) == 0) $t_adv_matchlines[$i]['streak'] = 0;
          else {
              $tmp = array_keys($matchdata['players'][$j]['kill_streaks']);
              $t_adv_matchlines[$i]['streak'] = end($tmp);
              unset($tmp);
          }
          $t_adv_matchlines[$i]['stacks'] = $matchdata['players'][$j]['camps_stacked'];
          $t_adv_matchlines[$i]['time_dead'] = $matchdata['players'][$j]['life_state_dead'];
          $t_adv_matchlines[$i]['buybacks'] = $matchdata['players'][$j]['buyback_count'];
          $t_adv_matchlines[$i]['pings'] = isset($matchdata['players'][$j]['pings']) ? $matchdata['players'][$j]['pings'] : 0;
          $t_adv_matchlines[$i]['stuns'] = $matchdata['players'][$j]['stuns'];
          $t_adv_matchlines[$i]['teamfight_part'] = $matchdata['players'][$j]['teamfight_participation'];
          if (!$t_adv_matchlines[$i]['teamfight_part']) {
            $team_score = 0; $n = $i < 5 ? 5 : 10;
            for ($k=0; $k < $n; $k++) $team_score += $matchdata['players'][$k]['kills'];
            if ($team_score) {
              $t_adv_matchlines[$i]['teamfight_part'] = $matchdata['players'][$j]['kills']/$team_score;
            } else {
              $t_adv_matchlines[$i]['teamfight_part'] = 0;
            }
          }
          $t_adv_matchlines[$i]['damage_taken'] = 0;
          foreach($matchdata['players'][$j]['damage_inflictor_received'] as $key => $instance) {
            $t_adv_matchlines[$i]['damage_taken'] += $instance;
          }
        }
        $i++;
    }
  }

  if (empty($t_draft)) {
    $i = 0;

    # OpenDota doesn't have information about draft for Ranked All Pick
    # Game Mode IDs:
    # 2  = Captain's Mode
    # 9  = Reverse Captain's Mode
    # 16 = Captain's Draft
    #
    # versions:
    # <= 20 = before 7.07
    # > 20 = after 7.07
    # TODO Draft information from Stratz for ranked all pick (22)


    if ($matchdata['game_mode'] == 2 || $matchdata['game_mode'] == 9) {
        if (isset($matchdata['picks_bans']))
          $drafts =& $matchdata['picks_bans'];
        else 
          $drafts =& $matchdata['draft_timings'];

        $stage = 0;
        $last_stage_pick = null;

        uasort($drafts, function($a, $b) {
          if ($a == $b) {
            $pick_a = $a['is_pick'] ?? $a['pick'];
            $pick_b = $b['is_pick'] ?? $b['pick'];
            if (!$pick_a && $pick_b) return -1;
            else if ($pick_a && !$pick_b) return 1;
            return 0;
          }
          return ($a['order'] < $b['order']) ? -1 : 1;
        });

        foreach ($drafts as $draft_instance) {
            if (!isset($draft_instance['hero_id']) || !$draft_instance['hero_id'])
              continue;
            
            $pick = $draft_instance['is_pick'] ?? $draft_instance['pick'];

            if (isset($draft_instance['team'])) {
              $team = $draft_instance['team'];
            } else if (isset($draft_instance['player_slot'])) {
              $team = $draft_instance['player_slot'] >= 5;
            } else {
              $team = $draft_instance['active_team']-2;
            }

            // if it's the first draft stage or  a switch from bans to picks was made
            if ($last_stage_pick !== $pick && !$pick) {
              $stage++;
            }

            $draft_stage = $stage;
            
            $last_stage_pick = $pick;

            $t_draft[$i]['matchid'] = $match;
            $t_draft[$i]['is_radiant'] = $team ? 0 : 1;
            $t_draft[$i]['is_pick'] = $pick;
            $t_draft[$i]['hero_id'] = $draft_instance['hero_id'];
            $t_draft[$i]['stage'] = $draft_stage;
            $t_draft[$i]['order'] = $i;

            $i++;
        }

        if ($t_match['radiant_opener'] === null) {
          $t_match['radiant_opener'] = $t_draft[0]['is_radiant'];
        }
    } else if ($matchdata['game_mode'] == 16) {
        foreach ($matchdata['picks_bans'] as $draft_instance) {
            if (!isset($draft_instance['hero_id']) || !$draft_instance['hero_id'])
              continue;
        
            $t_draft[$i]['matchid'] = $match;
            $t_draft[$i]['is_radiant'] = $draft_instance['team'] ? 0 : 1;
            $t_draft[$i]['is_pick'] = $draft_instance['is_pick'];
            $t_draft[$i]['hero_id'] = $draft_instance['hero_id'];

            if ($draft_instance['is_pick']) {
                if ($draft_instance['order'] < 11) $t_draft[$i]['stage'] = 1;
                else if ($draft_instance['order'] < 15) $t_draft[$i]['stage'] = 2;
                else $t_draft[$i]['stage'] = 3;
            } else {
                $t_draft[$i]['stage'] = 1;
            }
            $t_draft[$i]['order'] = $i;
            $i++;
        }

        if ($t_match['radiant_opener'] === null) {
          $t_match['radiant_opener'] = $t_draft[0]['is_radiant'];
        }
    } else if (!empty($matchdata['picks_bans_stratz']) && ($matchdata['game_mode'] == 22 || $matchdata['game_mode'] == 3)) {
      foreach ($matchdata['picks_bans_stratz'] as $draft_instance) {
        // I'm skipping opendota picks_bans information since it lacks information on
        // who nominated hero to ban and is missing pick order info
        // altho I guess I should reconsider adding it later
        if (!isset($draft_instance['isRadiant'])) continue;
        if (!$draft_instance['isPick']) {
          if(!$draft_instance['wasBannedSuccessfully']) continue;
          $t_draft[$i]['matchid'] = $match;
          $t_draft[$i]['is_radiant'] = $draft_instance['isRadiant'];
          $t_draft[$i]['is_pick'] = 0;
          $t_draft[$i]['hero_id'] = $draft_instance['bannedHeroId'];
          $t_draft[$i]['stage'] = 1;
        } else {
          $t_draft[$i]['matchid'] = $match;
          $t_draft[$i]['is_radiant'] = $draft_instance['isRadiant'];
          $t_draft[$i]['is_pick'] = 1;
          $t_draft[$i]['hero_id'] = $draft_instance['heroId'];
          if ($draft_instance['order'] < 4) $t_draft[$i]['stage'] = 1;
          else if ($draft_instance['order'] < 8) $t_draft[$i]['stage'] = 2;
          else $t_draft[$i]['stage'] = 3;
        }
        $t_draft[$i]['order'] = $i;
        $i++;
      }
    }
    
    if (empty($t_draft)) {
      foreach($matchdata['players'] as $draft_instance) {
          if (!isset($draft_instance['hero_id']) || !$draft_instance['hero_id'])
            continue;
          $t_draft[$i]['matchid'] = $match;
          $t_draft[$i]['is_radiant'] = $draft_instance['isRadiant'];
          $t_draft[$i]['is_pick'] = 1;
          $t_draft[$i]['hero_id'] = $draft_instance['hero_id'];
          $t_draft[$i]['stage'] = 1;
          $t_draft[$i]['order'] = 0;
          $i++;
      }
    }
  }

  if (empty($t_items)) {
    $t_items = [];
    $meta['items'];
    $meta['item_categories'];

    if (!$bad_replay && !isset($matchdata['items'])) {
      $i = 0;
      for ($j=0, $sz=10; $j<$sz; $j++) {
        if (!isset($matchdata['players'][$j]['hero_id'])) {
          $sz++;
          continue;
        }
        $travel_boots_state = 0;
        $plog = [];

        foreach ($matchdata['players'][$j]['purchase_log'] as $e) {
          if ($matchdata['duration'] - $e['time'] < 60 || empty($e['time'])) continue;

          $r = [
            'matchid' => $match,
            'playerid' => $matchdata['players'][$j]['account_id'] ?? $t_matchlines[$i]['playerid'],
            'hero_id' => $matchdata['players'][$j]['hero_id'] ?? $t_matchlines[$i]['heroid']
          ];

          $item_id = 0;
          foreach ($meta['items'] as $id => $item_tag) {
            if ($item_tag == $e['key']) {
              $item_id = $id;
              break;
            }
          }
          if (!$item_id) continue;

          // boots of travel workaround
          if ($item_id == 47 && $travel_boots_state == 0) { $item_id = 48; $travel_boots_state++; }
          if ($item_id == 48 && $travel_boots_state == 0) continue;
          if ($item_id == 219 && $travel_boots_state == 1) { $item_id = 220; $travel_boots_state++; }
          if ($item_id == 220 && $travel_boots_state == 1) continue;

          $category = null;

          foreach($meta['item_categories'] as $category_name => $items) {
            if (in_array($item_id, $items)) {
              $category = $category_name;
              break;
            }
          }

          $plog[$item_id] = $e['time'];

          // should I disable consumables?
          if (in_array($category, ['support', 'consumables', 'parts', 'recipes', 'event']) ) { //&& $e['time'] > 0) {
            continue;
          }

          $r['item_id'] = $item_id;
          $r['category_id'] = array_search($category, array_keys($meta['item_categories']));
          $r['time'] = $e['time'];

          $t_items[] = $r;
        }

        // missing items workaround
        for ($k=0; $k<6; $k++) {
          if (!empty($matchdata['players'][$j]['item_'.$k]) && !isset($plog[ $matchdata['players'][$j]['item_'.$k] ])) {
            $iid = $matchdata['players'][$j]['item_'.$k];
            $cost = $meta['items_full'][ $iid ]['cost'];
            if (!$cost || in_array($iid, $meta['item_categories']['droppable'])) continue;

            foreach($meta['item_categories'] as $category_name => $items) {
              if (in_array($iid, $items)) {
                $category = $category_name;
                break;
              }
            }
  
            if (in_array($category, ['support', 'consumables', 'parts', 'recipes', 'event']) ) {
              continue;
            }

            asort($plog);

            $nw_last = 0;
            $time_last = 0;
            $tm = 0;
            foreach ($plog as $iid_p => $time) {
              $min_closest = $time < 0 ? 0 : ceil( $time/60 );
              $nw_new = $matchdata['players'][$j]['gold_t'][ $min_closest ] ?? end($matchdata['players'][$j]['gold_t']);
              $diff = ($nw_new - $nw_last)*0.75;

              $cost_self = $meta['items_full'][ $iid_p ]['cost'];

              if ($diff >= $cost_self+$cost) {
                $tm = round(60*($time_last + ($min_closest-$time_last)*($cost/$diff)));
                break;
              }

              $time_last = $min_closest;
              $nw_last = $nw_new;
            }
            
            if (!$tm) $tm = $matchdata['duration'];

            if ($iid == 133) {
              echo 1;
            }

            $t_items[] = [
              'matchid' => $match,
              'playerid' => $matchdata['players'][$j]['account_id'] ?? $t_matchlines[$i]['playerid'],
              'hero_id' => $matchdata['players'][$j]['hero_id'] ?? $t_matchlines[$i]['heroid'],
              'item_id' => $iid,
              'time' => $tm,
              'category_id' => array_search($category, array_keys($meta['item_categories'])),
            ];
          }
        }

        $i++;
      }
    }
  }

  // if (empty($t_runes) && !$bad_replay) {
  //   $t_runes = [];
  //   foreach ($matchdata['players'] as $player) {
  //     foreach ($player['runes_log'] as $rune) {
  //       $t_runes[] = [
  //         'matchid' => $match,
  //         'playerid' => $player['account_id'],
  //         'rune_code' => $rune['key'],
  //         'time' => $rune['key'],
  //       ];
  //     }
  //   }
  // }
  if (empty($t_starting_items) && !$bad_replay) {
    $t_starting_items = [];
    
    $items_flip = array_flip($meta['items']);
    foreach ($matchdata['players'] as $player) {
      if (empty($player['purchase_log'])) continue;

      $sti = [];
      foreach ($player['purchase_log'] as $item) {
        if ($item['time'] > 0) {
          break;
        }
        
        $sti[] = $items_flip[$item['key']];
      }

      $consumables = [
        'all' => [],
        '5m' => [],
        '10m' => [],
      ];

      foreach ($player['purchase_log'] as $item) {
        $iid = $items_flip[$item['key']];
        if (!in_array($iid, $meta['item_categories']['consumables'])) {
          continue;
        }
        
        if (!isset($consumables['all'][ $iid ])) {
          $consumables['all'][ $iid ] = 0;
        }
        $consumables['all'][ $iid ]++;

        if ($item['time'] < 600) {
          if (!isset($consumables['10m'][ $iid ])) {
            $consumables['10m'][ $iid ] = 0;
          }
          $consumables['10m'][ $iid ]++;
        }

        if ($item['time'] < 300) {
          if (!isset($consumables['5m'][ $iid ])) {
            $consumables['5m'][ $iid ] = 0;
          }
          $consumables['5m'][ $iid ]++;
        }
      }

      $t_starting_items[] = [
        'matchid' => $match,
        'playerid' => $player['account_id'],
        'hero_id' => $player['hero_id'],
        'starting_items' => addslashes(\json_encode($sti)),
        'consumables' => addslashes(\json_encode($consumables)),
      ];
    }
  }
  if (empty($t_skill_builds) && !$bad_replay) {
    // ability_upgrades_arr
    $t_skill_builds = [];
    foreach ($matchdata['players'] as $player) {
      if (empty($player['ability_upgrades_arr'])) continue;
      $sti = skillPriority($player['ability_upgrades_arr'], $player['hero_id'], $player['hero_id'] == 74);
      $t_skill_builds[] = [
        'matchid' => $match,
        'playerid' => $player['account_id'],
        'hero_id' => $player['hero_id'],
        'skill_build' => addslashes(\json_encode($player['ability_upgrades_arr'])),
        'first_point_at' => addslashes(\json_encode($sti['firstPointAt'])),
        'maxed_at' => addslashes(\json_encode($sti['maxedAt'])),
        'priority' => addslashes(\json_encode($sti['priority'])),
        'talents' => addslashes(\json_encode($sti['talents'])),
        'attributes' => addslashes(\json_encode($sti['attributes'])),
        'ultimate' => $sti['ultimate'],
      ];
    }
  }

  $n = array_search($match, $scheduled);
  if ($n !== FALSE) {
    unset($scheduled[$n]);
  }
  $n = array_search($match, $scheduled_stratz);
  if ($n !== FALSE) {
    unset($scheduled_stratz[$n]);
  }
  
  if (isset($first_scheduled[$match])) {
    unset($first_scheduled[$match]);
  }


  echo "..Recording.";

  if ($match_exists) {
    // remove match before readding it
    $sql = "DELETE from matchlines where matchid = $match;".
      "DELETE from adv_matchlines where matchid = $match;".
      "DELETE from draft where matchid = $match; ".
      ( $lg_settings['main']['items'] ? "delete from items where matchid = $match;" : "").
      ( $lg_settings['main']['teams'] ? "delete from teams_matches where matchid = $match;" : "").
      "delete from matches where matchid = $match;";

    if ($conn->multi_query($sql) === TRUE);
    else echo("[F] Unexpected problems when quering database.\n".$conn->error."\n");

    do {
        $conn->store_result();
    } while($conn->next_result());
  }

  // TODO:
  if(!empty($cache_dir) && !empty($matchdata) && $lrg_use_cache && !$bad_replay && !file_exists("$cache_dir/".$match.".lrgcache.json")) {
    //$f = fopen("$cache_dir/".($bad_replay ? "unparsed_" : "").$match.".json", "w");
    //fwrite($f, $json);
    //fclose($f);

    $matchdata = [
      'matches' => $t_match,
      'matchlines' => $t_matchlines,
      'draft' => $t_draft,
      'adv_matchlines' => $t_adv_matchlines,
      'items' => $t_items ?? [],
      'skill_builds' => $t_skill_builds,
      'starting_items' => $t_starting_items,
    ];

    $matchdata['players'] = [];
    foreach ($t_matchlines as $ml) {
      $matchdata['players'][] = [
        'playerID' => $ml['playerid'],
        'nickname' => $t_players[ $ml['playerid'] ] ?? $t_new_players[ $ml['playerid'] ],
      ];
    }

    if (!empty($t_team_matches)) {
      $matchdata['teams_matches'] = $t_team_matches;
      $matchdata['teams'] = [];

      foreach ($t_team_matches as $t) {
        $matchdata['teams'][] = [
          'teamid' => $t['teamid'],
          'name' => $t_teams[ $t['teamid'] ]['name'],
          'tag' => $t_teams[ $t['teamid'] ]['tag'],
        ];
      }
    }

    $json = json_encode($matchdata);
    if (!empty($json)) {
      file_put_contents("$cache_dir/".$match.".lrgcache.json", $json);
      echo("..Saved LRG cache.");
    }
  }

  $t_match['cluster']  = $match_rules['cluster']['rep'] ?? $lg_settings['force_cluster'] ?? $t_match['cluster'] ?? null;

  $sql = ""; $err_query = "";

  foreach ($t_new_players as $id => $player) {
    if (isset($t_players[$id])) {
      if ($update_names && !isset($updated_names[$id])) {
        $sql = "UPDATE players SET nickname = \"".addslashes($player)."\" WHERE playerID = ".$id.
          (($schema['players_fixname'] ?? false) ? ' AND name_fixed=0' : '').
        ";";
        echo ".";
        if ($conn->query($sql) === TRUE) $updated_names[$id] = $player;
        else {
          echo $conn->error."\n";
          if ($conn->error === "MySQL server has gone away") {
            sleep(30);
            conn_restart();
            $matches[] = $match;
            return false;
          }
        }
      }
      continue;
    }
    $player = mb_substr($player, 0, 127);
    $sql = "INSERT INTO players (playerID, nickname".
      (($schema['players_fixname'] ?? false) ? ',name_fixed' : '').
      ") VALUES (".$id.",\"".addslashes($player)."\"".
      (($schema['players_fixname'] ?? false) ? ',0' : '').
    ");";

    if ($conn->query($sql) === TRUE || stripos($conn->error, "Duplicate entry") !== FALSE) $t_players[$id] = $player;
    else {
      echo $conn->error."\n".$sql."\n";
      if ($conn->error === "MySQL server has gone away") {
        sleep(30);
        conn_restart();
        $matches[] = $match;
        return false;
      }
    }
  }

  if ($t_match['version'] < 0) {
    $t_match['version'] = $lastversion;
  }

  $sql = "INSERT INTO matches (
    matchid, radiantWin, duration, modeID, leagueID, start_date, ".
    (($schema['matches_opener'] ?? false) ? "analysis_status, radiant_opener, seriesid, " : "").
    "stomp, comeback, cluster, version
    ) VALUES "
    ."(".$t_match['matchid'].",".($t_match['radiantWin'] ? "true" : "false" ).",".$t_match['duration'].","
    .$t_match['modeID'].",".$t_match['leagueID'].",".$t_match['start_date'].",".
    (($schema['matches_opener'] ?? false) ? 
      ($t_match['analysis_status'] ?? (!empty($t_adv_matchlines) ? '1' : '0')).",".($t_match['radiant_opener'] ?? 'null').",".($t_match['seriesid'] ?? 'null')."," :
      ""
    ).
    ($t_match['stomp'] ?? 0).",".$t_match['comeback'].",".($t_match['cluster'] ?? 0).",".$t_match['version'].");";
  $err_query = "delete from matches where matchid = ".$t_match['matchid'].";";

  if ($conn->multi_query($sql) === TRUE);
  else {
    echo "ERROR matches (".$conn->error."), reverting match.\n$sql\n";
    if ($conn->error === "MySQL server has gone away") {
      sleep(30);
      conn_restart();
      $matches[] = $match;
    }
    $conn->multi_query($err_query);
    do {
      $conn->store_result();
    } while($conn->next_result());
    
    return null;
  }

  $sql = "INSERT INTO matchlines (matchid, playerid, heroid, level, isRadiant, kills, deaths, assists, networth,".
          "gpm, xpm, heal, heroDamage, towerDamage, lastHits, denies) VALUES ";
  foreach($t_matchlines as $ml) {
      $sql .= "\n\t(".$ml['matchid'].",".$ml['playerid'].",".$ml['heroid'].",".
          $ml['level'].",".($ml['isRadiant'] ? "true" : "false").",".$ml['kills'].",".
          $ml['deaths'].",".$ml['assists'].",".$ml['networth'].",".
          $ml['gpm'].",".$ml['xpm'].",".($ml['heal'] ?? 0).",".
          ($ml['heroDamage'] ?? 0).",".($ml['towerDamage'] ?? 0).",".$ml['lastHits'].",".
          $ml['denies']."),";
  }
  $sql[strlen($sql)-1] = ";";

  $err_query = "DELETE from matchlines where matchid = ".$t_match['matchid'].";".$err_query;

  if ($conn->multi_query($sql) === TRUE);
  else {
    echo "ERROR matchlines (".$conn->error."), reverting match.\n$sql\n";
    if ($conn->error === "MySQL server has gone away") {
      sleep(30);
      conn_restart();
      $matches[] = $match;
    }
    $conn->multi_query($err_query);
    do {
      $conn->store_result();
    } while($conn->next_result());
    return null;
  }

  if (!$bad_replay && !empty($t_adv_matchlines)) {
    $sql = " INSERT INTO adv_matchlines (matchid, playerid, heroid, lh_at10, isCore, lane, ".
        (($schema['adv_matchlines_roles'] ?? false) ? 'role, lane_won, ' : '').
        " efficiency_at10, wards, sentries, couriers_killed, roshans_killed, wards_destroyed, 
        multi_kill, streak, stacks, time_dead, buybacks, pings, stuns, teamfight_part, damage_taken) VALUES ";

    foreach($t_adv_matchlines as &$aml) {
      if (!isset($aml['role'])) {
        if ($aml['isCore']) $aml['role'] = $aml['lane'];
        else $aml['role'] = $aml['lane'] == 1 ? 5 : 4;
      }
    }
    foreach($t_adv_matchlines as &$aml) {
      if (!isset($aml['lane_won'])) {
        $tie_factor = 0.075;
        $opp = [];
        $self = 0;
        $side = null;

        foreach ($t_matchlines as $ml) {
          if ($ml['heroid'] == $aml['heroid']) {
            $side = $ml['isRadiant'];
            break;
          }
        }
        foreach ($t_matchlines as $ml) {
          if ($ml['isRadiant'] != $side) {
            $opp[] = $ml['heroid'];
          }
        }
        foreach ($t_adv_matchlines as $aml2) {
          if (!in_array($aml2['heroid'], $opp)) {
            if ($aml2['lane'] == $aml['lane'] && $aml2['isCore'] && $aml2['efficiency_at10'] > $self) {
              $self = $aml2['efficiency_at10'];
            }
          }
        }
        foreach ($t_adv_matchlines as $aml2) {
          if (in_array($aml2['heroid'], $opp)) {
            if (4-$aml2['lane'] == $aml['lane'] && $aml2['isCore']) {
              $diff = $self - $aml2['efficiency_at10'];
              $aml['lane_won'] = abs($diff) <= $tie_factor ? 1 : ($diff > 0 ? 2 : 0);
              break;
            }
          }
        }
        if (empty($aml['lane_won'])) {
          foreach ($t_adv_matchlines as $aml2) {
            if (in_array($aml2['heroid'], $opp)) {
              if ($aml2['role'] == $aml['role']) {
                if ($aml['role'] > 3) {
                  foreach ($t_adv_matchlines as $aml3) {
                    if (!in_array($aml2['heroid'], $opp)) {
                      if ($aml3['lane'] == $aml['lane'] && $aml3['isCore'] || $aml3['role'] == ($aml['role'] == 4 ? 3 : 1)) {
                        $self = $aml3['efficiency_at10'];
                      }
                    } else {
                      if ($aml3['lane'] == $aml2['lane'] && $aml3['isCore'] || $aml3['role'] == ($aml2['role'] == 4 ? 3 : 1)) {
                        $aml2['efficiency_at10'] = $aml3['efficiency_at10'];
                      }
                    }
                  }
                }
                $diff = $self - $aml2['efficiency_at10'];
                $aml['lane_won'] = abs($diff) <= $tie_factor ? 1 : ($diff > 0 ? 2 : 0);
              }
            }
          }
          if (empty($aml['lane_won'])) $aml['lane_won'] = 2;
        }
      }
    }
    foreach($t_adv_matchlines as &$aml) {
      $sql .= "\n\t(".$aml['matchid'].",".$aml['playerid'].",".$aml['heroid'].",".
                  ($aml['lh_at10'] ?? 0).",".$aml['isCore'].",".$aml['lane'].",".
                  (($schema['adv_matchlines_roles'] ?? false) ? $aml['role'].",".$aml['lane_won']."," : '').
                  $aml['efficiency_at10'].",".($aml['wards'] ?? 0).",".($aml['sentries'] ?? 0).",".
                  $aml['couriers_killed'].",".$aml['roshans_killed'].",".$aml['wards_destroyed'].",".
                  $aml['multi_kill'].",".$aml['streak'].",".($aml['stacks'] ?? 0).",".
                  ($aml['time_dead'] ?? 0).",".($aml['buybacks'] ?? 0).",".$aml['pings'].",".
                  $aml['stuns'].",".$aml['teamfight_part'].",".$aml['damage_taken']."),";
    }
    $sql[strlen($sql)-1] = ";";

    $err_query = "DELETE from adv_matchlines where matchid = ".$t_match['matchid'].";".$err_query;

    if ($conn->multi_query($sql) === TRUE);
    else {
      echo "ERROR adv_matchlines (".$conn->error."), reverting match.\n$sql\n";
      if ($conn->error === "MySQL server has gone away") {
        sleep(30);
        conn_restart();
        $matches[] = $match;
      }
      $conn->multi_query($err_query);
      do {
        $conn->store_result();
      } while($conn->next_result());
      return null;
    }
  }

  if(!empty($t_draft)) {
      $sql = " INSERT INTO draft (matchid, is_radiant, is_pick, hero_id, stage".
        (($schema['draft_order'] ?? false) ? ", `order`" : '').
      ") VALUES ";
      $len = sizeof($t_draft);
      for($i = 0; $i < $len; $i++) {
          $sql .= "\n\t(".$t_draft[$i]['matchid'].",".($t_draft[$i]['is_radiant'] ? "true" : "false").",".
            ($t_draft[$i]['is_pick'] ? "true" : "false").",".
            $t_draft[$i]['hero_id'].",".$t_draft[$i]['stage'].
            (($schema['draft_order'] ?? false) ? ",".($t_draft[$i]['order'] ?? $i) : '').
          "),";
      }
      $sql[strlen($sql)-1] = ";";

      $err_query = "DELETE from draft where matchid = ".$t_match['matchid'].";".$err_query;

      if ($conn->multi_query($sql) === TRUE);
      else {
        echo "ERROR draft (".$conn->error."), reverting match.\n".$sql;
        if ($conn->error === "MySQL server has gone away") {
          sleep(30);
          conn_restart();
          $matches[] = $match;
        }
        $conn->multi_query($err_query);
        do {
          $conn->store_result();
        } while($conn->next_result());
        return null;
      }
  }

  if(!empty($t_items) && $lg_settings['main']['items']) {
    if ($lg_settings['main']['itemslines']) {
      $sql = " INSERT INTO itemslines (matchid, hero_id, playerid, items) VALUES ";
      $t_itemslines = [];
      foreach ($t_items as $item) {
        if (!isset($t_itemslines[ $item['playerid'] ])) {
          $t_itemslines[ $item['playerid'] ] = [
            'matchid' => $item['matchid'],
            'hero_id' => $item['hero_id'],
            'playerid' => $item['playerid'],
            'items' => []
          ];
        }
        $t_itemslines[ $item['playerid'] ]['items'][] = [
          'i' => (int)$item['item_id'],
          'c' => (int)$item['category_id'],
          't' => (int)$item['time'],
        ];
      }
      foreach ($t_itemslines as $t) {
        $sql .= "\n\t({$t['matchid']}, {$t['hero_id']}, {$t['playerid']}, '".json_encode($t['items'])."'),";
      }
      $sql[strlen($sql)-1] = ";";

      $err_query = "DELETE from itemslines where matchid = ".$t_match['matchid'].";".$err_query;
    } else {
      $sql = " INSERT INTO items (matchid, hero_id, playerid, item_id, category_id, `time`) VALUES ";
      $len = sizeof($t_items);
      for($i = 0; $i < $len; $i++) {
          $sql .= "\n\t(".$t_items[$i]['matchid'].",".
              $t_items[$i]['hero_id'].",".
              $t_items[$i]['playerid'].",".
              $t_items[$i]['item_id'].",".
              $t_items[$i]['category_id'].",".
              $t_items[$i]['time']."),";
      }
      $sql[strlen($sql)-1] = ";";

      $err_query = "DELETE from items where matchid = ".$t_match['matchid'].";".$err_query;
    }

    if ($conn->multi_query($sql) === TRUE);
    else {
      echo "ERROR items (".$conn->error."), reverting match.\n".$sql;
      if ($conn->error === "MySQL server has gone away") {
        sleep(30);
        conn_restart();
        $matches[] = $match;
      }
      $conn->multi_query($err_query);
      do {
        $conn->store_result();
      } while($conn->next_result());
      return null;
    }
  }

  if(!empty($t_skill_builds) && ($schema['skill_builds'] ?? false)) {
    $sql = " INSERT INTO skill_builds (matchid, playerid, hero_id, 
      skill_build, first_point_at, maxed_at, priority, talents".
        ($schema['skill_build_attr'] ? ", attributes, ultimate" : "").
      ") VALUES ";
    foreach ($t_skill_builds as $t) {
      $sql .= "\n\t({$t['matchid']}, {$t['playerid']}, {$t['hero_id']}, ".
        "'".($t['skill_build'])."',".
        "'".($t['first_point_at'])."',".
        "'".($t['maxed_at'])."',".
        "'".($t['priority'])."',".
        "'".($t['talents'])."'".
        ($schema['skill_build_attr']
        ? ",".($t['attributes'] ? "'".$t['attributes']."'" : "null").",".
          "".($t['ultimate'] ?? 'null').""
        : "").
      "),";
    }
    $sql[strlen($sql)-1] = ";";

    $err_query = "DELETE from skill_builds where matchid = ".$t_match['matchid'].";".$err_query;

    if ($conn->multi_query($sql) === TRUE);
    else {
      echo "ERROR skill_builds (".$conn->error."), reverting match.\n";
      if ($conn->error === "MySQL server has gone away") {
        sleep(30);
        conn_restart();
        $matches[] = $match;
      }
      $conn->multi_query($err_query);
      do {
        $conn->store_result();
      } while($conn->next_result());
      return null;
    }
  }

  if(!empty($t_starting_items) && ($schema['starting_items'] ?? false)) {
    $sql = " INSERT INTO starting_items (matchid, playerid, hero_id, starting_items".
      ($schema['starting_consumables'] ? ", consumables" : "").
    ") VALUES ";
    foreach ($t_starting_items as $t) {
      $sql .= "\n\t({$t['matchid']}, {$t['playerid']}, {$t['hero_id']}, ".
        "'".($t['starting_items'])."'".
        ($schema['starting_consumables'] ? ", '".($t['consumables'])."'" : "").
      "),";
    }
    $sql[strlen($sql)-1] = ";";

    $err_query = "DELETE from starting_items where matchid = ".$t_match['matchid'].";".$err_query;

    if ($conn->multi_query($sql) === TRUE);
    else {
      echo "ERROR starting_items (".$conn->error."), reverting match.\n";
      if ($conn->error === "MySQL server has gone away") {
        sleep(30);
        conn_restart();
        $matches[] = $match;
      }
      $conn->multi_query($err_query);
      do {
        $conn->store_result();
      } while($conn->next_result());
      return null;
    }
  }

  if ($lg_settings['main']['teams'] && sizeof($t_team_matches)) {
    $sql = "INSERT INTO teams_matches (matchid, teamid, is_radiant) VALUES ";

    foreach($t_team_matches as $m) {
        if($m['is_radiant'] > 1) {
          echo "[W] Error when adding teams-matches data: is_radiant flag has higher value than 1\n".
              "[ ]\t".$m['matchid']." - ".$m['teamid']." - ".$m['is_radiant']."\n";
              continue;
        }
        $sql .= "\n\t(".$m['matchid'].",".$m['teamid'].",".$m['is_radiant']."),";
    }
    $sql[strlen($sql)-1] = ";";

    $err_query = "DELETE from teams_matches where matchid = ".$t_match['matchid'].";".$err_query;

    if ($conn->multi_query($sql) === TRUE);
    else {
      echo "ERROR teams_matches (".$conn->error."), reverting match.\n";
      if ($conn->error === "MySQL server has gone away") {
        sleep(30);
        conn_restart();
        $matches[] = $match;
      }
      $conn->multi_query($err_query);
      do {
        $conn->store_result();
      } while($conn->next_result());
      return null;
    }
  }

  echo "..OK.\n";

  if ($lg_settings['main']['teams']) {
    $newteams = array();
    foreach($t_teams as $id => $team) {
      if($team['added']) continue;
      $newteams[$id] = $team;
    }
    if(sizeof($newteams)) {
      $sql = "INSERT INTO teams (teamid, name, tag) VALUES \n";
      foreach ($newteams as $id => $team) {
        $team['name'] = mb_substr($team['name'], 0, 48); // 48 = team name field length -2
        $team['tag'] = mb_substr($team['tag'], 0, 23); // 23 = team tag field length -2
        // I shouldn't use fixed varchar size in first place though
        $sql .= "\n\t(".$id.",\"".addslashes($team['name'])."\",\"".addslashes($team['tag'])."\"),";
      }
      $sql[strlen($sql)-1] = ";";
  
      if ($conn->multi_query($sql) === TRUE || stripos($conn->error, "Duplicate entry") !== FALSE);
      else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n".$sql."\n");
  
      if($lg_settings['ana']['teams']['rosters']) {
        //echo "[ ] Getting teams rosters\n";
  
        $sql = "";
  
        foreach ($newteams as $id => $team) {
          $json = file_get_contents('https://api.steampowered.com/IDOTA2Match_570/GetTeamInfoByTeamID/v001/?key='.$steamapikey.'&teams_requested=1&start_at_team_id='.$id);
          $matchdata = json_decode($json, true);
          # it may return more than 5 players, but we actually care only about the first 5 players
          # others are probably coach and standins, they aren't part of official active roster
  
          # initial idea about positions was to detect player position somehow and use it in team competitions
          # to detect heros stats based on player positions
          # right now it's placeholder
          # TODO
          $position = 0;
  
          for($i=0; isset($matchdata['result']['teams'][0]['player_'.$i.'_account_id']); $i++)
              $sql .= "\n\t(".$id.",".$matchdata['result']['teams'][0]['player_'.$i.'_account_id'].", ".$position."),";
        }
  
        if(!empty($sql)) {
          $sql[strlen($sql)-1] = ";";
          $sql = "INSERT INTO teams_rosters (teamid, playerid, position) VALUES ".$sql;
  
              if ($conn->multi_query($sql) === TRUE || stripos($conn->error, "Duplicate entry") !== FALSE) echo "[S] Successfully recorded new teams rosters to database.\n";
              else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n");
        }
      }

      foreach ($newteams as $id => $team) {
        $t_teams[$id]['added'] = true;
      }
    }
  }

  if ($match && isset($first_scheduled[$match]))
    unset($first_scheduled[$match]);

  $k = array_search($match, $scheduled);
  if ($k !== FALSE)
    unset($scheduled[$k]);
  $k = array_search($match, $scheduled_stratz);
  if ($k !== FALSE)
    unset($scheduled_stratz[$k]);

  return true;
}
