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
  foreach ($t_adv_matchlines as &$aml) {
    if (!isset($aml['role'])) {
      if ($aml['isCore']) {
        $aml['role'] = $aml['lane'];
      } else {
        $aml['role'] = ($aml['lane'] == 1) ? 4 : 3;
      }
    }
  }
  unset($aml);

  // Lane won calculation
  $tie_factor = 0.075;
  foreach ($t_adv_matchlines as &$aml) {
    if (isset($aml['lane_won'])) continue;

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

    if (empty($aml['lane_won'])) {
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
      if (empty($aml['lane_won'])) $aml['lane_won'] = 2;
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

// --- matches ---
$sql = "INSERT INTO matches (
  matchid, radiantWin, duration, modeID, leagueID, start_date, " .
  (($schema['matches_opener'] ?? false) ? "analysis_status, radiant_opener, seriesid, " : "") .
  "stomp, comeback, cluster, version) VALUES (" .
  $mid . ", " . ($t_match['radiantWin'] ? "true" : "false") . ", " . $t_match['duration'] . ", " .
  $t_match['modeID'] . ", " . $t_match['leagueID'] . ", " . $t_match['start_date'] . ", " .
  (($schema['matches_opener'] ?? false)
    ? ($t_match['analysis_status'] ?? (!empty($t_adv_matchlines) ? '1' : '0')) . ", " .
      ($t_match['radiant_opener'] ?? 'null') . ", " . ($t_match['seriesid'] ?? 'null') . ", "
    : ""
  ) .
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
    ($ml['towerDamage'] ?? 0) . ", " . $ml['lastHits'] . ", " . $ml['denies'] . ")";
}
$sql = "INSERT INTO matchlines (matchid, playerid, heroid, " .
  ($schema['variant_supported'] ? "variant, " : "") .
  "level, isRadiant, kills, deaths, assists, networth,
  gpm, xpm, heal, heroDamage, towerDamage, lastHits, denies) VALUES " .
  implode(",\n\t", $rows) . ";";

if (!$conn->query($sql)) { return $_tx_fail('matchlines'); }

// --- adv_matchlines ---
if (!$bad_replay && !empty($t_adv_matchlines)) {
  $rows = [];
  foreach ($t_adv_matchlines as $aml) {
    if (!$aml['heroid']) continue;
    $rows[] = "(" . $aml['matchid'] . ", " . $aml['playerid'] . ", " . $aml['heroid'] . ", " .
      ($aml['lh_at10'] ?? 0) . ", " . $aml['isCore'] . ", " . $aml['lane'] . ", " .
      (($schema['adv_matchlines_roles'] ?? false) ? $aml['role'] . ", " . $aml['lane_won'] . ", " : "") .
      $aml['efficiency_at10'] . ", " . ($aml['wards'] ?? 0) . ", " . ($aml['sentries'] ?? 0) . ", " .
      $aml['couriers_killed'] . ", " . $aml['roshans_killed'] . ", " . $aml['wards_destroyed'] . ", " .
      $aml['multi_kill'] . ", " . $aml['streak'] . ", " . ($aml['stacks'] ?? 0) . ", " .
      $aml['time_dead'] . ", " . ($aml['buybacks'] ?? 0) . ", " . $aml['pings'] . ", " .
      ($aml['stuns'] ?? 0) . ", " . $aml['teamfight_part'] . ", " . $aml['damage_taken'] . ")";
  }
  $sql = "INSERT INTO adv_matchlines (matchid, playerid, heroid, lh_at10, isCore, lane, " .
    (($schema['adv_matchlines_roles'] ?? false) ? "role, lane_won, " : "") .
    "efficiency_at10, wards, sentries, couriers_killed, roshans_killed, wards_destroyed,
    multi_kill, streak, stacks, time_dead, buybacks, pings, stuns, teamfight_part, damage_taken) VALUES " .
    implode(",\n\t", $rows) . ";";

  if (!$conn->query($sql)) { return $_tx_fail('adv_matchlines'); }
}

// --- draft ---
if (!empty($t_draft)) {
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
if (!empty($t_items) && ($lg_settings['main']['items'] ?? false)) {
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
        $item['item_id'] . ", " . ($item['category_id'] ?? 0) . ", " . $item['time'] . ")";
    }
    $sql = "INSERT INTO items (matchid, hero_id, playerid, item_id, category_id, `time`) VALUES " . implode(",\n\t", $rows) . ";";
  }

  if (!$conn->query($sql)) { return $_tx_fail('items'); }
}

// --- skill_builds ---
if (!empty($t_skill_builds) && ($schema['skill_builds'] ?? false) && ($lg_settings['main']['skill_builds'] ?? false)) {
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
if (!empty($t_starting_items) && ($schema['starting_items'] ?? false) && ($lg_settings['main']['starting'] ?? false)) {
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
if (!empty($t_wards) && ($schema['wards'] ?? false) && ($lg_settings['main']['wards'] ?? false)) {
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
if (!empty($t_fantasy_points) && ($lg_settings['main']['fantasy'] ?? false) && ($schema['fantasy_mvp'] ?? false)) {
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

$conn->commit();
echo "..OK.\n";

// Cleanup

if ($match && isset($first_scheduled[$match])) unset($first_scheduled[$match]);

$k = array_search($match, $scheduled);
if ($k !== FALSE) unset($scheduled[$k]);

$k = array_search($match, $scheduled_stratz);
if ($k !== FALSE) unset($scheduled_stratz[$k]);

return true;
