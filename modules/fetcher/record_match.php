<?php

$t_match['cluster'] = $match_rules['cluster']['rep'] ?? $lg_settings['force_cluster'] ?? $t_match['cluster'] ?? null;

if ($t_match['version'] < 0) {
  $t_match['version'] = $lastversion;
}

if (($lg_settings['main']['normalize_turbo'] ?? true) && ($t_match['modeID'] == 23)) {
  $t_match['duration'] *= 2;

  foreach ($t_matchlines as $i => $line) {
    $t_matchlines[$i]['gpm'] = round($line['gpm'] / 2);
    $t_matchlines[$i]['xpm'] = round($line['xpm'] / 2);
    $t_matchlines[$i]['lastHits'] *= 2;
    $t_matchlines[$i]['denies'] *= 2;
  }

  if (!empty($t_adv_matchlines)) {
    foreach ($t_adv_matchlines as $i => $line) {
      $t_adv_matchlines[$i]['lh_at10'] *= 2;
      $t_adv_matchlines[$i]['wards_destroyed'] *= 2;
      $t_adv_matchlines[$i]['wards'] *= 2;
      $t_adv_matchlines[$i]['sentries'] *= 2;
      $t_adv_matchlines[$i]['stacks'] *= 2;
    }
  }

  if (!empty($t_items)) {
    foreach ($t_items as $i => $line) {
      $t_items[$i]['time'] *= 2;
    }
  }
}

if (!$bad_replay && !empty($t_adv_matchlines)) {
  // Fill missing / colliding roles into unique 1-5 per side (lane heuristic, free slots only).
  // On -repair: always rebuild roles from lanes, then always recompute lane_won.
  if (!empty($repair_mode)) {
    foreach ($t_adv_matchlines as &$aml) {
      unset($aml['role'], $aml['lane_won']);
    }
    unset($aml);
  }
  $role_by_hero = [];
  foreach ($t_matchlines as $ml) {
    $role_by_hero[(int)$ml['heroid']] = $ml;
  }
  foreach ([0, 1] as $side) {
    $idxs = [];
    foreach ($t_adv_matchlines as $i => $aml) {
      $hid = (int)($aml['heroid'] ?? 0);
      $ml = $role_by_hero[$hid] ?? null;
      if (!$ml || !$hid) continue;
      if ((!empty($ml['isRadiant']) ? 1 : 0) !== $side) continue;
      $idxs[] = $i;
    }
    if (!$idxs) continue;

    if (!empty($repair_mode)) {
      $players = [];
      foreach ($idxs as $i) {
        $aml = $t_adv_matchlines[$i];
        $hid = (int)$aml['heroid'];
        $ml = $role_by_hero[$hid];
        $lane = (int)($aml['lane'] ?? 4);
        $players[] = [
          'hid' => $hid,
          'gpm' => (int)($ml['gpm'] ?? 0),
          'roaming' => $lane > 3,
          'lane' => $lane,
          'eff' => (float)($aml['efficiency_at10'] ?? 0),
          'lh_at10' => (int)($aml['lh_at10'] ?? 0),
        ];
      }
      $assigned = lrg_assign_roles_from_lanes($players);
      foreach ($idxs as $i) {
        $hid = (int)$t_adv_matchlines[$i]['heroid'];
        if (!isset($assigned[$hid])) continue;
        $t_adv_matchlines[$i]['role'] = $assigned[$hid];
        $t_adv_matchlines[$i]['isCore'] = $assigned[$hid] <= 3 ? 1 : 0;
      }
      continue;
    }

    $counts = [];
    foreach ($idxs as $i) {
      $r = isset($t_adv_matchlines[$i]['role']) ? (int)$t_adv_matchlines[$i]['role'] : 0;
      if ($r >= 1 && $r <= 5) $counts[$r] = ($counts[$r] ?? 0) + 1;
    }

    $used = [];
    $need = [];
    foreach ($idxs as $i) {
      $r = isset($t_adv_matchlines[$i]['role']) ? (int)$t_adv_matchlines[$i]['role'] : 0;
      if ($r >= 1 && $r <= 5 && ($counts[$r] ?? 0) === 1) {
        $used[$r] = true;
      } else {
        $need[] = $i;
      }
    }

    foreach ($need as $ni => $i) {
      $aml = &$t_adv_matchlines[$i];
      $lane = (int)($aml['lane'] ?? 4);
      if (!empty($aml['isCore'])) {
        $pref = ($lane >= 1 && $lane <= 3) ? $lane : 1;
      } else {
        $pref = ($lane == 1) ? 5 : 4;
      }
      if (!isset($used[$pref])) {
        $aml['role'] = $pref;
        $aml['isCore'] = $pref <= 3 ? 1 : 0;
        $used[$pref] = true;
        unset($need[$ni]);
      }
      unset($aml);
    }

    $free = array_values(array_diff(range(1, 5), array_keys($used)));
    foreach (array_values($need) as $i) {
      if ($free === []) break;
      $aml = &$t_adv_matchlines[$i];
      $pick = null;
      if (!empty($aml['isCore'])) {
        foreach ($free as $fi => $role) {
          if ($role <= 3) { $pick = $fi; break; }
        }
      } else {
        for ($fi = count($free) - 1; $fi >= 0; $fi--) {
          if ($free[$fi] >= 4) { $pick = $fi; break; }
        }
      }
      if ($pick === null) $pick = 0;
      $role = $free[$pick];
      array_splice($free, $pick, 1);
      $aml['role'] = $role;
      $aml['isCore'] = $role <= 3 ? 1 : 0;
      $used[$role] = true;
      unset($aml);
    }

    // Still broken (collisions / incomplete) → full lane-based reassignment.
    $seen = [];
    $broken = false;
    foreach ($idxs as $i) {
      $r = (int)($t_adv_matchlines[$i]['role'] ?? 0);
      if ($r < 1 || $r > 5 || isset($seen[$r])) { $broken = true; break; }
      $seen[$r] = true;
    }
    if ($broken) {
      $players = [];
      foreach ($idxs as $i) {
        $aml = $t_adv_matchlines[$i];
        $hid = (int)$aml['heroid'];
        $ml = $role_by_hero[$hid];
        $lane = (int)($aml['lane'] ?? 4);
        $players[] = [
          'hid' => $hid,
          'gpm' => (int)($ml['gpm'] ?? 0),
          'roaming' => $lane > 3,
          'lane' => $lane,
          'eff' => (float)($aml['efficiency_at10'] ?? 0),
          'lh_at10' => (int)($aml['lh_at10'] ?? 0),
        ];
      }
      $assigned = lrg_assign_roles_from_lanes($players);
      foreach ($idxs as $i) {
        $hid = (int)$t_adv_matchlines[$i]['heroid'];
        if (!isset($assigned[$hid])) continue;
        $t_adv_matchlines[$i]['role'] = $assigned[$hid];
        $t_adv_matchlines[$i]['isCore'] = $assigned[$hid] <= 3 ? 1 : 0;
      }
    }
  }

  // Lane won calculation
  $tie_factor = 0.075;
  foreach ($t_adv_matchlines as &$aml) {
    if (isset($aml['lane_won']) && empty($repair_mode)) continue;

    $opp = []; $self = 0; $side = null;
    foreach ($t_matchlines as $ml) {
      if ($ml['heroid'] == $aml['heroid']) { $side = $ml['isRadiant']; break; }
    }
    foreach ($t_matchlines as $ml) {
      if ($ml['isRadiant'] != $side) $opp[] = $ml['heroid'];
    }
    foreach ($t_adv_matchlines as $aml2) {
      if (!in_array($aml2['heroid'], $opp) && $aml2['lane'] == $aml['lane'] && $aml2['isCore'] && $aml2['efficiency_at10'] > $self) {
        $self = $aml2['efficiency_at10'];
      }
    }
    foreach ($t_adv_matchlines as $aml2) {
      if (in_array($aml2['heroid'], $opp) && 4 - $aml2['lane'] == $aml['lane'] && $aml2['isCore']) {
        $diff = $self - $aml2['efficiency_at10'];
        $aml['lane_won'] = abs($diff) <= $tie_factor ? 1 : ($diff > 0 ? 2 : 0);
        break;
      }
    }

    if (!isset($aml['lane_won'])) {
      foreach ($t_adv_matchlines as $aml2) {
        if (in_array($aml2['heroid'], $opp) && $aml2['role'] == $aml['role']) {
          if ($aml['role'] > 3) {
            foreach ($t_adv_matchlines as $aml3) {
              if (!in_array($aml3['heroid'], $opp)) {
                if (($aml3['lane'] == $aml['lane'] && $aml3['isCore']) || $aml3['role'] == ($aml['role'] == 4 ? 3 : 1)) {
                  $self = $aml3['efficiency_at10'];
                }
              } else {
                if (($aml3['lane'] == $aml2['lane'] && $aml3['isCore']) || $aml3['role'] == ($aml2['role'] == 4 ? 3 : 1)) {
                  $aml2['efficiency_at10'] = $aml3['efficiency_at10'];
                }
              }
            }
          }
          $diff = $self - $aml2['efficiency_at10'];
          $aml['lane_won'] = abs($diff) <= $tie_factor ? 1 : ($diff > 0 ? 2 : 0);
        }
      }
      if (!isset($aml['lane_won'])) $aml['lane_won'] = 1;
    }

    if (!isset($aml['time_dead']) || $aml['time_dead'] < 0) $aml['time_dead'] = 0;
  }
  unset($aml);
}

// --- Players ---
if (!empty($t_new_players)) {
  $rows = [];
  foreach ($t_new_players as $id => $player) {
    $rows[] = "(" . $id . ", \"" . addslashes(mb_substr($player, 0, 127)) . "\"" .
      (($schema['players_fixname'] ?? false) ? ", 0" : "") . ")";
  }
  $sql = "INSERT INTO players (playerID, nickname" .
    (($schema['players_fixname'] ?? false) ? ", name_fixed" : "") .
    ") VALUES " . implode(",\n\t", $rows) .
    "\n  ON DUPLICATE KEY UPDATE nickname = " . (
      ($schema['players_fixname'] ?? false)
        ? ($update_names ? "IF(name_fixed = 0, VALUES(nickname), nickname)" : "nickname")
        : ($update_names ? "VALUES(nickname)" : "nickname")
    ) . ";";

  if ($conn->query($sql) === TRUE) {
    foreach ($t_new_players as $id => $player) {
      $t_players[$id] = $player;
      if ($update_names) $updated_names[$id] = $player;
    }
  } else {
    echo "ERROR players (" . $conn->error . ").\n";
    if ($conn->error === "MySQL server has gone away") {
      sleep(30); conn_restart(); $matches[] = $match;
      return false;
    }
  }
}

// --- Leagues ---
if ($schema['leagues'] ?? false) {
  $new_leagues = array_filter($t_leagues, fn($l) => !$l['added']);
  if (!empty($new_leagues)) {
    $rows = [];
    foreach ($new_leagues as $id => $league) {
      $rows[] = "(" . $id . ", '" . $conn->real_escape_string($league['name']) . "', " .
        ($league['url'] ? "'" . $conn->real_escape_string($league['url']) . "'" : "NULL") . ", " .
        ($league['description'] ? "'" . $conn->real_escape_string($league['description']) . "'" : "NULL") . ")";
    }
    $sql = "INSERT INTO leagues (ticket_id, name, url, description) VALUES " . implode(",\n\t", $rows) .
      "\n  ON DUPLICATE KEY UPDATE name = VALUES(name), url = VALUES(url), description = VALUES(description);";

    if ($conn->query($sql) === TRUE) {
      foreach ($new_leagues as $id => $_) $t_leagues[$id]['added'] = true;
    } else {
      echo "ERROR leagues (" . $conn->error . ").\n";
    }
  }
}

// --- Missing team IDs recovered from rosters ---
if (($lg_settings['main']['teams'] ?? false) && function_exists('lrg_fill_missing_team_matches')) {
  if (!isset($t_team_matches) || !is_array($t_team_matches)) $t_team_matches = [];
  lrg_fill_missing_team_matches(
    $conn, $t_team_matches, $t_matchlines, (int)$match, (int)$t_match['start_date']
  );
}

// --- Teams ---
if ($lg_settings['main']['teams'] ?? false) {
  $new_teams = array_filter($t_teams, fn($t) => !$t['added']);
  if (!empty($new_teams)) {
    $rows = [];
    foreach ($new_teams as $id => $team) {
      $rows[] = "(" . $id . ", \"" .
        addslashes(mb_substr($team['name'], 0, 48)) . "\", \"" .
        addslashes(mb_substr($team['tag'], 0, 23)) . "\")";
    }
    $sql = "INSERT INTO teams (teamid, name, tag) VALUES " . implode(",\n\t", $rows) .
      "\n  ON DUPLICATE KEY UPDATE name = VALUES(name), tag = VALUES(tag);";

    if ($conn->query($sql) === TRUE) {
      foreach ($new_teams as $id => $_) $t_teams[$id]['added'] = true;
    } else {
      echo "ERROR teams (" . $conn->error . ").\n";
      if ($conn->error === "MySQL server has gone away") {
        sleep(30); conn_restart(); $matches[] = $match;
        return false;
      }
    }
  }
}

// Match data transaction

$mid = $t_match['matchid'];

// Drain any pending multi_query results from earlier in fetch() to avoid
// "commands out of sync" errors before starting the transaction.
if ($conn->more_results()) {
  do {
    $conn->next_result();
    if ($r = $conn->store_result()) $r->free();
  } while ($conn->more_results());
}

$conn->begin_transaction();

$_tx_fail = function(string $table) use ($conn, $match, &$matches) {
  $err = $conn->error;
  echo "ERROR {$table} ({$err}), rolling back.\n";
  if ($err === "MySQL server has gone away") {
    sleep(30);
    conn_restart();
    $matches[] = $match;
    return false;
  }
  $conn->rollback();
  return null;
};

$_addition_existing = ($addition_mode ?? false) && ($match_exists ?? false);

if (!$_addition_existing) {
  // --- matches ---
  $sql = "INSERT INTO matches (
    matchid, radiantWin, duration, modeID, leagueID, start_date, " .
    (($schema['matches_opener'] ?? false) ? "analysis_status, radiant_opener, seriesid, " : "") .
    (($schema['matches_mmr'] ?? false) ? "mmr, " : "") .
    (($schema['matches_replay_salt'] ?? false) ? "replay_salt, " : "") .
    (($schema['matches_seq_num'] ?? false)
      ? "seq_num, tower_status_radiant, tower_status_dire, barracks_status_radiant, barracks_status_dire, players_c, heroes_c, team_ids_c, "
      : "") .
    (($schema['matches_avg_rank'] ?? false) ? "avg_rank, " : "") .
    (($schema['matches_source'] ?? false) ? "source, " : "") .
    "stomp, comeback, cluster, version) VALUES (" .
    $mid . ", " . ($t_match['radiantWin'] ? "true" : "false") . ", " . $t_match['duration'] . ", " .
    $t_match['modeID'] . ", " . $t_match['leagueID'] . ", " . $t_match['start_date'] . ", " .
    (($schema['matches_opener'] ?? false)
      ? ($t_match['analysis_status'] ?? (!empty($t_adv_matchlines) ? '1' : '0')) . ", " .
        ($t_match['radiant_opener'] ?? 'null') . ", " . ($t_match['seriesid'] ?? 'null') . ", "
      : ""
    ) .
    (($schema['matches_mmr'] ?? false) ? (($t_match['mmr'] ?? null) !== null ? (int)$t_match['mmr'] : 'null') . ", " : "") .
    (($schema['matches_replay_salt'] ?? false) ? (($t_match['replay_salt'] ?? null) !== null ? (int)$t_match['replay_salt'] : 'null') . ", " : "") .
    (($schema['matches_seq_num'] ?? false)
      ? (($t_match['seq_num'] ?? null) !== null ? (int)$t_match['seq_num'] : 'null') . ", " .
        (($t_match['tower_status_radiant'] ?? null) !== null ? (int)$t_match['tower_status_radiant'] : 'null') . ", " .
        (($t_match['tower_status_dire'] ?? null) !== null ? (int)$t_match['tower_status_dire'] : 'null') . ", " .
        (($t_match['barracks_status_radiant'] ?? null) !== null ? (int)$t_match['barracks_status_radiant'] : 'null') . ", " .
        (($t_match['barracks_status_dire'] ?? null) !== null ? (int)$t_match['barracks_status_dire'] : 'null') . ", " .
        lrg_sql_json($conn, $t_match['players_c'] ?? null) . ", " .
        lrg_sql_json($conn, $t_match['heroes_c'] ?? null) . ", " .
        lrg_sql_json($conn, $t_match['team_ids_c'] ?? null) . ", "
      : "") .
    (($schema['matches_avg_rank'] ?? false)
      ? (($t_match['avg_rank'] ?? null) !== null && $t_match['avg_rank'] !== '' ? (int)$t_match['avg_rank'] : 'null') . ", "
      : "") .
    (($schema['matches_source'] ?? false)
      ? (($t_match['source'] ?? null) !== null && $t_match['source'] !== '' ? (int)$t_match['source'] : 'null') . ", "
      : "") .
    ($t_match['stomp'] ?? 0) . ", " . $t_match['comeback'] . ", " .
    ($t_match['cluster'] ?? 0) . ", " . $t_match['version'] . ");";

  if (!$conn->query($sql)) { return $_tx_fail('matches'); }

  // --- matchlines ---
  $rows = [];
  foreach ($t_matchlines as $ml) {
    if (!$ml['heroid']) continue;
    $rows[] = "(" . $ml['matchid'] . ", " . $ml['playerid'] . ", " . $ml['heroid'] . ", " .
      ($schema['variant_supported'] ? ((isset($ml['variant']) && $ml['variant']) ? $ml['variant'] : "null") . ", " : "") .
      $ml['level'] . ", " . ($ml['isRadiant'] ? "true" : "false") . ", " .
      $ml['kills'] . ", " . $ml['deaths'] . ", " . $ml['assists'] . ", " .
      $ml['networth'] . ", " . $ml['gpm'] . ", " . $ml['xpm'] . ", " .
      ($ml['heal'] ?? 0) . ", " . ($ml['heroDamage'] ?? 0) . ", " .
      ($ml['towerDamage'] ?? 0) . ", " . $ml['lastHits'] . ", " . $ml['denies'] .
      (($schema['matchlines_player_slot'] ?? false)
        ? ", " . (isset($ml['player_slot']) && $ml['player_slot'] !== null && $ml['player_slot'] !== '' ? (int)$ml['player_slot'] : "null")
        : "") .
      ")";
  }
  $sql = "INSERT INTO matchlines (matchid, playerid, heroid, " .
    ($schema['variant_supported'] ? "variant, " : "") .
    "level, isRadiant, kills, deaths, assists, networth,
    gpm, xpm, heal, heroDamage, towerDamage, lastHits, denies" .
    (($schema['matchlines_player_slot'] ?? false) ? ", player_slot" : "") .
    ") VALUES " .
    implode(",\n\t", $rows) . ";";

  if (!$conn->query($sql)) { return $_tx_fail('matchlines'); }
} elseif ($_addition_existing && !empty($repair_mode)) {
  $sets = [];
  if (!empty($schema['matches_seq_num'])) {
    if (($t_match['seq_num'] ?? null) !== null && $t_match['seq_num'] !== '') {
      $sets[] = "seq_num = ".(int)$t_match['seq_num'];
    }
    foreach (['tower_status_radiant', 'tower_status_dire', 'barracks_status_radiant', 'barracks_status_dire'] as $_bcol) {
      if (($t_match[$_bcol] ?? null) !== null && $t_match[$_bcol] !== '') {
        $sets[] = "$_bcol = ".(int)$t_match[$_bcol];
      }
    }
    unset($_bcol);
    foreach (['players_c', 'heroes_c', 'team_ids_c'] as $_pcol) {
      if (!empty($t_match[$_pcol])) {
        $sets[] = "$_pcol = ".lrg_sql_json($conn, $t_match[$_pcol]);
      }
    }
    unset($_pcol);
  }
  if (!empty($schema['matches_avg_rank']) && ($t_match['avg_rank'] ?? null) !== null && $t_match['avg_rank'] !== '') {
    $sets[] = "avg_rank = ".(int)$t_match['avg_rank'];
  }
  if (!empty($schema['matches_source']) && ($t_match['source'] ?? null) !== null && $t_match['source'] !== '') {
    $sets[] = "source = ".(int)$t_match['source'];
  }
  if (!empty($sets)) {
    $sql = "UPDATE matches SET ".implode(', ', $sets)." WHERE matchid = $mid";
    if (!$conn->query($sql)) { return $_tx_fail('matches_repair'); }
  }
  if (!empty($schema['matchlines_player_slot']) && !empty($t_matchlines)) {
    foreach ($t_matchlines as $ml) {
      if (!isset($ml['player_slot']) || $ml['player_slot'] === null || $ml['player_slot'] === '') continue;
      $sql = "UPDATE matchlines SET player_slot = ".(int)$ml['player_slot'].
        " WHERE matchid = $mid AND playerid = ".(int)$ml['playerid'];
      if (!$conn->query($sql)) { return $_tx_fail('matchlines_repair'); }
    }
  }
}

if ($_addition_existing && !empty($repair_mode)
    && !empty($t_adv_matchlines) && in_array('adv_matchlines', $present_tables ?? [])) {
  $repair_ts = !empty($schema['adv_matchlines_timeseries'])
    && in_array('adv_matchlines_timeseries', $missing_tables ?? []);
  $repair_roles = !empty($schema['adv_matchlines_roles'])
    && in_array('adv_matchlines_roles', $missing_tables ?? []);
  if ($repair_ts || $repair_roles) {
    foreach ($t_adv_matchlines as $aml) {
      $sets = [];
      if ($repair_ts) {
        $sets[] = "nw_t = ".lrg_sql_json($conn, $aml['nw_t'] ?? null);
        $sets[] = "gold_t = ".lrg_sql_json($conn, $aml['gold_t'] ?? null);
        $sets[] = "xp_t = ".lrg_sql_json($conn, $aml['xp_t'] ?? null);
        $sets[] = "damage_breakdown = ".lrg_sql_json($conn, $aml['damage_breakdown'] ?? null);
        $sets[] = "lh_t = ".lrg_sql_json($conn, $aml['lh_t'] ?? null);
        if (!empty($schema['adv_matchlines_tormentors'])) {
          $sets[] = "tormentors_killed = ".(int)($aml['tormentors_killed'] ?? 0);
        }
      }
      if ($repair_roles) {
        $sets[] = "role = ".(int)($aml['role'] ?? 0);
        $sets[] = "lane_won = ".(int)($aml['lane_won'] ?? 1);
        $sets[] = "isCore = ".(int)($aml['isCore'] ?? 0);
        if (isset($aml['lane'])) $sets[] = "lane = ".(int)$aml['lane'];
      }
      if (!$sets) continue;
      $sql = "UPDATE adv_matchlines SET ".implode(', ', $sets).
        " WHERE matchid = $mid AND playerid = ".(int)$aml['playerid'];
      if (!$conn->query($sql)) { return $_tx_fail('adv_matchlines_repair'); }
    }
  }
}

// --- adv_matchlines ---
if (!$bad_replay && !empty($t_adv_matchlines) && !in_array('adv_matchlines', $present_tables ?? [])) {
  $rows = [];
  foreach ($t_adv_matchlines as $aml) {
    if (!$aml['heroid']) continue;
    $rows[] = "(" . $aml['matchid'] . ", " . $aml['playerid'] . ", " . $aml['heroid'] . ", " .
      ($aml['lh_at10'] ?? 0) . ", " . $aml['isCore'] . ", " . $aml['lane'] . ", " .
      (($schema['adv_matchlines_roles'] ?? false) ? $aml['role'] . ", " . $aml['lane_won'] . ", " : "") .
      $aml['efficiency_at10'] . ", " . ($aml['wards'] ?? 0) . ", " . ($aml['sentries'] ?? 0) . ", " .
      $aml['couriers_killed'] . ", " . $aml['roshans_killed'] . ", " .
      ((!empty($schema['adv_matchlines_tormentors'])) ? ($aml['tormentors_killed'] ?? 0) . ", " : "") .
      $aml['wards_destroyed'] . ", " .
      $aml['multi_kill'] . ", " . $aml['streak'] . ", " . ($aml['stacks'] ?? 0) . ", " .
      $aml['time_dead'] . ", " . ($aml['buybacks'] ?? 0) . ", " . $aml['pings'] . ", " .
      ($aml['stuns'] ?? 0) . ", " . $aml['teamfight_part'] . ", " . $aml['damage_taken'] .
      (($schema['adv_matchlines_timeseries'] ?? false)
        ? ", " . lrg_sql_json($conn, $aml['nw_t'] ?? null) .
          ", " . lrg_sql_json($conn, $aml['gold_t'] ?? null) .
          ", " . lrg_sql_json($conn, $aml['xp_t'] ?? null) .
          ", " . lrg_sql_json($conn, $aml['lh_t'] ?? null) .
          ", " . lrg_sql_json($conn, $aml['damage_breakdown'] ?? null)
        : "") .
      ")";
  }
  $sql = "INSERT INTO adv_matchlines (matchid, playerid, heroid, lh_at10, isCore, lane, " .
    (($schema['adv_matchlines_roles'] ?? false) ? "role, lane_won, " : "") .
    "efficiency_at10, wards, sentries, couriers_killed, roshans_killed" .
    ((!empty($schema['adv_matchlines_tormentors'])) ? ", tormentors_killed" : "") .
    ", wards_destroyed,
    multi_kill, streak, stacks, time_dead, buybacks, pings, stuns, teamfight_part, damage_taken" .
    (($schema['adv_matchlines_timeseries'] ?? false) ? ", nw_t, gold_t, xp_t, lh_t, damage_breakdown" : "") .
    ") VALUES " .
    implode(",\n\t", $rows) . ";";

  if (!$conn->query($sql)) { return $_tx_fail('adv_matchlines'); }
}

// --- draft ---
if (!empty($t_draft) && !$addition_mode) {
  $rows = [];
  foreach ($t_draft as $i => $d) {
    $rows[] = "(" . $d['matchid'] . ", " . ($d['is_radiant'] ? "true" : "false") . ", " .
      ($d['is_pick'] ? "true" : "false") . ", " . $d['hero_id'] . ", " . $d['stage'] .
      (($schema['draft_order'] ?? false) ? ", " . ($d['order'] ?? $i) : "") . ")";
  }
  $sql = "INSERT INTO draft (matchid, is_radiant, is_pick, hero_id, stage" .
    (($schema['draft_order'] ?? false) ? ", `order`" : "") . ") VALUES " .
    implode(",\n\t", $rows) . ";";

  if (!$conn->query($sql)) { return $_tx_fail('draft'); }
}

// --- items / itemslines ---
if (!empty($t_items) && ($lg_settings['main']['items'] ?? false) && !in_array($_items_tbl ?? 'items', $present_tables ?? [])) {
  if ($lg_settings['main']['itemslines'] ?? false) {
    $t_itemslines = [];
    foreach ($t_items as $item) {
      $pid = $item['playerid'];
      if (!isset($t_itemslines[$pid])) {
        $t_itemslines[$pid] = ['matchid' => $item['matchid'], 'hero_id' => $item['hero_id'], 'playerid' => $pid, 'items' => []];
      }
      $t_itemslines[$pid]['items'][] = ['i' => (int)$item['item_id'], 'c' => (int)$item['category_id'], 't' => (int)$item['time']];
    }
    $rows = [];
    foreach ($t_itemslines as $t) {
      $rows[] = "({$t['matchid']}, {$t['hero_id']}, {$t['playerid']}, '" . json_encode($t['items']) . "')";
    }
    $sql = "INSERT INTO itemslines (matchid, hero_id, playerid, items) VALUES " . implode(",\n\t", $rows) . ";";
  } else {
    $rows = [];
    foreach ($t_items as $item) {
      $rows[] = "(" . $item['matchid'] . ", " . $item['hero_id'] . ", " . $item['playerid'] . ", " .
        $item['item_id'] . ", " . (empty($item['category_id']) ? 0 : $item['category_id']) . ", " . $item['time'] . ")";
    }
    $sql = "INSERT INTO items (matchid, hero_id, playerid, item_id, category_id, `time`) VALUES " . implode(",\n\t", $rows) . ";";
  }

  if (!$conn->query($sql)) { return $_tx_fail('items'); }
}

// --- skill_builds ---
if (!empty($t_skill_builds) && ($schema['skill_builds'] ?? false) && (($addition_mode ?? false) || ($lg_settings['main']['skill_builds'] ?? false)) && !in_array('skill_builds', $present_tables ?? [])) {
  $rows = [];
  foreach ($t_skill_builds as $t) {
    if (!$t['hero_id']) continue;
    $rows[] = "({$t['matchid']}, {$t['playerid']}, {$t['hero_id']}, " .
      "'{$t['skill_build']}', '{$t['first_point_at']}', '{$t['maxed_at']}', '{$t['priority']}', '{$t['talents']}'" .
      ($schema['skill_build_attr']
        ? ", " . (isset($t['attributes']) ? "'" . $t['attributes'] . "'" : "'[]'") . ", " . ($t['ultimate'] ?? 'null')
        : "") . ")";
  }
  $sql = "INSERT INTO skill_builds (matchid, playerid, hero_id,
    skill_build, first_point_at, maxed_at, priority, talents" .
    ($schema['skill_build_attr'] ? ", attributes, ultimate" : "") . ") VALUES " .
    implode(",\n\t", $rows) . ";";

  if (!$conn->query($sql)) { return $_tx_fail('skill_builds'); }
}

// --- starting_items ---
if (!empty($t_starting_items) && ($schema['starting_items'] ?? false) && (($addition_mode ?? false) || ($lg_settings['main']['starting'] ?? false)) && !in_array('starting_items', $present_tables ?? [])) {
  $rows = [];
  foreach ($t_starting_items as $t) {
    if (!$t['hero_id']) continue;
    $rows[] = "({$t['matchid']}, {$t['playerid']}, {$t['hero_id']}, '{$t['starting_items']}'" .
      ($schema['starting_consumables'] ? ", '" . ($t['consumables'] ?? "[]") . "'" : "") . ")";
  }
  $sql = "INSERT INTO starting_items (matchid, playerid, hero_id, starting_items" .
    ($schema['starting_consumables'] ? ", consumables" : "") . ") VALUES " .
    implode(",\n\t", $rows) . ";";

  if (!$conn->query($sql)) { return $_tx_fail('starting_items'); }
}

// --- wards ---
if (!empty($t_wards) && ($schema['wards'] ?? false) && (($addition_mode ?? false) || ($lg_settings['main']['wards'] ?? false)) && !in_array('wards', $present_tables ?? [])) {
  $rows = [];
  foreach ($t_wards as $t) {
    if (empty($t['wards_log']) && empty($t['sentries_log']) && empty($t['destroyed_log'])) continue;
    $t['heroid'] = $t['hero_id'] ?? $t['heroid'];
    $rows[] = "({$t['matchid']}, {$t['playerid']}, {$t['heroid']}, '" .
      ($t['wards_log'] ?? '[]') . "', '" . ($t['sentries_log'] ?? '[]') . "', '" . ($t['destroyed_log'] ?? '[]') . "')";
  }
  if (!empty($rows)) {
    $sql = "INSERT INTO wards (matchid, playerid, hero_id, wards_log, sentries_log, destroyed_log) VALUES " .
      implode(",\n\t", $rows) . ";";
    if (!$conn->query($sql)) { return $_tx_fail('wards'); }
  }
}

// --- fantasy_mvp_points + awards ---
if (!empty($t_fantasy_points) && ($lg_settings['main']['fantasy'] ?? false) && ($schema['fantasy_mvp'] ?? false) && !in_array('fantasy_mvp_points', $present_tables ?? [])) {
  $rows = [];
  foreach ($t_fantasy_points as $t) {
    $rows[] = "({$t['matchid']}, {$t['playerid']}, {$t['heroid']}, {$t['total_points']},
      {$t['kills']}, {$t['deaths']}, {$t['assists']}, {$t['creeps']}, {$t['gpm']}, {$t['xpm']},
      {$t['obs_placed']}, {$t['stacks']}, {$t['stuns']}, {$t['teamfight_part']}, {$t['damage']},
      {$t['healing']}, {$t['damage_taken']}, {$t['hero_damage_taken_bonus']}, {$t['hero_damage_taken_penalty']},
      {$t['tower_damage']}, {$t['obs_kills']}, {$t['cour_kills']}, {$t['buybacks']})";
  }
  $sql = "INSERT INTO fantasy_mvp_points (
    matchid, playerid, heroid, total_points, kills, deaths,
    assists, creeps, gpm, xpm, obs_placed, stacks, stuns, teamfight_part, damage,
    healing, damage_taken, hero_damage_taken_bonus, hero_damage_taken_penalty, tower_damage,
    obs_kills, cour_kills, buybacks) VALUES " . implode(",\n\t", $rows) . ";";

  if (!$conn->query($sql)) { return $_tx_fail('fantasy_mvp_points'); }

  if (!empty($t_fantasy_awards)) {
    $rows = [];
    foreach ($t_fantasy_awards as $t) {
      $rows[] = "({$t['matchid']}, {$t['playerid']}, {$t['heroid']}, {$t['total_points']},
        {$t['mvp']}, {$t['mvp_losing']}, {$t['core']}, {$t['support']}, {$t['lvp']})";
    }
    $sql = "INSERT INTO fantasy_mvp_awards (matchid, playerid, heroid, total_points, mvp, mvp_losing, core, support, lvp) VALUES " .
      implode(",\n\t", $rows) . ";";
    if (!$conn->query($sql)) { return $_tx_fail('fantasy_mvp_awards'); }
  }
}

// --- teams_matches ---
if (($lg_settings['main']['teams'] ?? false) && !empty($t_team_matches)) {
  $rows = [];
  foreach ($t_team_matches as $m) {
    if ($m['is_radiant'] > 1) {
      echo "[W] Error when adding teams-matches data: is_radiant flag has higher value than 1\n" .
        "[ ]\t{$m['matchid']} - {$m['teamid']} - {$m['is_radiant']}\n";
      continue;
    }
    $rows[] = "({$m['matchid']}, {$m['teamid']}, {$m['is_radiant']})";
  }
  if (!empty($rows)) {
    $sql = "INSERT INTO teams_matches (matchid, teamid, is_radiant) VALUES " . implode(",\n\t", $rows) .
      "\n  ON DUPLICATE KEY UPDATE is_radiant = VALUES(is_radiant);";
    if (!$conn->query($sql)) { return $_tx_fail('teams_matches'); }
  }
}

// --- matches_ext ---
if (!empty($t_matches_ext) && ($schema['matches_ext'] ?? false) && !in_array('matches_ext', $present_tables ?? [])) {
  $ext = $t_matches_ext;
  $sql = "INSERT INTO matches_ext (matchid, nw_t, xp_t, gold_t, teamfights) VALUES (" .
    $mid . ", " . lrg_sql_json($conn, $ext['nw_t'] ?? null) . ", " .
    lrg_sql_json($conn, $ext['xp_t'] ?? null) . ", " .
    lrg_sql_json($conn, $ext['gold_t'] ?? null) . ", " .
    lrg_sql_json($conn, $ext['teamfights'] ?? null) . ")";
  if (!$conn->query($sql)) { return $_tx_fail('matches_ext'); }
}

// --- objectives ---
if (!empty($t_objectives) && ($schema['objectives'] ?? false) && !in_array('objectives', $present_tables ?? [])) {
  $rows = [];
  foreach ($t_objectives as $o) {
    $kid = $o['killer_playerid'];
    $rows[] = "(" . $mid . ", " . (int)($o['objective_id'] ?? 0) . ", '" .
      $conn->real_escape_string((string)($o['objective_key'] ?? '')) . "', " .
      (int)$o['timing'] . ", " .
      ($kid === null || $kid === '' ? "NULL" : (int)$kid) . ", " .
      (!empty($o['target_is_radiant']) ? "1" : "0") . ")";
  }
  if (!empty($rows)) {
    $sql = "INSERT INTO objectives (matchid, objective_id, objective_key, timing, killer_playerid, target_is_radiant) VALUES " .
      implode(",\n\t", $rows) . ";";
    if (!$conn->query($sql)) { return $_tx_fail('objectives'); }
  }
}

// --- runes ---
if (!empty($t_runes) && ($schema['runes'] ?? false) && !in_array('runes', $present_tables ?? [])) {
  $rows = [];
  foreach ($t_runes as $rn) {
    $rows[] = "(" . $mid . ", " . (int)$rn['playerid'] . ", " . (int)$rn['rune_code'] . ", " . (int)$rn['timing'] . ")";
  }
  $sql = "INSERT INTO runes (matchid, playerid, rune_code, timing) VALUES " . implode(",\n\t", $rows) . ";";
  if (!$conn->query($sql)) { return $_tx_fail('runes'); }
}

// --- chat_report ---
if (!empty($t_chat_report) && ($schema['chat_report'] ?? false) && !in_array('chat_report', $present_tables ?? [])) {
  $rows = [];
  foreach (lrg_chat_report_rows($t_chat_report) as $cr) {
    $pid = (int)($cr['playerid'] ?? 0);
    if (!$pid) continue;
    $rows[] = "(" . $mid . ", " . $pid . ", " . (int)($cr['chat_count'] ?? 0) . ", " .
      (int)($cr['chatwheel_count'] ?? 0) . ", " . (int)($cr['spray_count'] ?? 0) . ", " .
      lrg_sql_json($conn, $cr['top_messages'] ?? []) . ", " .
      lrg_sql_json($conn, $cr['top_chatwheel'] ?? []) . ")";
  }
  if ($rows) {
    $sql = "INSERT INTO chat_report (matchid, playerid, chat_count, chatwheel_count, spray_count, top_messages, top_chatwheel) VALUES " .
      implode(",\n\t", $rows) . ";";
    if (!$conn->query($sql)) { return $_tx_fail('chat_report'); }
  }
}

$conn->commit();
echo "..OK.\n";

if (!empty($schema['matches_failed'])) {
  lrg_match_fail_drop_recorded($conn, (int)$mid);
}

// Cleanup

if ($match && isset($first_scheduled[$match])) unset($first_scheduled[$match]);

$k = array_search($match, $scheduled);
if ($k !== FALSE) unset($scheduled[$k]);

$k = array_search($match, $scheduled_stratz);
if ($k !== FALSE) unset($scheduled_stratz[$k]);

return true;
