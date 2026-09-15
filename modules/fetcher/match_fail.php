<?php

function lrg_match_fail_limit(): int {
  global $lg_settings;
  $n = (int)($lg_settings['fail_retries'] ?? $lg_settings['fetch']['fail_retries'] ?? 15);
  return max(0, $n);
}

function lrg_match_fail_touch(mysqli $conn, int $matchid, string $kind, bool $is_donor = false): void {
  global $schema;
  if (empty($schema['matches_failed'])) return;

  $miss = $kind === 'missing' ? 1 : 0;
  $unp = $kind === 'unparsed' ? 1 : 0;
  $now = time();
  $donor = $is_donor ? 1 : 0;
  $sql = "INSERT INTO matches_failed (matchid, retries_missing, retries_unparsed, last_attempt, is_draft_donor)
    VALUES ($matchid, $miss, $unp, $now, $donor)
    ON DUPLICATE KEY UPDATE
      retries_missing = retries_missing + $miss,
      retries_unparsed = retries_unparsed + $unp,
      last_attempt = $now,
      is_draft_donor = IF($donor = 1, 1, is_draft_donor)";
  $conn->query($sql);
}

function lrg_match_fail_counts(mysqli $conn, int $matchid): array {
  global $schema;
  if (empty($schema['matches_failed'])) {
    return ['retries_missing' => 0, 'retries_unparsed' => 0];
  }
  $r = $conn->query("SELECT retries_missing, retries_unparsed FROM matches_failed WHERE matchid = $matchid LIMIT 1");
  if (!$r || !$r->num_rows) {
    return ['retries_missing' => 0, 'retries_unparsed' => 0];
  }
  $row = $r->fetch_assoc();
  return [
    'retries_missing' => (int)$row['retries_missing'],
    'retries_unparsed' => (int)$row['retries_unparsed'],
  ];
}

function lrg_match_fail_exceeded(mysqli $conn, int $matchid): bool {
  $lim = lrg_match_fail_limit();
  $c = lrg_match_fail_counts($conn, $matchid);
  return $c['retries_missing'] >= $lim;
}

function lrg_match_fail_drop_recorded(mysqli $conn, ?int $matchid = null): void {
  global $schema;
  if (empty($schema['matches_failed'])) return;
  if ($matchid) {
    $conn->query("DELETE FROM matches_failed WHERE matchid = ".(int)$matchid);
    return;
  }
  $conn->query("DELETE mf FROM matches_failed mf INNER JOIN matches m ON m.matchid = mf.matchid");
}

/** @return bool false = retry later, true = give up (skip) */
function lrg_fetch_retry_or_give_up(string $kind): bool {
  global $conn, $match, $schema;
  if ($kind === 'unparsed') return false;
  if (empty($schema['matches_failed'])) return false;
  $mid = (int)$match;
  lrg_match_fail_touch($conn, $mid, $kind);
  if (lrg_match_fail_exceeded($conn, $mid)) {
    echo("..Retries exceeded, giving up.\n");
    return true;
  }
  return false;
}

/** @return true|null true = give up, null = record as failed */
function lrg_fetch_fail_missing() {
  global $conn, $match, $schema;
  if (empty($schema['matches_failed'])) return null;
  $mid = (int)$match;
  lrg_match_fail_touch($conn, $mid, 'missing');
  if (lrg_match_fail_exceeded($conn, $mid)) {
    echo("..Retries exceeded, giving up.\n");
    return true;
  }
  return null;
}
