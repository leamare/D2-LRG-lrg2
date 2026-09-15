<?php

/**
 * Assign stored CM/draft-donor blobs onto matching remade All Pick (mode 1) matches.
 *
 * A donor (short or low-score match with a real draft) donates when:
 *   - 9/10 heroes match on the same sides
 *   - 8/10 players match on the same sides
 *   - target is All Pick (mode 1) with no real draft
 *
 * Usage (from repo root):
 *   php tools/donate_draft.php -lLEAGUE_TAG [-n]
 *
 *   -n  dry-run
 */

$root = dirname(__DIR__);
chdir($root);

require_once $root . '/head.php';
require_once $root . '/modules/fetcher/match_fail.php';
require_once $root . '/modules/fetcher/draft_donate.php';

if (!isset($lrg_league_tag) || !isset($lrg_sql_db)) {
  die("[F] Pass -lLEAGUE_TAG.\n");
}

$tool_opts = getopt('l:n');
$dry = isset($tool_opts['n']);

$conn = lrg_mysqli_connect($lrg_sql_db);
if ($conn->connect_error) {
  die("[F] Connection failed: ".$conn->connect_error."\n");
}
$conn->set_charset('utf8mb4');

$schema_quiet = true;
require_once $root . '/modules/commons/schema.php';

if (empty($schema['matches_draft_donors'])) {
  die("[F] matches_draft_donors is missing. Run php tools/update_schema.php -l$lrg_league_tag\n");
}

$donated = 0;
$stored_unused = 0;

$r = $conn->query("SELECT matchid, draft, heroes, players, modeID FROM matches_draft_donors WHERE donated_to IS NULL");
if (!$r) {
  die("[F] ".$conn->error."\n");
}

while ($row = $r->fetch_assoc()) {
  $donor_id = (int)$row['matchid'];
  $draft = json_decode($row['draft'] ?? '[]', true) ?: [];
  if (empty($draft)) continue;
  $lineup = lrg_draft_side_sets(
    json_decode($row['heroes'] ?? '[]', true) ?: [],
    json_decode($row['players'] ?? '[]', true) ?: []
  );
  $mode = isset($row['modeID']) ? (int)$row['modeID'] : 0;

  $target = lrg_draft_find_allpick_target($conn, $lineup, $donor_id);
  if (!$target) {
    $stored_unused++;
    continue;
  }

  echo "[ ] Donor $donor_id -> $target";
  if ($dry) {
    echo " (dry-run)\n";
    $donated++;
    continue;
  }
  if (lrg_draft_insert_rows($conn, $target, $draft)) {
    lrg_draft_mark_donated($conn, $donor_id, $target);
    if ($mode > 0) {
      $conn->query("UPDATE matches SET modeID = $mode WHERE matchid = $target");
    }
    echo " OK\n";
    $donated++;
  } else {
    echo " skipped (target already has a real draft)\n";
  }
}

echo "[S] Donated: $donated; unused donors: $stored_unused".($dry ? " (dry-run)" : "")."\n";
