<?php 

function estimate_players_bans($matches, $r_player_pos, &$players_pb, &$draft_context, &$pickban_context, &$pickban_ctx_alt, $pd_context) {
  global $report;

  $pdb = [ 1 => [], 2 => [], 3 => [] ];

  foreach ($matches as $player => $phs) {
    $pdb[1][$player] = $pdb[2][$player] = $pdb[3][$player] = [
      'playerid' => $player,
      'matches' => 0,
      'winrate' => 0,
      'wins' => 0,
    ];
  }

  $uncertain = [];
  $match_unc = [];
  $wins_unc = [];

  // 1. I go through all heroes banned vs the team and compare them to total stats. (ban's draft stage affects the result)
  if (!isset($draft_context[0])) return $pdb;
  foreach ($draft_context[0] as $stage => $heroes) {
    foreach ($heroes as $hero => $el) {
      $hid = $el['heroid'];
      $ms = $el['matches'];

      $candidates = [];

      if (!empty($pickban_context[$hid]) && $pickban_context[$hid]['matches_picked']) {
        foreach ($matches as $player => $phs) {
          if (isset($phs[$hid])) {
            $candidates[$player] = $phs[$hid]['c']/$pickban_context[$hid]['matches_picked'];
          }
        }

        // 1.1. If the hero has picks and was picked by the team -> see who played it.
        //      If there is uncertainty (multiple players played the hero), then this hero is added to "uncertain" bucket.
        if (!empty($candidates)) {
          foreach ($candidates as $player => $rate) {
            $pdb[$stage][$player]['matches'] += round($ms * $rate);
            $pdb[$stage][$player]['wins'] += round($el['winrate'] * $ms * $rate);
          }
        } else {
          $uncertain[$hid."|".$stage] = [];
          $match_unc[$hid."|".$stage] = $ms;
          $wins_unc[$hid."|".$stage] = $el['winrate'] * $ms;
        }
      } else if (!empty($pickban_context[$hid]) && $pickban_context[$hid]['matches_picked']) {
        // 1.2. if the hero was not picked by the team, but was generally picked -> see what roles this hero appeared in.
        if (isset($report['hero_positions']) && isset($report['pickban'][$hid]['matches_picked'])) {
          $roles = [];
          for ($isCore = 0; $isCore <= 1; $isCore++) {
            for ($lane = 1; $lane <= 3; $lane++) {
              if (isset($report['hero_positions'][$isCore][$lane])) {
                $roles[$isCore.'.'.$lane] = $report['hero_positions'][$isCore][$lane]['matches_s']/$report['pickban'][$hid]['matches_picked'];
              }
            }
          }

          arsort($roles);

          $candidates = [];
          foreach ($roles as $role => $rate) {
            foreach ($r_player_pos[$role] as $player => $ratio) {
              $pdb[$stage][$player]['matches'] += round($ms * $ratio);
              $pdb[$stage][$player]['wins'] += round($el['winrate'] * $ms * $ratio);
            }
          }
        } else {
          $uncertain[$hid."|".$stage] = [];
          $match_unc[$hid."|".$stage] = $ms;
          $wins_unc[$hid."|".$stage] = $el['winrate'] * $ms;
        }
      } else {
        // 1.3. if the hero was not picked at all -> compare bans against this team to total bans.
        //      If the result is above the threshold -> uncertain bucket, else => meta ban
        
        $ratio = (isset($report['pickban'][$hid]['matches_banned']) && $report['pickban'][$hid]['matches_banned']) ? 
          (isset($pickban_ctx_alt[$hid]['matches_banned']) ? $pickban_ctx_alt[$hid]['matches_banned'] / $report['pickban'][$hid]['matches_banned'] : 0) : 0;

        if ($ratio > 0.2) {
          $uncertain[$hid."|".$stage] = [];
          $match_unc[$hid."|".$stage] = $ms;
          $wins_unc[$hid."|".$stage] = $el['winrate'] * $ms;
        }
      }
    }
  }

  // 2. If uncertain bucket is not empty, start going through all matches, played by this team.
  //    If the match has an uncertain hero banned, see who had their hero picked already and remove them from potential cancidates.
  //    Then divide the number of bans between the remaining players, according to the usual pick stage of their heroes (closer to the ban's stage = better).

  foreach ($uncertain as $code => $candidates) {
    [ $hid, $stage ] = explode('|', $code);

    for ($i = +$stage; $i <= 3; $i++) {
      if (!isset($pd_context[1][$i])) continue;
      foreach ($pd_context[1][$i] as $el) {
        if (isset($el[$hid])) {
          if (!isset($candidates[$el['playerid']])) {
            $candidates[$el['playerid']] = 0;
          }
          $candidates[$el['playerid']] += $el['matches'];
        }
      }
    }
    foreach ($candidates as $player => $matches) {
      if (!isset($players_pb[$player]['matches_picked']) || !$players_pb[$player]['matches_picked']) continue;
      $candidates[$player] = $matches / $players_pb[$player]['matches_picked'];

      $pdb[$stage][$candidates[$i]]['matches'] += round($match_unc[$code] * $candidates[$player]);
      $pdb[$stage][$candidates[$i]]['wins'] += round($wins_unc[$code] * $pdb[$stage][$candidates[$i]]['matches']);
    }
  }

  foreach ($matches as $player => $phs) {
    $wins_old = (isset($players_pb[$player]['matches_banned']) && $players_pb[$player]['matches_banned']) ? 
      round($players_pb[$player]['matches_banned']*($players_pb[$player]['winrate_banned'] ?? 0)) : 0;
    $players_pb[$player]['matches_banned'] = ($players_pb[$player]['matches_banned'] ?? 0) 
      + ($pdb[1][$player]['matches'] ?? 0)
      + ($pdb[2][$player]['matches'] ?? 0)
      + ($pdb[3][$player]['matches'] ?? 0);
    $wins = ($pdb[1][$player]['wins'] ?? 0) + ($pdb[2][$player]['wins'] ?? 0) + ($pdb[3][$player]['wins'] ?? 0);
    $players_pb[$player]['winrate_banned'] = $players_pb[$player]['matches_banned'] ? ($wins + $wins_old) / $players_pb[$player]['matches_banned'] : 0;

    if (isset($pdb[1][$player])) {
      $pdb[1][$player]['winrate'] = $pdb[1][$player]['matches'] ? $pdb[1][$player]['wins'] / $pdb[1][$player]['matches'] : 0;
      unset($pdb[1][$player]['wins']);
    }
    if (isset($pdb[2][$player])) {
      $pdb[2][$player]['winrate'] = $pdb[2][$player]['matches'] ? $pdb[2][$player]['wins'] / $pdb[2][$player]['matches'] : 0;
      unset($pdb[2][$player]['wins']);
    }
    if (isset($pdb[3][$player])) {
      $pdb[3][$player]['winrate'] = $pdb[3][$player]['matches'] ? $pdb[3][$player]['wins'] / $pdb[3][$player]['matches'] : 0;
      unset($pdb[3][$player]['wins']);
    }
  }

  foreach ($pdb as $stage => $players) {
    $pdb[$stage] = array_values($players);
  }

  return $pdb;
}

function estimate_players_draft_processor_tvt_report(&$context_pickban, $tid = null) {
  global $report;

  // populate players matches on heroes
  $matches_teams = [];
  $player_pos = [];
  $r_player_pos_teams = [];
  $teams_pids = [];

  foreach ($report['teams'] as $tid => $el) {
    if (!isset($el['players_draft_pb'])) continue;
    $pids = array_keys($el['players_draft_pb']);
    $teams_pids[$tid] = $pids;
    $matches_teams[$tid] = [];
    $r_player_pos_teams[$tid] = [];

    foreach($el['players_draft_pb'] as $player => $ppb) {
      if (!isset($report['players'][$player])) continue;
      if (!empty($report['players_additional']) && isset($report['players_additional'][$player]['positions'])) {
        $player_pos[$player] = reset($report['players_additional'][$player]['positions']);

        foreach ($report['players_additional'][$player]['positions'] as $pos) {
          $roletag = $pos['core'].'.'.$pos['lane'];
          if (empty($r_player_pos_teams[$tid][$roletag])) {
            $r_player_pos_teams[$tid][$roletag] = [];
          }
          if (isset($report['players_additional'][$player]['matches']) && $report['players_additional'][$player]['matches']) {
            $r_player_pos_teams[$tid][$roletag][$player] = $pos['matches'] / $report['players_additional'][$player]['matches'];
          }
        }
      }
      $matches_teams[$tid][ $player ] = [];
    }
  }
  
  foreach($report['matches'] as $match => $ll) {
    $radiant_tid = $report['match_participants_teams'][$match]['radiant'] ?? 0;
    $dire_tid = $report['match_participants_teams'][$match]['dire'] ?? 0;

    foreach ($ll as $l) {
      $t = +$l['radiant'] ? $radiant_tid : $dire_tid;
      if (!isset($matches_teams[$t])) {
        $matches_teams[$t] = [];
      }
      if (!isset($matches_teams[$t][ $l['player'] ])) {
        $matches_teams[$t][ $l['player'] ] = [];
      }
      if (!isset($matches_teams[$t][ $l['player'] ][ $l['hero'] ])) $matches_teams[$t][ $l['player'] ][ $l['hero'] ] = [ 'ms' => [], 'c' => 0, 'w' => 0 ];
      $matches_teams[$t][ $l['player'] ][ $l['hero'] ]['ms'][] = $match;
      $matches_teams[$t][ $l['player'] ][ $l['hero'] ]['c']++;
      if (isset($report['matches_additional'][$match]['radiant_win'])) {
        $matches_teams[$t][ $l['player'] ][ $l['hero'] ]['w'] += ($report['matches_additional'][$match]['radiant_win'] == $l['radiant']);
      }
    }
  }

  if (!empty($matches_teams[0])) {
    $team_0_draft = $report['draft'] ?? [];
    $team_0_pickban = $report['pickban'] ?? [];
    $team_0_pd = $report['players_draft'] ?? [];
    $team_0_pd_pb = $report['players_draft_pb'] ?? [];

    foreach ($report['teams'] as $tid => $el) {
      if (!isset($el['draft'])) continue;
      foreach ($el['draft'] as $stage => $heroes) {
        foreach ($heroes as $hero => $el) {
          if (!isset($team_0_draft[$stage][$hero]['winrate_banned']) || !isset($team_0_draft[$stage][$hero]['matches_banned'])) continue;
          $wins = $team_0_draft[$stage][$hero]['winrate_banned']*$team_0_draft[$stage][$hero]['matches_banned'];
          $team_0_draft[$stage][$hero]['winrate_banned'] = ($wins - $el['winrate_banned']*$el['matches_banned'])
            / ($team_0_draft[$stage][$hero]['matches_banned'] - $el['matches_banned']);
          $team_0_draft[$stage][$hero]['matches_banned'] -= $el['matches_banned'];
        }
      }
      if (!isset($el['pickban'])) continue;
      foreach ($el['pickban'] as $hero => $el) {
        if (!isset($team_0_pickban[$hero])) continue;
        if (isset($team_0_pickban[$hero]['winrate_banned']) && isset($team_0_pickban[$hero]['matches_banned'])) {
          $denom = $team_0_pickban[$hero]['matches_banned'] - $el['matches_banned'];
          $team_0_pickban[$hero]['winrate_banned'] = $denom ? ($team_0_pickban[$hero]['winrate_banned']*$team_0_pickban[$hero]['matches_banned']
             - $el['winrate_banned']*$el['matches_banned'])
            / $denom : 0;
          $team_0_pickban[$hero]['matches_banned'] -= $el['matches_banned'];
        }

        if (isset($team_0_pickban[$hero]['winrate_picked']) && isset($team_0_pickban[$hero]['matches_picked'])) {
          $team_0_pickban[$hero]['winrate_picked'] = ($team_0_pickban[$hero]['winrate_picked']*$team_0_pickban[$hero]['matches_picked']
             - $el['winrate_picked']*$el['matches_picked'])
            / ($team_0_pickban[$hero]['matches_picked'] - $el['matches_picked']);
          $team_0_pickban[$hero]['matches_picked'] -= $el['matches_picked'];
        }
      }

      if (!isset($el['players_draft_pb'])) continue;
      foreach ($el['players_draft_pb'] as $stage => $heroes) {
        foreach ($heroes as $hero => $el) {
          if (!isset($team_0_pd_pb[$hero]) || !isset($team_0_pd_pb[$hero]['winrate_picked']) || !isset($team_0_pd_pb[$hero]['matches_picked'])) continue;
          $team_0_pd_pb[$hero]['matches_picked'] -= $el['matches_picked'];
          $team_0_pd_pb[$hero]['winrate_picked'] = ($team_0_pd_pb[$hero]['winrate_picked']*$team_0_pd_pb[$hero]['matches_picked']
            - $el['winrate_picked']*$el['matches_picked'])
            / ($team_0_pd_pb[$hero]['matches_picked'] - $el['matches_picked']);
          $team_0_pd_pb[$hero]['matches_picked'] -= $el['matches_picked'];
        }
      }

      if (!isset($el['players_draft'])) continue;
      foreach ($el['players_draft'] as $stage => $pls) {
        foreach ($pls as $pid => $el) {
          if (!isset($team_0_pd[$stage][$pid]['winrate_picked']) || !isset($team_0_pd[$stage][$pid]['matches_picked'])) continue;
          $team_0_pd[$stage][$pid]['winrate_picked'] = ($team_0_pd[$stage][$pid]['winrate_picked']*$team_0_pd[$stage][$pid]['matches_picked']
            - $el['winrate_picked']*$el['matches_picked'])
            / ($team_0_pd[$stage][$pid]['matches_picked'] - $el['matches_picked']);
          $team_0_pd[$stage][$pid]['matches_picked'] -= $el['matches_picked'];
        }
      }
    }

    $report['teams'][0] = [
      'draft_vs' => $team_0_draft,
      'pickban' => $team_0_pickban,
      'players_draft' => $team_0_pd,
      'players_draft_pb' => $team_0_pd_pb,
    ];
  }

  $pds = [];
  foreach ($matches_teams as $tid => $el) {
    if (!isset($report['teams'][$tid])) continue;
    $r_player_pos_ref = $r_player_pos_teams[$tid] ?? [];
    $players_draft_pb_ref = $report['teams'][$tid]['players_draft_pb'] ?? [];
    $draft_vs_ref = $report['teams'][$tid]['draft_vs'] ?? [];
    $pickban_ref = $report['teams'][$tid]['pickban'] ?? [];
    $pickban_vs_ref = $report['teams'][$tid]['pickban_vs'] ?? [];
    $players_draft_ref = $report['teams'][$tid]['players_draft'] ?? [];
    $pds[$tid] = estimate_players_bans(
      $el,
      $r_player_pos_ref,
      $players_draft_pb_ref,
      $draft_vs_ref,
      $pickban_ref,
      $pickban_vs_ref,
      $players_draft_ref,
    );

    foreach ($pds[$tid] as $stage => $pls) {
      foreach ($pls as $el) {
        if (!isset($context_pickban[$el['playerid']])) continue;

        if (!isset($report['players_draft'][0][$stage][$el['playerid']])) {
          $report['players_draft'][0][$stage][$el['playerid']] = $el;
          $report['players_draft'][0][$stage][$el['playerid']]['wins'] = $el['matches'] * $el['winrate'];
        } else {
          $report['players_draft'][0][$stage][$el['playerid']]['matches'] += $el['matches'];
          $report['players_draft'][0][$stage][$el['playerid']]['wins'] += $el['matches'] * $el['winrate'];
        }
      }
    }

    if (isset($report['teams'][$tid]['players_draft_pb'])) {
      foreach ($report['teams'][$tid]['players_draft_pb'] as $pid => $el) {
        if (!isset($context_pickban[$pid])) continue;

        $ms = (($context_pickban[$pid]['matches_banned'] ?? 0) + ($el['matches_banned'] ?? 0));
        $context_pickban[$pid]['winrate_banned'] = $ms ? (
          (($context_pickban[$pid]['winrate_banned'] ?? 0)*($context_pickban[$pid]['matches_banned'] ?? 0)
          + ($el['winrate_banned'] ?? 0)*($el['matches_banned'] ?? 0))
          / $ms) : 0;
        $context_pickban[$pid]['matches_banned'] = $ms;
      }
    }
  }

  unset($report['teams'][0]);

  if (isset($report['players_draft'][0])) {
    foreach ($report['players_draft'][0] as $stage => $pls) {
      foreach ($pls as $pid => $el) {
        $report['players_draft'][0][$stage][$pid]['winrate'] = $report['players_draft'][0][$stage][$pid]['matches'] ? 
          ($report['players_draft'][0][$stage][$pid]['wins'] ?? 0) / $report['players_draft'][0][$stage][$pid]['matches'] : 0;
        unset($report['players_draft'][0][$stage][$pid]['wins']);
      }
      $report['players_draft'][0][$stage] = array_values($report['players_draft'][0][$stage]);
    }
  }
}

function estimate_players_draft_processor_pvp_report(&$context_pickban) {
  global $report;

  $matches = [];
  $player_pos = [];
  $r_player_pos = [];
  // $bans_vs = [];

  foreach($context_pickban as $player => $ppb) {
    if (!isset($report['players'][$player])) continue;
    if (!empty($report['players_additional']) && isset($report['players_additional'][$player]['positions'])) {
      $player_pos[$player] = reset($report['players_additional'][$player]['positions']);

      foreach ($report['players_additional'][$player]['positions'] as $pos) {
        $roletag = $pos['core'].'.'.$pos['lane'];
        if (empty($r_player_pos[$roletag])) {
          $r_player_pos[$roletag] = [];
        }
        if (isset($report['players_additional'][$player]['matches']) && $report['players_additional'][$player]['matches']) {
          $r_player_pos[$roletag][$player] = $pos['matches'] / $report['players_additional'][$player]['matches'];
        }
      }
    }
    $matches[ $player ] = [];
  }

  foreach($report['matches'] as $match => $ll) {
    foreach ($ll as $l) {
      if (!isset($matches[ $l['player'] ])) {
        $matches[ $l['player'] ] = [];
      }
      if (!isset($matches[ $l['player'] ][ $l['hero'] ])) $matches[ $l['player'] ][ $l['hero'] ] = [ 'ms' => [], 'c' => 0, 'w' => 0 ];
      $matches[ $l['player'] ][ $l['hero'] ]['ms'][] = $match;
      $matches[ $l['player'] ][ $l['hero'] ]['c']++;
      if (isset($report['matches_additional'][$match]['radiant_win'])) {
        $matches[ $l['player'] ][ $l['hero'] ]['w'] += ($report['matches_additional'][$match]['radiant_win'] == $l['radiant']);
      }
    }
  }

  $draft_ref = $report['draft'] ?? [];
  $pickban_ref1 = $report['pickban'] ?? [];
  $pickban_ref2 = $report['pickban'] ?? [];
  $pds = estimate_players_bans(
    $matches,
    $r_player_pos,
    $context_pickban,
    $draft_ref,
    $pickban_ref1,
    $pickban_ref2,
    $report['players_draft'] ?? [],
  );

  $report['players_draft'][0] = $pds;

  if (isset($report['players_draft'][0])) {
    foreach ($report['players_draft'][0] as $stage => $pls) {
    foreach ($pls as $i => $el) {
      $report['players_draft'][0][$stage][$i]['matches'] = round($report['players_draft'][0][$stage][$i]['matches'] / 2);
    }
  }
  }

  foreach ($context_pickban as $pid => $el) {
    if (isset($context_pickban[$pid]['matches_banned'])) {
      $context_pickban[$pid]['matches_banned'] = round($context_pickban[$pid]['matches_banned'] / 2);
    }
  }
}

function estimate_players_draft_processor_tvt_single_team(&$context, $tid) {
  global $report;
  
  // populate players matches on heroes
  $matches = [];
  $player_pos = [];
  $r_player_pos = [];
  if (!isset($context[$tid]['players_draft_pb'])) return;
  $pids = array_keys($context[$tid]['players_draft_pb']);
  foreach($context[$tid]['players_draft_pb'] as $player => $ppb) {
    if (!isset($report['players'][$player])) continue;
    if (!empty($report['players_additional']) && isset($report['players_additional'][$player]['positions'])) {
      $player_pos[$player] = reset($report['players_additional'][$player]['positions']);

      foreach ($report['players_additional'][$player]['positions'] as $pos) {
        $roletag = $pos['core'].'.'.$pos['lane'];
        if (empty($r_player_pos[$roletag])) {
          $r_player_pos[$roletag] = [];
        }
        if (isset($report['players_additional'][$player]['matches']) && $report['players_additional'][$player]['matches']) {
          $r_player_pos[$roletag][$player] = $pos['matches'] / $report['players_additional'][$player]['matches'];
        }
      }
    }
    $matches[ $player ] = [];
  }
  foreach($report['matches'] as $match => $ll) {
    if (!in_array($match, $context[$tid]['matches'])) {
      $_plrs = array_map(function($v) { return $v['player']; }, $ll);

      if (empty(array_intersect($pids, $_plrs))) {
        continue;
      }
    }

    $radiant = ( $report['match_participants_teams'][$match]['radiant'] ?? 0 ) == $tid ? 1 : 0;
    foreach ($ll as $l) {
      if ($l['radiant'] != $radiant) continue;
      if (!isset($matches[ $l['player'] ])) continue;
      if (!isset($matches[ $l['player'] ][ $l['hero'] ])) $matches[ $l['player'] ][ $l['hero'] ] = [ 'ms' => [], 'c' => 0, 'w' => 0 ];
      $matches[ $l['player'] ][ $l['hero'] ]['ms'][] = $match;
      $matches[ $l['player'] ][ $l['hero'] ]['c']++;
      if (isset($report['matches_additional'][$match]['radiant_win'])) {
        $matches[ $l['player'] ][ $l['hero'] ]['w'] += ($report['matches_additional'][$match]['radiant_win'] == $radiant);
      }
    }
  }

  foreach ($matches as $player => $phs) {
    $pdb[1][$player] = $pdb[2][$player] = $pdb[3][$player] = [
      'playerid' => $player,
      'matches' => 0,
      'winrate' => 0,
      'wins' => 0,
    ];
  }

  $players_draft_pb_ref = $context[$tid]['players_draft_pb'] ?? [];
  $draft_vs_ref = $context[$tid]['draft_vs'] ?? [];
  $pickban_ref = $context[$tid]['pickban'] ?? [];
  $pickban_vs_ref = $context[$tid]['pickban_vs'] ?? [];
  $pdb = estimate_players_bans(
    $matches,
    $r_player_pos,
    $players_draft_pb_ref,
    $draft_vs_ref,
    $pickban_ref,
    $pickban_vs_ref,
    $context[$tid]['players_draft'] ?? [],
  );

  $context[$tid]['players_draft'][0] = $pdb;
}