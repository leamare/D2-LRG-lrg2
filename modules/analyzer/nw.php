<?php

const NW_ADV_FROM = 10;
const NW_ADV_TO = 45;

function lrg_nw_adv_add(array &$bucket, array $series) {
  $last = min(NW_ADV_TO, count($series) - 1);
  for ($m = NW_ADV_FROM; $m <= $last; $m++) {
    if (!isset($series[$m]) || !is_numeric($series[$m])) continue;
    $bucket['sum'][$m] = ($bucket['sum'][$m] ?? 0) + abs((int)$series[$m]);
    $bucket['cnt'][$m] = ($bucket['cnt'][$m] ?? 0) + 1;
  }
}

function lrg_nw_adv_avg(array $bucket): array {
  if (empty($bucket['cnt'])) return [];
  $max = min(NW_ADV_TO, max(array_keys($bucket['cnt'])));
  if ($max < NW_ADV_FROM) return [];
  $out = [];
  for ($m = NW_ADV_FROM; $m <= $max; $m++) {
    $out[] = empty($bucket['cnt'][$m]) ? 0 : (int)round($bucket['sum'][$m] / $bucket['cnt'][$m]);
  }
  return $out;
}

function lrg_nw_adv_merge(array $buckets): array {
  $acc = ['sum' => [], 'cnt' => []];
  foreach ($buckets as $b) {
    foreach ($b['cnt'] as $m => $c) {
      $acc['sum'][$m] = ($acc['sum'][$m] ?? 0) + $b['sum'][$m];
      $acc['cnt'][$m] = ($acc['cnt'][$m] ?? 0) + $c;
    }
  }
  return lrg_nw_adv_avg($acc);
}

function lrg_nw_series($raw): ?array {
  if ($raw === null || $raw === '') return null;
  if (is_string($raw)) $raw = json_decode($raw, true);
  if (!is_array($raw) || !$raw) return null;
  return array_values($raw);
}

if (!empty($schema['matches_ext'])) {
  $team_of = [];
  if (!empty($schema['teams'])) {
    $tq = $conn->query("SELECT matchid, teamid FROM teams_matches");
    if ($tq) {
      while ($row = $tq->fetch_row()) {
        $team_of[(int)$row[0]][] = (int)$row[1];
      }
      $tq->free();
    }
  }

  $g = ['sum' => [], 'cnt' => []];
  $by_cluster = [];
  $by_team = [];

  echo "[ ] Networth advantage\n";
  $q = $conn->query(
    "SELECT me.matchid, m.cluster, me.nw_t, me.gold_t ".
    "FROM matches_ext me JOIN matches m ON m.matchid = me.matchid ".
    "WHERE me.nw_t IS NOT NULL OR me.gold_t IS NOT NULL",
    MYSQLI_USE_RESULT
  );
  if ($q === false) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  while ($row = $q->fetch_row()) {
    $series = lrg_nw_series($row[2]) ?? lrg_nw_series($row[3]);
    if ($series === null) continue;
    $mid = (int)$row[0];
    $cluster = (int)$row[1];
    lrg_nw_adv_add($g, $series);
    if (!isset($by_cluster[$cluster])) $by_cluster[$cluster] = ['sum' => [], 'cnt' => []];
    lrg_nw_adv_add($by_cluster[$cluster], $series);
    foreach ($team_of[$mid] ?? [] as $tid) {
      if (!isset($by_team[$tid])) $by_team[$tid] = ['sum' => [], 'cnt' => []];
      lrg_nw_adv_add($by_team[$tid], $series);
    }
  }
  $q->free();

  $avg = lrg_nw_adv_avg($g);
  if ($avg) $result['nw_adv'] = $avg;

  if (!empty($result['teams'])) {
    foreach ($result['teams'] as $tid => &$team) {
      if (!empty($by_team[$tid]['cnt'])) {
        $team['nw_adv'] = lrg_nw_adv_avg($by_team[$tid]);
      }
    }
    unset($team);
  }

  if (!empty($result['regions_data']) && !empty($result['regions'])) {
    $meta['clusters'];
    $reg_matches = [];
    foreach ($result['regions'] as $clid => $matches) {
      $rid = $meta['clusters'][$clid] ?? 0;
      $reg_matches[$rid] = ($reg_matches[$rid] ?? 0) + $matches;
    }
    $groups = $lg_settings['ana']['regions']['groups_overwrite']
      ?? $lg_settings['ana']['regions']['groups']
      ?? null;
    $region_clusters = [];
    foreach ($meta['clusters'] as $cluster => $region) {
      if (($lg_settings['ana']['regions']['group_regions'] ?? false) && !empty($groups)) {
        foreach ($groups as $gid => $rs) {
          if (in_array((int)$region, $rs)) {
            $region = (int)$gid;
            break;
          }
        }
        if ($region < 100) $region = $lg_settings['ana']['regions']['fallback_group'] ?? $region;
      }
      if (($region < 100) && (!isset($reg_matches[$region]) || (
        ($lg_settings['ana']['regions']['use_limiter_grouping'] ?? false) && $reg_matches[$region] < $limiter_median
      ))) {
        if (($lg_settings['ana']['regions']['group_regions_when_lower_than_limiter'] ?? false) && isset($lg_settings['ana']['regions']['fallback_group'])) {
          $region = $lg_settings['ana']['regions']['fallback_group'] ?? $region;
        } else {
          continue;
        }
      }
      $region_clusters[$region][] = $cluster;
    }
    foreach ($result['regions_data'] as $region => &$rdata) {
      $parts = [];
      foreach ($region_clusters[$region] ?? [] as $cluster) {
        if (!empty($by_cluster[$cluster]['cnt'])) $parts[] = $by_cluster[$cluster];
      }
      $series = lrg_nw_adv_merge($parts);
      if ($series) $rdata['nw_adv'] = $series;
    }
    unset($rdata);
  }

  unset($team_of, $g, $by_cluster, $by_team);
  echo "[S] Networth advantage\n";
}

function lrg_nw_at_points($raw, int $shift): array {
  $series = lrg_nw_series($raw);
  $out = [null, null, null, null];
  if ($series === null) return $out;
  foreach ([5, 10, 15, 20] as $i => $m) {
    $idx = $m + $shift;
    if ($idx >= 0 && isset($series[$idx]) && is_numeric($series[$idx])) {
      $out[$i] = (int)$series[$idx];
    }
  }
  return $out;
}

function lrg_nw_at_add(array &$bucket, $id, array $pts) {
  if (!isset($bucket[$id])) $bucket[$id] = [[0, 0, 0, 0], [0, 0, 0, 0]];
  for ($i = 0; $i < 4; $i++) {
    if ($pts[$i] === null) continue;
    $bucket[$id][0][$i] += $pts[$i];
    $bucket[$id][1][$i]++;
  }
}

function lrg_nw_at_pack(array $bucket): array {
  $out = [];
  foreach ($bucket as $id => $sc) {
    $vals = [];
    $any = false;
    for ($i = 0; $i < 4; $i++) {
      if ($sc[1][$i] === 0) {
        $vals[] = null;
      } else {
        $vals[] = (int)round($sc[0][$i] / $sc[1][$i]);
        $any = true;
      }
    }
    if ($any) $out[(int)$id] = $vals;
  }
  return $out;
}

if (!empty($schema['adv_matchlines_timeseries'])) {
  echo "[ ] Networth snapshots\n";

  $has_role = !empty($schema['adv_matchlines_roles']);
  $has_source = !empty($schema['matches_source']);
  $select = ["a.heroid", "a.playerid"];
  if ($has_role) $select[] = "a.role";
  if ($has_source) $select[] = "m.source";
  $select[] = "a.nw_t";
  $nw_i = count($select) - 1;
  $src_i = $has_source ? $nw_i - 1 : null;
  $role_i = $has_role ? 2 : null;
  $from = "adv_matchlines a".($has_source ? " JOIN matches m ON m.matchid = a.matchid" : "");

  $q = $conn->query(
    "SELECT ".implode(', ', $select)." FROM $from WHERE a.nw_t IS NOT NULL",
    MYSQLI_USE_RESULT
  );
  if ($q === false) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $heroes = [];
  $players = [];
  $hero_role = [];
  $player_role = [];
  while ($row = $q->fetch_row()) {
    // Stratz stores minute 1 at index 0; OpenDota stores minute 0 at index 0
    $shift = ($src_i !== null && (int)$row[$src_i] === 2) ? -1 : 0;
    $pts = lrg_nw_at_points($row[$nw_i], $shift);
    $hid = (int)$row[0];
    $pid = (int)$row[1];
    if ($hid) lrg_nw_at_add($heroes, $hid, $pts);
    if ($pid) lrg_nw_at_add($players, $pid, $pts);
    if ($role_i !== null) {
      $role = (int)$row[$role_i];
      if ($role >= 1 && $role <= 5) {
        if (!isset($hero_role[$role])) $hero_role[$role] = [];
        if (!isset($player_role[$role])) $player_role[$role] = [];
        if ($hid) lrg_nw_at_add($hero_role[$role], $hid, $pts);
        if ($pid) lrg_nw_at_add($player_role[$role], $pid, $pts);
      }
    }
  }
  $q->free();

  $packed_hr = [];
  $packed_pr = [];
  foreach ($hero_role as $role => $bucket) {
    $packed = lrg_nw_at_pack($bucket);
    if ($packed) $packed_hr[$role] = $packed;
  }
  foreach ($player_role as $role => $bucket) {
    $packed = lrg_nw_at_pack($bucket);
    if ($packed) $packed_pr[$role] = $packed;
  }
  $packed_h = lrg_nw_at_pack($heroes);
  $packed_p = lrg_nw_at_pack($players);

  if ($packed_h || $packed_p || $packed_hr || $packed_pr) {
    $result['nw_at'] = [
      'h' => $packed_h,
      'hr' => $packed_hr,
      'p' => $packed_p,
      'pr' => $packed_pr,
    ];
  }
  unset($heroes, $players, $hero_role, $player_role);
  echo "[S] Networth snapshots\n";
}
