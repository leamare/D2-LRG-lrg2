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
