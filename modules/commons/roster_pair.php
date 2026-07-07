<?php

function series_report_roster_sig(array &$report, $mid, string $side, int $team_id): string {
  $pids = [];
  foreach (($report['matches'][$mid] ?? []) as $pl) {
    $on_side = !empty($pl['radiant']) ? 'radiant' : 'dire';
    if ($on_side !== $side) {
      continue;
    }
    $pid = (int)($pl['player'] ?? 0);
    if ($pid) {
      $pids[] = $pid;
    }
  }
  sort($pids);
  if (count($pids) >= 4) {
    return 'r:' . implode(',', $pids);
  }
  if ($team_id) {
    return 't:' . $team_id;
  }
  return 'u:0';
}

function series_report_pair_key(array &$report, $mid): string {
  $mpt = $report['match_participants_teams'][$mid] ?? [];
  $rid = (int)($mpt['radiant'] ?? 0);
  $did = (int)($mpt['dire'] ?? 0);
  $a = series_report_roster_sig($report, $mid, 'radiant', $rid);
  $b = series_report_roster_sig($report, $mid, 'dire', $did);
  return $a < $b ? "$a~$b" : "$b~$a";
}

function series_report_team_pair_key(array &$report, $mid): string {
  $mpt = $report['match_participants_teams'][$mid] ?? [];
  $teams = [(int)($mpt['radiant'] ?? 0), (int)($mpt['dire'] ?? 0)];
  sort($teams);
  if ($teams[0] && $teams[1]) {
    return $teams[0] . '.' . $teams[1];
  }
  return series_report_pair_key($report, $mid);
}

function series_side_player_ids(array &$report, $mid, string $side): array {
  $pids = [];
  foreach (($report['matches'][$mid] ?? []) as $pl) {
    $on_side = !empty($pl['radiant']) ? 'radiant' : 'dire';
    if ($on_side !== $side) {
      continue;
    }
    $pid = (int)($pl['player'] ?? 0);
    if ($pid) {
      $pids[] = $pid;
    }
  }
  sort($pids);
  return $pids;
}

/** Both sides share >=4 players (either orientation) — likely the same series. */
function series_rosters_likely_same_series(array &$report, $mid1, $mid2): bool {
  $orientations = [
    ['radiant', 'radiant', 'dire', 'dire'],
    ['radiant', 'dire', 'dire', 'radiant'],
  ];
  foreach ($orientations as [$s1a, $s2a, $s1b, $s2b]) {
    $a1 = series_side_player_ids($report, $mid1, $s1a);
    $a2 = series_side_player_ids($report, $mid2, $s2a);
    $b1 = series_side_player_ids($report, $mid1, $s1b);
    $b2 = series_side_player_ids($report, $mid2, $s2b);
    if (count($a1) < 4 || count($a2) < 4 || count($b1) < 4 || count($b2) < 4) {
      continue;
    }
    if (count(array_intersect($a1, $a2)) >= 4 && count(array_intersect($b1, $b2)) >= 4) {
      return true;
    }
  }
  return false;
}

/**
 * Resolve a stable meeting key for series grouping.
 * Primary: sorted team-id pair. On id mismatch vs a known pair, alias via roster overlap.
 */
function series_resolve_meeting_key(array &$report, $mid, string $tid_key, array &$canon): string {
  if (strpos($tid_key, '~') !== false) {
    return $tid_key;
  }

  if (isset($canon['aliases'][$tid_key])) {
    return $canon['aliases'][$tid_key];
  }

  if (isset($canon['refs'][$tid_key])) {
    $canon['refs'][$tid_key] = $mid;
    return $tid_key;
  }

  foreach ($canon['refs'] as $canonical_key => $ref_mid) {
    if ($canonical_key === $tid_key) {
      continue;
    }
    if (series_rosters_likely_same_series($report, $mid, $ref_mid)) {
      $canon['aliases'][$tid_key] = $canonical_key;
      return $canonical_key;
    }
  }

  $canon['refs'][$tid_key] = $mid;
  return $tid_key;
}

function series_canon_by_roster(array &$report, array $mids): array {
  $sig_to_tids = [];
  foreach ($mids as $mid) {
    foreach (['radiant', 'dire'] as $side) {
      $tid = (int)($report['match_participants_teams'][$mid][$side] ?? 0);
      if (!$tid) {
        continue;
      }
      $sig = series_report_roster_sig($report, $mid, $side, $tid);
      $sig_to_tids[$sig][$tid] = ($sig_to_tids[$sig][$tid] ?? 0) + 1;
    }
  }

  $canon = [];
  foreach ($sig_to_tids as $sig => $cnts) {
    arsort($cnts);
    $canon[$sig] = (int)array_key_first($cnts);
  }

  return $canon;
}

function series_canonical_team_id(array &$report, $mid, string $side, array $canon_by_sig): int {
  $tid = (int)($report['match_participants_teams'][$mid][$side] ?? 0);
  $sig = series_report_roster_sig($report, $mid, $side, $tid);
  return $canon_by_sig[$sig] ?? $tid;
}

function series_match_scores(array &$report, array $mids): array {
  $canon = series_canon_by_roster($report, $mids);
  $scores = [];

  foreach ($mids as $match) {
    if (!isset($report['match_participants_teams'][$match])) {
      continue;
    }
    $radiant = series_canonical_team_id($report, $match, 'radiant', $canon);
    $dire = series_canonical_team_id($report, $match, 'dire', $canon);
    $scores[$radiant] = ($scores[$radiant] ?? 0) + ($report['matches_additional'][$match]['radiant_win'] ? 1 : 0);
    $scores[$dire] = ($scores[$dire] ?? 0) + ($report['matches_additional'][$match]['radiant_win'] ? 0 : 1);
  }

  return $scores;
}
