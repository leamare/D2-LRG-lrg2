<?php

include_once __DIR__ . '/../commons/objectives.php';

function lrg_sql_json(mysqli $conn, $value): string {
  if ($value === null || $value === '') return 'NULL';
  if (is_string($value)) {
    $trim = trim($value);
    if ($trim === '' || strcasecmp($trim, 'null') === 0) return 'NULL';
    $json = $value;
  } else {
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || $json === 'null') return 'NULL';
  }
  return "'" . $conn->real_escape_string($json) . "'";
}

function lrg_timeseries_json($value) {
  if ($value === null || $value === '' || $value === []) return null;
  if (!is_array($value) && !is_object($value)) return null;
  return $value;
}

function lrg_prefix_sum_series($per_min): ?array {
  if (!is_array($per_min) || $per_min === []) return null;
  $out = [];
  $acc = 0;
  foreach (array_values($per_min) as $v) {
    $acc += (int)$v;
    $out[] = $acc;
  }
  return $out;
}

function lrg_player_slot_from_row(array $player, int $index): int {
  if (isset($player['player_slot']) && $player['player_slot'] !== '' && $player['player_slot'] !== null) {
    return (int)$player['player_slot'];
  }
  if (isset($player['playerSlot']) && $player['playerSlot'] !== '' && $player['playerSlot'] !== null) {
    return (int)$player['playerSlot'];
  }
  $radiant = !empty($player['isRadiant']) || (isset($player['is_radiant']) && $player['is_radiant']);
  if ($index < 5 || $radiant) {
    return $radiant ? ($index < 5 ? $index : $index - 5) : $index;
  }
  return 128 + max(0, $index - 5);
}

function lrg_participants_map(array $t_matchlines, array $t_team_matches = []): array {
  $players = [[], []];
  $heroes = [[], []];
  $team_ids = [0, 0];
  foreach ($t_team_matches as $tm) {
    $team_ids[!empty($tm['is_radiant']) ? 0 : 1] = (int)$tm['teamid'];
  }
  $rows = [];
  foreach ($t_matchlines as $i => $ml) {
    if (empty($ml['heroid'])) continue;
    $rows[] = [
      'slot' => (int)($ml['player_slot'] ?? lrg_player_slot_from_row($ml, (int)$i)),
      'rad' => !empty($ml['isRadiant']),
      'playerid' => (int)$ml['playerid'],
      'heroid' => (int)$ml['heroid'],
    ];
  }
  usort($rows, fn($a, $b) => $a['slot'] <=> $b['slot']);
  foreach ($rows as $row) {
    $side = $row['rad'] ? 0 : 1;
    $players[$side][] = $row['playerid'];
    $heroes[$side][] = $row['heroid'];
  }
  return [
    'players_c' => $players,
    'heroes_c' => $heroes,
    'team_ids_c' => $team_ids,
  ];
}

function lrg_extract_runes(int $match, array $matchdata): array {
  $out = [];
  foreach ($matchdata['players'] ?? [] as $player) {
    if (empty($player['runes_log']) || !is_array($player['runes_log'])) continue;
    $pid = $player['account_id'] ?? $player['playerid'] ?? $player['playerID'] ?? null;
    if ($pid === null || $pid === '') continue;
    foreach ($player['runes_log'] as $rune) {
      $out[] = [
        'matchid' => $match,
        'playerid' => (int)$pid,
        'rune_code' => (int)($rune['key'] ?? $rune['rune'] ?? 0),
        'timing' => (int)($rune['time'] ?? $rune['timing'] ?? 0),
      ];
    }
  }
  return $out;
}

function lrg_extract_objectives(int $match, array $matchdata): array {
  $t_objectives = [];
  $players = $matchdata['players'] ?? [];

  $slot_to_index = function ($player_slot) {
    $player_slot = (int)$player_slot;
    if ($player_slot < 0) return null;
    return $player_slot >= 128 ? ($player_slot - 128 + 5) : $player_slot;
  };

  $index_to_playerid = function ($index) use ($players) {
    if ($index === null || !isset($players[$index])) return null;
    $id = $players[$index]['account_id'] ?? $players[$index]['playerid'] ?? $players[$index]['playerID'] ?? null;
    return ($id === null || $id === '') ? null : (int)$id;
  };

  $event_index = function ($obj) use ($slot_to_index) {
    if (isset($obj['slot']) && $obj['slot'] !== null && (int)$obj['slot'] >= 0) return (int)$obj['slot'];
    if (isset($obj['player_slot']) && $obj['player_slot'] !== null) return $slot_to_index($obj['player_slot']);
    return null;
  };

  $team_is_radiant = function ($obj) {
    if (!isset($obj['team'])) return null;
    $team = (int)$obj['team'];
    if ($team === 2) return true;
    if ($team === 3) return false;
    return null;
  };

  $t4_count = [1 => 0, 2 => 0];

  foreach ($matchdata['objectives'] ?? [] as $obj) {
    if (empty($obj['type'])) continue;

    $blob = [];
    $killer_index = null;
    $npc = (string)($obj['key'] ?? '');

    switch ($obj['type']) {
      case 'CHAT_MESSAGE_FIRSTBLOOD':
        $blob['objective_key'] = 'hero_first_blood';
        $blob['target_is_radiant'] = ((int)($obj['key'] ?? 0) < 5) ? 1 : 0;
        $killer_index = $event_index($obj);
        break;

      case 'CHAT_MESSAGE_ROSHAN_KILL':
        $killer_is_radiant = $team_is_radiant($obj);
        if ($killer_is_radiant === null) break;
        $blob['objective_key'] = 'unit_roshan_kill';
        $blob['target_is_radiant'] = $killer_is_radiant ? 0 : 1;
        break;

      case 'CHAT_MESSAGE_AEGIS':
      case 'CHAT_MESSAGE_DENIED_AEGIS':
      case 'CHAT_MESSAGE_AEGIS_STOLEN':
        $killer_index = $event_index($obj);
        if ($killer_index === null) break;
        $blob['objective_key'] = [
          'CHAT_MESSAGE_AEGIS' => 'unit_roshan_aegis_pickup',
          'CHAT_MESSAGE_DENIED_AEGIS' => 'unit_roshan_aegis_denied',
          'CHAT_MESSAGE_AEGIS_STOLEN' => 'unit_roshan_aegis_stolen',
        ][$obj['type']];
        $blob['target_is_radiant'] = $killer_index < 5 ? 0 : 1;
        break;

      case 'CHAT_MESSAGE_MINIBOSS_KILL':
        $killer_index = $event_index($obj);
        $killer_is_radiant = $team_is_radiant($obj);
        if ($killer_is_radiant === null && $killer_index === null) break;
        if ($killer_is_radiant === null) $killer_is_radiant = $killer_index < 5;
        $blob['objective_key'] = 'unit_tormentor_kill';
        $blob['target_is_radiant'] = $killer_is_radiant ? 0 : 1;
        break;

      case 'CHAT_MESSAGE_COURIER_LOST':
        $victim_is_radiant = $team_is_radiant($obj);
        $killer_index = isset($obj['killer']) ? $slot_to_index($obj['killer']) : $event_index($obj);
        if ($victim_is_radiant === null) {
          if ($killer_index === null) break;
          $victim_is_radiant = $killer_index >= 5;
        }
        $blob['objective_key'] = 'unit_courier_kill';
        $blob['target_is_radiant'] = $victim_is_radiant ? 1 : 0;
        break;

      case 'building_kill':
        if ($npc === '' || stripos($npc, 'fort') !== false) break;
        $target_radiant = strpos($npc, 'goodguys') !== false;
        $blob['objective_key'] = preg_replace('/npc_dota_(goodguys|badguys)_(.+)/i', 'building_$2', $npc) ?: $npc;
        $blob['target_is_radiant'] = $target_radiant ? 1 : 0;
        $killer_index = $event_index($obj);
        $side = $target_radiant ? 1 : 2;
        if (preg_match('/tower4/', strtolower($npc))) {
          $t4_count[$side]++;
        }
        $blob['_t4'] = $t4_count[$side] ?: 1;
        $blob['_npc'] = $npc;
        break;

      default:
        // Glyph, scan, etc. — keep the raw OpenDota type; not every event is a unit.
        $blob['objective_key'] = (string)$obj['type'];
        $killer_index = $event_index($obj);
        $killer_is_radiant = $team_is_radiant($obj);
        if ($killer_is_radiant !== null) {
          $blob['target_is_radiant'] = $killer_is_radiant ? 0 : 1;
        } elseif ($killer_index !== null) {
          $blob['target_is_radiant'] = $killer_index < 5 ? 0 : 1;
        } else {
          $blob['target_is_radiant'] = 0;
        }
        break;
    }

    if (empty($blob)) continue;
    $target = !empty($blob['target_is_radiant']);
    $t4 = (int)($blob['_t4'] ?? 1);
    $npc_for_id = $blob['_npc'] ?? $blob['objective_key'];
    unset($blob['_t4'], $blob['_npc']);
    $blob['matchid'] = $match;
    $blob['timing'] = (int)($obj['time'] ?? 0);
    $blob['killer_playerid'] = $index_to_playerid($killer_index);
    $blob['objective_id'] = ($obj['type'] === 'building_kill')
      ? lrg_objective_id_from_npc((string)$npc_for_id, $target, $t4)
      : lrg_objective_id_from_type($blob['objective_key'], $target, $t4);
    $t_objectives[] = $blob;
  }

  foreach ($players as $i => $player) {
    if (empty($player['runes_log'])) continue;
    foreach ($player['runes_log'] as $rune) {
      if ((int)($rune['key'] ?? -1) !== 8) continue;
      $t_objectives[] = [
        'matchid' => $match,
        'objective_key' => 'rune_wisdom_shrine',
        'objective_id' => 850000,
        'target_is_radiant' => ((int)$i < 5) ? 0 : 1,
        'timing' => (int)($rune['time'] ?? 0),
        'killer_playerid' => $index_to_playerid((int)$i),
      ];
    }
  }

  usort($t_objectives, fn($a, $b) => $a['timing'] <=> $b['timing']);
  return $t_objectives;
}

function lrg_chat_report_rows($cr): array {
  if (!is_array($cr) || $cr === []) return [];
  if (!empty($cr['playerid']) && !isset($cr[0])) return [$cr];
  $first = reset($cr);
  if (is_array($first) && !empty($first['playerid'])) return array_values($cr);
  return [];
}

function lrg_chat_player_maps(array $matchdata, array $matchlines = []): array {
  $by_slot = [];
  $by_pslot = [];
  $lists = [];
  if (!empty($matchdata['players']) && is_array($matchdata['players'])) $lists[] = $matchdata['players'];
  if (!empty($matchdata['matchlines']) && is_array($matchdata['matchlines'])) $lists[] = $matchdata['matchlines'];
  if ($matchlines) $lists[] = $matchlines;
  foreach ($lists as $players) {
    foreach ($players as $i => $pl) {
      if (!is_array($pl)) continue;
      $pid = $pl['account_id'] ?? $pl['playerid'] ?? $pl['playerID'] ?? $pl['steamAccountId'] ?? null;
      if ($pid === null || $pid === '') continue;
      $pid = (int)$pid;
      if (isset($pl['slot']) && $pl['slot'] !== '' && $pl['slot'] !== null) {
        $by_slot[(int)$pl['slot']] = $pid;
      }
      $by_slot[(int)$i] = $by_slot[(int)$i] ?? $pid;
      $ps = $pl['player_slot'] ?? $pl['playerSlot'] ?? null;
      if ($ps === null || $ps === '') {
        if (!empty($pl['isRadiant']) || !empty($pl['is_radiant'])) $ps = (int)$i;
        elseif (isset($pl['isRadiant']) || isset($pl['is_radiant'])) $ps = 128 + max(0, (int)$i - 5);
      }
      if ($ps !== null && $ps !== '') $by_pslot[(int)$ps] = $pid;
    }
  }
  return ['slot' => $by_slot, 'player_slot' => $by_pslot];
}

function lrg_chat_line_playerid(array $line, array $maps): ?int {
  foreach (['playerid', 'account_id', 'playerID', 'steamAccountId'] as $k) {
    if (isset($line[$k]) && $line[$k] !== '' && $line[$k] !== null) return (int)$line[$k];
  }
  if (isset($line['player_slot']) && $line['player_slot'] !== '' && $line['player_slot'] !== null) {
    $ps = (int)$line['player_slot'];
    if (isset($maps['player_slot'][$ps])) return $maps['player_slot'][$ps];
  }
  if (isset($line['playerSlot']) && $line['playerSlot'] !== '' && $line['playerSlot'] !== null) {
    $ps = (int)$line['playerSlot'];
    if (isset($maps['player_slot'][$ps])) return $maps['player_slot'][$ps];
  }
  if (isset($line['slot']) && $line['slot'] !== '' && $line['slot'] !== null) {
    $slot = (int)$line['slot'];
    if (isset($maps['slot'][$slot])) return $maps['slot'][$slot];
    if (isset($maps['player_slot'][$slot])) return $maps['player_slot'][$slot];
    if ($slot >= 0 && $slot <= 4 && isset($maps['player_slot'][$slot])) return $maps['player_slot'][$slot];
    if ($slot >= 5 && $slot <= 9 && isset($maps['player_slot'][128 + $slot - 5])) {
      return $maps['player_slot'][128 + $slot - 5];
    }
  }
  return null;
}

function lrg_extract_chat_report(int $match, array $matchdata, array $matchlines = []): ?array {
  $chat = $matchdata['chat'] ?? $matchdata['chatlog'] ?? $matchdata['chat_log'] ?? null;
  if (is_string($chat)) $chat = json_decode($chat, true);
  if (!is_array($chat)) $chat = [];

  $maps = lrg_chat_player_maps($matchdata, $matchlines);
  $by_pid = [];
  $empty = ['chat' => 0, 'wheel' => 0, 'spray' => 0, 'messages' => [], 'wheels' => []];
  foreach (array_unique(array_merge(array_values($maps['slot']), array_values($maps['player_slot']))) as $pid) {
    $pid = (int)$pid;
    if ($pid) $by_pid[$pid] = $empty;
  }

  foreach ($chat as $line) {
    if (!is_array($line)) continue;
    $pid = lrg_chat_line_playerid($line, $maps);
    if ($pid === null || !$pid) continue;
    $type = strtolower((string)($line['type'] ?? 'chat'));
    $key = (string)($line['key'] ?? $line['text'] ?? $line['msg'] ?? $line['message'] ?? '');
    if ($key === '') continue;

    if (!isset($by_pid[$pid])) {
      $by_pid[$pid] = $empty;
    }

    $is_spray = ($type === 'spray' || $type === 'chatwheel_spray' || $type === 'spraypaint');
    $is_wheel = $is_spray || $type === 'chatwheel' || $type === 'chat_wheel';

    if ($is_spray) {
      $by_pid[$pid]['spray']++;
      $by_pid[$pid]['wheels'][$key] = ($by_pid[$pid]['wheels'][$key] ?? 0) + 1;
    } elseif ($is_wheel) {
      $by_pid[$pid]['wheel']++;
      $by_pid[$pid]['wheels'][$key] = ($by_pid[$pid]['wheels'][$key] ?? 0) + 1;
    } else {
      $by_pid[$pid]['chat']++;
      $by_pid[$pid]['messages'][$key] = ($by_pid[$pid]['messages'][$key] ?? 0) + 1;
    }
  }

  if ($by_pid === []) return null;

  $top = function (array $counts, int $n = 25): array {
    arsort($counts, SORT_NUMERIC);
    $out = [];
    $i = 0;
    foreach ($counts as $text => $c) {
      $out[] = [$text, (int)$c];
      if (++$i >= $n) break;
    }
    return $out;
  };

  $rows = [];
  foreach ($by_pid as $pid => $agg) {
    $rows[] = [
      'matchid' => $match,
      'playerid' => (int)$pid,
      'chat_count' => $agg['chat'],
      'chatwheel_count' => $agg['wheel'],
      'spray_count' => $agg['spray'],
      'top_messages' => $top($agg['messages']),
      'top_chatwheel' => $top($agg['wheels']),
    ];
  }
  return $rows;
}

function lrg_extract_matches_ext(int $match, array $matchdata): array {
  $gold = lrg_timeseries_json($matchdata['radiant_gold_adv'] ?? $matchdata['gold_t'] ?? null);
  $xp = lrg_timeseries_json($matchdata['radiant_xp_adv'] ?? $matchdata['xp_t'] ?? null);
  $nw = lrg_timeseries_json($matchdata['radiant_nw_adv'] ?? $matchdata['nw_t'] ?? $gold);
  $tf = lrg_timeseries_json($matchdata['teamfights'] ?? null);
  return [
    'matchid' => $match,
    'nw_t' => $nw,
    'xp_t' => $xp,
    'gold_t' => $gold,
    'teamfights' => $tf,
  ];
}

function lrg_fetch_skip_short(string $msg, $match = 0, $matchdata = [], array $t_draft = [], array $t_matchlines = []): bool {
  global $conn, $schema;
  $mid = (int)$match;
  if (!$mid) {
    $mid = (int)($GLOBALS['match'] ?? 0);
  }
  if (!empty($schema['matches_draft_donors'])) {
    lrg_draft_donor_consider(
      $conn,
      $mid,
      is_array($matchdata) ? $matchdata : [],
      $t_draft,
      $t_matchlines
    );
  }
  echo($msg);
  return true;
}

function lrg_inflictor_damage_bucket(string $key): string {
  $k = strtolower(trim($key));
  if ($k === '' || $k === 'null' || $k === '0' || $k === 'auto_attack' || $k === 'dota_unknown') {
    return 'ph';
  }
  if (preg_match('/culling_blade|impetus|life_break|nether_strike|sunder|arctic_burn|muerta_pierce|omniknight_hammer_of_purity|spectre_desolate|winter_wyvern_arctic/', $k)) {
    return 'p';
  }
  if (preg_match('/^(item_)?(basher|abyssal_blade|silver_edge|invis_sword|greater_crit|lesser_crit|bfury|desolator|monkey_king_bar|quelling_blade|blight_stone|javelin|rapier|overwhelming_blink|revenants_brooch)/', $k)) {
    return 'ph';
  }
  return 'm';
}

function lrg_extract_damage_breakdown(array $player): ?array {
  if (!empty($player['damage_breakdown']) && is_array($player['damage_breakdown'])) {
    return $player['damage_breakdown'];
  }
  $deal = is_array($player['damage_inflictor'] ?? null) ? $player['damage_inflictor'] : [];
  $recv = is_array($player['damage_inflictor_received'] ?? null) ? $player['damage_inflictor_received'] : [];
  $stuns = (float)($player['stuns'] ?? 0);
  $heal = (int)($player['hero_healing'] ?? 0);
  if ($deal === [] && $recv === [] && $stuns == 0.0 && $heal === 0) return null;

  $sum = static function (array $src): array {
    $out = ['m' => 0, 'ph' => 0, 'p' => 0];
    foreach ($src as $k => $v) {
      $out[lrg_inflictor_damage_bucket((string)$k)] += (int)$v;
    }
    return $out;
  };

  $d = $sum($deal);
  $r = $sum($recv);
  if ($d['m'] + $d['ph'] + $d['p'] + $r['m'] + $r['ph'] + $r['p'] + $heal === 0 && $stuns == 0.0) {
    return null;
  }

  return [
    'r' => $r,
    'd' => $d,
    'cc' => [
      'ss' => (int)round($stuns * 100),
      'sls' => 0,
      'd' => 0,
    ],
    'h' => [
      'sh' => 0,
      'ah' => $heal,
    ],
  ];
}

function lrg_fetch_check_ext_tables(mysqli $conn, int $match, array $schema, bool $repair_mode): array {
  $present = [];
  $missing = [];
  $check = function (string $table, string $flag) use ($conn, $match, $schema, &$present, &$missing) {
    if (empty($schema[$flag])) return;
    $q = $conn->query("SELECT 1 FROM `$table` WHERE matchid=$match LIMIT 1");
    if ($q && $q->num_rows) $present[] = $table;
    else $missing[] = $table;
  };
  $check('matches_ext', 'matches_ext');
  $check('objectives', 'objectives');
  $check('runes', 'runes');
  $check('chat_report', 'chat_report');

  if ($repair_mode) {
    if (!empty($schema['matches_seq_num'])) {
      $q = $conn->query("SELECT 1 FROM matches WHERE matchid=$match AND seq_num IS NULL LIMIT 1");
      if ($q && $q->num_rows) $missing[] = 'matches_seq_num';
    }
    if (!empty($schema['matches_avg_rank'])) {
      $q = $conn->query("SELECT 1 FROM matches WHERE matchid=$match AND avg_rank IS NULL LIMIT 1");
      if ($q && $q->num_rows) $missing[] = 'matches_avg_rank';
    }
    if (!empty($schema['matches_source'])) {
      $q = $conn->query("SELECT 1 FROM matches WHERE matchid=$match AND source IS NULL LIMIT 1");
      if ($q && $q->num_rows) $missing[] = 'matches_source';
    }
    if (!empty($schema['matchlines_player_slot'])) {
      $q = $conn->query("SELECT 1 FROM matchlines WHERE matchid=$match AND player_slot IS NULL LIMIT 1");
      if ($q && $q->num_rows) $missing[] = 'matchlines_player_slot';
    }
    if (!empty($schema['adv_matchlines_timeseries'])) {
      $q = $conn->query("SELECT 1 FROM adv_matchlines WHERE matchid=$match AND nw_t IS NULL LIMIT 1");
      if ($q && $q->num_rows) $missing[] = 'adv_matchlines_timeseries';
    }
    // Always refresh roles + laning outcomes on -repair
    if (!empty($schema['adv_matchlines_roles'])) {
      $q = $conn->query("SELECT 1 FROM adv_matchlines WHERE matchid=$match LIMIT 1");
      if ($q && $q->num_rows) $missing[] = 'adv_matchlines_roles';
    }
  }
  return [$present, $missing];
}

/**
 * Assign unique roles 1-5 from lane buckets.
 * Input rows: hid, gpm, roaming, lane, eff, lh_at10
 * @return array<int,int> hero_id => role
 */
function lrg_assign_roles_from_lanes(array $players): array {
  $lanes = [];
  foreach ($players as $p) {
    $ln = (int)($p['lane'] ?? 4);
    if ($ln < 1 || $ln > 4) $ln = 4;
    $lanes[$ln][] = $p;
  }
  ksort($lanes);

  $assigned_roles = [];
  $unassigned = [];

  foreach ([1, 2, 3] as $lane) {
    if (empty($lanes[$lane])) continue;
    $lane_non_roaming = array_values(array_filter($lanes[$lane], fn($p) => empty($p['roaming'])));
    $lane_roaming     = array_values(array_filter($lanes[$lane], fn($p) => !empty($p['roaming'])));

    if (!empty($lane_non_roaming)) {
      usort($lane_non_roaming, function($a, $b) {
        if (abs(($b['eff'] ?? 0) - ($a['eff'] ?? 0)) > 0.05) return ($b['eff'] ?? 0) <=> ($a['eff'] ?? 0);
        return ($b['lh_at10'] ?? 0) <=> ($a['lh_at10'] ?? 0);
      });
      $assigned_roles[$lane] = array_shift($lane_non_roaming);
      $unassigned = array_merge($unassigned, $lane_non_roaming, $lane_roaming);
    } else {
      $unassigned = array_merge($unassigned, $lane_roaming);
    }
  }

  if (!empty($lanes[4])) {
    $unassigned = array_merge($unassigned, $lanes[4]);
  }

  usort($unassigned, function($a, $b) {
    if (($a['gpm'] ?? 0) != ($b['gpm'] ?? 0)) return ($a['gpm'] ?? 0) <=> ($b['gpm'] ?? 0);
    return ($a['eff'] ?? 0) <=> ($b['eff'] ?? 0);
  });

  $supports = [];
  $roaming_supports = [];
  foreach ($unassigned as $p) {
    if (!empty($p['roaming'])) $roaming_supports[] = $p;
    else $supports[] = $p;
  }

  if (count($unassigned) == 2) {
    if (count($roaming_supports) == 2) {
      usort($roaming_supports, function($a, $b) {
        if (($a['lane'] ?? 0) == 1 && ($b['lane'] ?? 0) != 1) return 1;
        if (($b['lane'] ?? 0) == 1 && ($a['lane'] ?? 0) != 1) return -1;
        return ($a['gpm'] ?? 0) <=> ($b['gpm'] ?? 0);
      });
      $assigned_roles[5] = array_shift($roaming_supports);
      $assigned_roles[4] = array_shift($roaming_supports);
    } else if (count($roaming_supports) == 1 && count($supports) == 1) {
      $roamer = $roaming_supports[0];
      $laner = $supports[0];
      if (($laner['lane'] ?? 0) == 1) {
        $assigned_roles[5] = $laner;
        $assigned_roles[4] = $roamer;
      } else {
        $assigned_roles[4] = $laner;
        $assigned_roles[5] = $roamer;
      }
    } else {
      usort($unassigned, function($a, $b) {
        $a_safe = (($a['lane'] ?? 0) == 1) ? 0 : 1;
        $b_safe = (($b['lane'] ?? 0) == 1) ? 0 : 1;
        if ($a_safe != $b_safe) return $a_safe <=> $b_safe;
        return ($a['gpm'] ?? 0) <=> ($b['gpm'] ?? 0);
      });
      $assigned_roles[5] = array_shift($unassigned);
      $assigned_roles[4] = array_shift($unassigned);
    }
  } else {
    for ($role = 1; $role <= 3; $role++) {
      if (!isset($assigned_roles[$role]) && !empty($unassigned)) {
        usort($unassigned, function($a, $b) {
          if (empty($a['roaming']) !== empty($b['roaming'])) return empty($a['roaming']) ? -1 : 1;
          return ($b['gpm'] ?? 0) <=> ($a['gpm'] ?? 0);
        });
        $assigned_roles[$role] = array_shift($unassigned);
        usort($unassigned, function($a, $b) {
          return ($a['gpm'] ?? 0) <=> ($b['gpm'] ?? 0);
        });
      }
    }
    if (!empty($unassigned)) $assigned_roles[5] = array_shift($unassigned);
    if (!empty($unassigned)) $assigned_roles[4] = array_shift($unassigned);
    $role_slot = 1;
    while (!empty($unassigned)) {
      if (!isset($assigned_roles[$role_slot])) {
        $assigned_roles[$role_slot] = array_shift($unassigned);
      }
      $role_slot++;
    }
  }

  $out = [];
  foreach ($assigned_roles as $role => $p) {
    if (!empty($p['hid'])) $out[(int)$p['hid']] = (int)$role;
  }
  return $out;
}

/**
 * Recalculate unique roles 1-5 + lane_won from matchlines / adv_matchlines already in memory.
 * Uses lane, efficiency_at10, lh_at10, gpm — no API.
 *
 * @param list<array<string,mixed>> $t_adv_matchlines
 * @param list<array<string,mixed>> $t_matchlines
 */
function lrg_recalc_roles_laning(array &$t_adv_matchlines, array $t_matchlines): void {
  if (empty($t_adv_matchlines) || empty($t_matchlines)) return;

  $by_hero = [];
  foreach ($t_matchlines as $ml) {
    $by_hero[(int)$ml['heroid']] = $ml;
  }

  foreach ([0, 1] as $side) {
    $idxs = [];
    $players = [];
    foreach ($t_adv_matchlines as $i => $aml) {
      $hid = (int)($aml['heroid'] ?? 0);
      $ml = $by_hero[$hid] ?? null;
      if (!$ml || !$hid) continue;
      if ((!empty($ml['isRadiant']) ? 1 : 0) !== $side) continue;
      $lane = (int)($aml['lane'] ?? 4);
      $idxs[] = $i;
      $players[] = [
        'hid' => $hid,
        'gpm' => (int)($ml['gpm'] ?? 0),
        'roaming' => $lane > 3,
        'lane' => $lane,
        'eff' => (float)($aml['efficiency_at10'] ?? 0),
        'lh_at10' => (int)($aml['lh_at10'] ?? 0),
      ];
    }
    if (!$players) continue;
    $assigned = lrg_assign_roles_from_lanes($players);
    foreach ($idxs as $i) {
      $hid = (int)$t_adv_matchlines[$i]['heroid'];
      if (!isset($assigned[$hid])) continue;
      $t_adv_matchlines[$i]['role'] = $assigned[$hid];
      $t_adv_matchlines[$i]['isCore'] = $assigned[$hid] <= 3 ? 1 : 0;
    }
  }

  $tie_factor = 0.075;
  foreach ($t_adv_matchlines as &$aml) {
    unset($aml['lane_won']);
    $opp = [];
    $self = 0;
    $side = null;
    foreach ($t_matchlines as $ml) {
      if ((int)$ml['heroid'] === (int)$aml['heroid']) {
        $side = $ml['isRadiant'];
        break;
      }
    }
    foreach ($t_matchlines as $ml) {
      if ($ml['isRadiant'] != $side) $opp[] = (int)$ml['heroid'];
    }
    foreach ($t_adv_matchlines as $aml2) {
      if (!in_array((int)$aml2['heroid'], $opp, true)
          && (int)$aml2['lane'] === (int)$aml['lane']
          && !empty($aml2['isCore'])
          && (float)$aml2['efficiency_at10'] > $self) {
        $self = (float)$aml2['efficiency_at10'];
      }
    }
    foreach ($t_adv_matchlines as $aml2) {
      if (in_array((int)$aml2['heroid'], $opp, true)
          && 4 - (int)$aml2['lane'] === (int)$aml['lane']
          && !empty($aml2['isCore'])) {
        $diff = $self - (float)$aml2['efficiency_at10'];
        $aml['lane_won'] = abs($diff) <= $tie_factor ? 1 : ($diff > 0 ? 2 : 0);
        break;
      }
    }
    if (!isset($aml['lane_won'])) {
      foreach ($t_adv_matchlines as &$aml2) {
        if (in_array((int)$aml2['heroid'], $opp, true) && (int)$aml2['role'] === (int)$aml['role']) {
          if ((int)$aml['role'] > 3) {
            foreach ($t_adv_matchlines as $aml3) {
              if (!in_array((int)$aml3['heroid'], $opp, true)) {
                if (((int)$aml3['lane'] === (int)$aml['lane'] && !empty($aml3['isCore']))
                    || (int)$aml3['role'] === ((int)$aml['role'] === 4 ? 3 : 1)) {
                  $self = (float)$aml3['efficiency_at10'];
                }
              } else {
                if (((int)$aml3['lane'] === (int)$aml2['lane'] && !empty($aml3['isCore']))
                    || (int)$aml3['role'] === ((int)$aml2['role'] === 4 ? 3 : 1)) {
                  $aml2['efficiency_at10'] = $aml3['efficiency_at10'];
                }
              }
            }
          }
          $diff = $self - (float)$aml2['efficiency_at10'];
          $aml['lane_won'] = abs($diff) <= $tie_factor ? 1 : ($diff > 0 ? 2 : 0);
        }
      }
      unset($aml2);
      if (!isset($aml['lane_won'])) $aml['lane_won'] = 1;
    }
  }
  unset($aml);
}
