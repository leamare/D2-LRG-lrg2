<?php

include_once __DIR__ . '/match_fail.php';

function lrg_draft_side_sets(array $heroes, array $players): array {
  $rh = $heroes['radiant'] ?? [];
  $dh = $heroes['dire'] ?? [];
  $rp = $players['radiant'] ?? [];
  $dp = $players['dire'] ?? [];
  sort($rh);
  sort($dh);
  sort($rp);
  sort($dp);
  return [
    'heroes' => ['radiant' => array_values($rh), 'dire' => array_values($dh)],
    'players' => ['radiant' => array_values($rp), 'dire' => array_values($dp)],
  ];
}

function lrg_draft_overlap(array $a, array $b): int {
  return count(array_intersect($a, $b));
}

function lrg_draft_lineup_score(array $left, array $right): array {
  $h = lrg_draft_overlap($left['heroes']['radiant'] ?? [], $right['heroes']['radiant'] ?? [])
    + lrg_draft_overlap($left['heroes']['dire'] ?? [], $right['heroes']['dire'] ?? []);
  $p = lrg_draft_overlap($left['players']['radiant'] ?? [], $right['players']['radiant'] ?? [])
    + lrg_draft_overlap($left['players']['dire'] ?? [], $right['players']['dire'] ?? []);
  return ['heroes' => $h, 'players' => $p];
}

function lrg_draft_lineup_from_players(array $players): array {
  $heroes = ['radiant' => [], 'dire' => []];
  $pids = ['radiant' => [], 'dire' => []];
  foreach ($players as $i => $pl) {
    $hid = (int)($pl['hero_id'] ?? $pl['heroid'] ?? 0);
    if (!$hid) continue;
    $rad = !empty($pl['isRadiant']) || (!empty($pl['is_radiant']));
    if (!$rad && isset($pl['player_slot'])) {
      $rad = ((int)$pl['player_slot'] < 128);
    }
    if (!$rad && $i < 5 && !isset($pl['isRadiant']) && !isset($pl['is_radiant'])) {
      $rad = true;
    }
    $side = $rad ? 'radiant' : 'dire';
    $heroes[$side][] = $hid;
    $pid = $pl['account_id'] ?? $pl['playerid'] ?? $pl['playerID'] ?? null;
    if ($pid !== null && $pid !== '') $pids[$side][] = (int)$pid;
  }
  return lrg_draft_side_sets($heroes, $pids);
}

function lrg_draft_lineup_from_matchlines(array $t_matchlines): array {
  $heroes = ['radiant' => [], 'dire' => []];
  $pids = ['radiant' => [], 'dire' => []];
  foreach ($t_matchlines as $ml) {
    $hid = (int)($ml['heroid'] ?? $ml['hero_id'] ?? 0);
    if (!$hid) continue;
    $side = !empty($ml['isRadiant']) ? 'radiant' : 'dire';
    $heroes[$side][] = $hid;
    $pids[$side][] = (int)$ml['playerid'];
  }
  return lrg_draft_side_sets($heroes, $pids);
}

function lrg_draft_blob_from_rows(array $t_draft): array {
  $out = [];
  foreach ($t_draft as $i => $d) {
    if (empty($d['hero_id'])) continue;
    $out[] = [
      'is_radiant' => !empty($d['is_radiant']) ? 1 : 0,
      'is_pick' => !empty($d['is_pick']) ? 1 : 0,
      'hero_id' => (int)$d['hero_id'],
      'stage' => (int)($d['stage'] ?? 1),
      'order' => (int)($d['order'] ?? $i),
    ];
  }
  return $out;
}

function lrg_draft_from_picks_bans(array $picks, int $match): array {
  $out = [];
  $stage = 0;
  $last_pick = null;
  foreach ($picks as $i => $pb) {
    $hid = (int)($pb['hero_id'] ?? $pb['heroId'] ?? 0);
    if (!$hid) continue;
    $is_pick = !empty($pb['is_pick']) || !empty($pb['isPick']);
    if (isset($pb['is_radiant']) || isset($pb['isRadiant'])) {
      $is_rad = (int)!empty($pb['is_radiant'] ?? $pb['isRadiant']);
    } elseif (isset($pb['team'])) {
      // OpenDota: team 0 = radiant, 1 = dire
      $is_rad = ((int)$pb['team'] === 0) ? 1 : 0;
    } else {
      $is_rad = 0;
    }
    if ($last_pick !== null && $last_pick !== $is_pick) $stage++;
    $last_pick = $is_pick;
    $out[] = [
      'matchid' => $match,
      'is_radiant' => $is_rad,
      'is_pick' => $is_pick ? 1 : 0,
      'hero_id' => $hid,
      'stage' => $stage + 1,
      'order' => (int)($pb['order'] ?? $i),
    ];
  }
  return $out;
}

function lrg_draft_align_picks_to_lineup(array $draft, array $lineup): array {
  $draft = array_values($draft);
  foreach (['radiant' => 1, 'dire' => 0] as $side => $is_rad) {
    $actual = [];
    foreach ($lineup['heroes'][$side] ?? [] as $hid) {
      $hid = (int)$hid;
      if ($hid) $actual[] = $hid;
    }
    if ($actual === []) continue;

    $pick_idxs = [];
    $pick_heroes = [];
    foreach ($draft as $i => $d) {
      if (empty($d['is_pick'])) continue;
      if ((int)!empty($d['is_radiant']) !== $is_rad) continue;
      $hid = (int)($d['hero_id'] ?? 0);
      if (!$hid) continue;
      $pick_idxs[] = $i;
      $pick_heroes[] = $hid;
    }

    $remaining = $actual;
    $replace = [];
    foreach ($pick_heroes as $j => $hid) {
      $k = array_search($hid, $remaining, true);
      if ($k !== false) {
        unset($remaining[$k]);
        $remaining = array_values($remaining);
      } else {
        $replace[] = $pick_idxs[$j];
      }
    }

    foreach ($replace as $di) {
      if (!$remaining) break;
      $old = (int)$draft[$di]['hero_id'];
      $new = array_shift($remaining);
      $draft[$di]['hero_id'] = $new;
      foreach ($draft as $bi => $bd) {
        if (!empty($bd['is_pick'])) continue;
        if ((int)($bd['hero_id'] ?? 0) !== $new) continue;
        $draft[$bi]['hero_id'] = $old;
        break;
      }
    }

    if ($remaining) {
      $max_order = 0;
      foreach ($draft as $d) {
        $max_order = max($max_order, (int)($d['order'] ?? 0));
      }
      foreach ($remaining as $hid) {
        foreach ($draft as $bi => $bd) {
          if (!empty($bd['is_pick'])) continue;
          if ((int)($bd['hero_id'] ?? 0) !== $hid) continue;
          unset($draft[$bi]);
          break;
        }
        $max_order++;
        $last = end($draft) ?: [];
        $draft[] = [
          'is_radiant' => $is_rad,
          'is_pick' => 1,
          'hero_id' => $hid,
          'stage' => (int)($last['stage'] ?? 1),
          'order' => $max_order,
        ];
      }
      $draft = array_values($draft);
    }
  }
  return $draft;
}

function lrg_draft_has_real_order(array $draft): bool {
  if (count($draft) < 10) return false;
  $has_ban = false;
  $orders = [];
  $stages = [];
  foreach ($draft as $d) {
    if (empty($d['is_pick'])) $has_ban = true;
    $orders[(int)($d['order'] ?? 0)] = true;
    $stages[(int)($d['stage'] ?? 1)] = true;
  }
  return $has_ban || count($orders) > 1 || count($stages) > 1;
}

function lrg_draft_insert_rows(mysqli $conn, int $matchid, array $draft): bool {
  global $schema;
  if (empty($draft)) return false;

  $target_lineup = lrg_draft_load_lineup_from_db($conn, $matchid);
  if ($target_lineup) {
    $draft = lrg_draft_align_picks_to_lineup($draft, $target_lineup);
  }

  $q = $conn->query("SELECT is_pick, stage" .
    (($schema['draft_order'] ?? false) ? ", `order`" : "") .
    " FROM draft WHERE matchid = $matchid");
  if ($q && $q->num_rows) {
    $has_ban = false;
    $orders = [];
    $stages = [];
    while ($row = $q->fetch_assoc()) {
      if (empty($row['is_pick'])) $has_ban = true;
      $orders[(int)($row['order'] ?? 0)] = true;
      $stages[(int)($row['stage'] ?? 1)] = true;
    }
    $trivial = !$has_ban && count($orders) <= 1 && count($stages) <= 1;
    if (!$trivial) return false;
    $conn->query("DELETE FROM draft WHERE matchid = $matchid");
  }

  $rows = [];
  foreach ($draft as $i => $d) {
    $hid = (int)($d['hero_id'] ?? 0);
    if (!$hid) continue;
    $rows[] = "(" . $matchid . ", " . (!empty($d['is_radiant']) ? "true" : "false") . ", " .
      (!empty($d['is_pick']) ? "true" : "false") . ", " . $hid . ", " . (int)($d['stage'] ?? 1) .
      (($schema['draft_order'] ?? false) ? ", " . (int)($d['order'] ?? $i) : "") . ")";
  }
  if (empty($rows)) return false;
  $sql = "INSERT INTO draft (matchid, is_radiant, is_pick, hero_id, stage" .
    (($schema['draft_order'] ?? false) ? ", `order`" : "") . ") VALUES " .
    implode(",\n\t", $rows) . ";";
  return (bool)$conn->query($sql);
}

function lrg_draft_load_lineup_from_db(mysqli $conn, int $matchid): ?array {
  $r = $conn->query("SELECT playerid, heroid, isRadiant FROM matchlines WHERE matchid = $matchid");
  if (!$r || !$r->num_rows) return null;
  $ml = [];
  while ($row = $r->fetch_assoc()) {
    $ml[] = [
      'playerid' => (int)$row['playerid'],
      'heroid' => (int)$row['heroid'],
      'isRadiant' => (int)$row['isRadiant'],
    ];
  }
  return lrg_draft_lineup_from_matchlines($ml);
}

function lrg_draft_find_allpick_target(mysqli $conn, array $lineup, int $except_match = 0): ?int {
  global $schema;
  $ex = $except_match ? "AND m.matchid <> $except_match" : "";
  $trivial = "OR (SUM(NOT d.is_pick) = 0 AND COUNT(DISTINCT d.stage) <= 1";
  if (!empty($schema['draft_order'])) {
    $trivial .= " AND COUNT(DISTINCT d.`order`) <= 1";
  }
  $trivial .= ")";
  $sql = "SELECT m.matchid
    FROM matches m
    LEFT JOIN draft d ON d.matchid = m.matchid
    WHERE m.modeID = 1 $ex
    GROUP BY m.matchid
    HAVING COUNT(d.matchid) = 0
       $trivial";
  $r = $conn->query($sql);
  if (!$r) {
    $sql = "SELECT m.matchid
      FROM matches m
      LEFT JOIN draft d ON d.matchid = m.matchid
      WHERE m.modeID = 1 $ex
      GROUP BY m.matchid
      HAVING COUNT(d.matchid) = 0";
    $r = $conn->query($sql);
  }
  if (!$r) return null;
  while ($row = $r->fetch_assoc()) {
    $mid = (int)$row['matchid'];
    $other = lrg_draft_load_lineup_from_db($conn, $mid);
    if (!$other) continue;
    $score = lrg_draft_lineup_score($lineup, $other);
    if ($score['heroes'] >= 9 && $score['players'] >= 8) {
      return $mid;
    }
  }
  return null;
}

function lrg_draft_find_donor_for_lineup(mysqli $conn, array $lineup, int $except_match = 0): ?array {
  global $schema;
  if (empty($schema['matches_draft_donors'])) return null;
  $ex = $except_match ? "AND matchid <> $except_match" : "";
  $r = $conn->query("SELECT matchid, draft, heroes, players, modeID FROM matches_draft_donors WHERE donated_to IS NULL $ex");
  if (!$r) return null;
  while ($row = $r->fetch_assoc()) {
    $heroes = json_decode($row['heroes'] ?? '[]', true) ?: [];
    $players = json_decode($row['players'] ?? '[]', true) ?: [];
    $donor_line = lrg_draft_side_sets($heroes, $players);
    $score = lrg_draft_lineup_score($lineup, $donor_line);
    if ($score['heroes'] >= 9 && $score['players'] >= 8) {
      $draft = json_decode($row['draft'] ?? '[]', true) ?: [];
      if (empty($draft)) continue;
      return [
        'matchid' => (int)$row['matchid'],
        'draft' => $draft,
        'modeID' => isset($row['modeID']) ? (int)$row['modeID'] : null,
      ];
    }
  }
  return null;
}

function lrg_draft_mark_donated(mysqli $conn, int $donor, int $donatee): void {
  $conn->query("UPDATE matches_draft_donors SET donated_to = $donatee WHERE matchid = $donor");
}

function lrg_draft_store_donor(mysqli $conn, int $matchid, array $draft, array $lineup, ?int $mode = null): void {
  global $schema;
  if (empty($schema['matches_draft_donors']) || empty($draft)) return;

  $q = $conn->query("SELECT matchid, donated_to FROM matches_draft_donors WHERE matchid = $matchid LIMIT 1");
  if ($q && $q->num_rows) {
    echo("..Draft donor already stored.");
    return;
  }

  $heroes = json_encode($lineup['heroes'], JSON_UNESCAPED_UNICODE);
  $players = json_encode($lineup['players'], JSON_UNESCAPED_UNICODE);
  $blob = json_encode($draft, JSON_UNESCAPED_UNICODE);
  $now = time();
  $mode_sql = $mode !== null && $mode > 0 ? (int)$mode : 'NULL';
  $sql = "INSERT INTO matches_draft_donors (matchid, draft, heroes, players, modeID, donated_to, created_at) VALUES (" .
    $matchid . ", '" . $conn->real_escape_string($blob) . "', '" .
    $conn->real_escape_string($heroes) . "', '" .
    $conn->real_escape_string($players) . "', $mode_sql, NULL, $now)";
  if (!$conn->query($sql)) {
    echo("..ERROR storing draft donor (".$conn->error.").");
    return;
  }

  if (!empty($schema['matches_failed'])) {
    lrg_match_fail_touch($conn, $matchid, 'missing', true);
  }
}

/**
 * Short / low-score match: try to donate an existing draft, else store as donor.
 */
function lrg_draft_donor_consider(mysqli $conn, int $match, array $matchdata, array $t_draft = [], array $t_matchlines = []): void {
  global $schema;
  if (empty($schema['matches_draft_donors'])) return;

  // Prefer a real draft; trivial/fallback $t_draft must not block picks_bans.
  $draft = is_array($t_draft) ? $t_draft : [];
  if (!lrg_draft_has_real_order($draft)) {
    if (!empty($matchdata['picks_bans']) && is_array($matchdata['picks_bans'])) {
      $draft = lrg_draft_from_picks_bans($matchdata['picks_bans'], $match);
    } elseif (!empty($matchdata['draft']) && is_array($matchdata['draft'])) {
      $draft = $matchdata['draft'];
    }
  }
  if (!lrg_draft_has_real_order($draft)) return;

  $blob = lrg_draft_blob_from_rows($draft);
  if (empty($blob)) return;

  if (!empty($t_matchlines)) {
    $lineup = lrg_draft_lineup_from_matchlines($t_matchlines);
  } elseif (!empty($matchdata['players'])) {
    $lineup = lrg_draft_lineup_from_players($matchdata['players']);
  } elseif (!empty($matchdata['matchlines'])) {
    $lineup = lrg_draft_lineup_from_matchlines($matchdata['matchlines']);
  } else {
    return;
  }

  $blob = lrg_draft_align_picks_to_lineup($blob, $lineup);

  $mode = (int)($matchdata['game_mode'] ?? $matchdata['mode'] ?? $matchdata['matches']['modeID'] ?? $matchdata['modeID'] ?? 0);

  $target = lrg_draft_find_allpick_target($conn, $lineup, $match);
  if ($target) {
    if (lrg_draft_insert_rows($conn, $target, $blob)) {
      lrg_draft_store_donor($conn, $match, $blob, $lineup, $mode ?: null);
      lrg_draft_mark_donated($conn, $match, $target);
      if ($mode > 0) {
        $conn->query("UPDATE matches SET modeID = $mode WHERE matchid = $target");
      }
      echo("..Donated draft to $target.");
      return;
    }
  }

  lrg_draft_store_donor($conn, $match, $blob, $lineup, $mode ?: null);
  echo("..Stored draft donor.");
}
