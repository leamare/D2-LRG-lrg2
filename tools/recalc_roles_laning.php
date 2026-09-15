<?php

/**
 * Recalculate roles + laning outcomes (lane_won, isCore) from DB data already present.
 *
 * Uses matchlines (gpm, side) + adv_matchlines (lane, efficiency_at10, lh_at10).
 * No API calls.
 *
 * Usage (from repo root):
 *   php tools/recalc_roles_laning.php -lLEAGUE_TAG [-mMATCHID] [-Mmatchlist] [-n]
 *
 *   -m  single match
 *   -M  match-id list file
 *   -n  dry-run
 *   (no -m/-M = all matches that have adv_matchlines)
 */

$root = dirname(__DIR__);
chdir($root);

require_once $root . '/head.php';
require_once $root . '/modules/fetcher/match_ext.php';

if (!isset($lrg_league_tag) || !isset($lrg_sql_db)) {
  die("[F] Pass -lLEAGUE_TAG.\n");
}

$tool_opts = getopt('l:m:M:n');
$dry = isset($tool_opts['n']);
$only = isset($tool_opts['m']) ? [(int)$tool_opts['m']] : null;
if ($only === null && !empty($tool_opts['M'])) {
  $path = (string)$tool_opts['M'];
  if (!is_file($path)) die("[F] Matchlist not found: $path\n");
  $only = [];
  foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    $only[] = (int)$line;
  }
}

$conn = lrg_mysqli_connect($lrg_sql_db);
if ($conn->connect_error) {
  die("[F] Connection failed: ".$conn->connect_error."\n");
}
$conn->set_charset('utf8mb4');

$schema_quiet = true;
require_once $root . '/modules/commons/schema.php';

if (empty($schema['adv_matchlines_roles'])) {
  die("[F] adv_matchlines roles/lane_won columns missing. Run php tools/update_schema.php -l$lrg_league_tag\n");
}

if ($only !== null) {
  $ids = array_values(array_filter($only));
} else {
  $ids = [];
  $r = $conn->query("SELECT DISTINCT matchid FROM adv_matchlines ORDER BY matchid");
  if (!$r) die("[F] ".$conn->error."\n");
  while ($row = $r->fetch_assoc()) $ids[] = (int)$row['matchid'];
}

echo "[ ] Recalc roles/laning for ".count($ids)." match(es)".($dry ? " (dry-run)" : "")."\n";

$updated = 0;
$skipped = 0;
foreach ($ids as $mid) {
  $t_matchlines = [];
  $r = $conn->query("SELECT playerid, heroid, isRadiant, gpm FROM matchlines WHERE matchid = $mid");
  if (!$r || !$r->num_rows) {
    echo "[-] $mid no matchlines\n";
    $skipped++;
    continue;
  }
  while ($row = $r->fetch_assoc()) $t_matchlines[] = $row;

  $t_adv = [];
  $r = $conn->query("SELECT * FROM adv_matchlines WHERE matchid = $mid");
  if (!$r || !$r->num_rows) {
    echo "[-] $mid no adv_matchlines\n";
    $skipped++;
    continue;
  }
  while ($row = $r->fetch_assoc()) $t_adv[] = $row;

  $before = [];
  foreach ($t_adv as $a) {
    $before[(int)$a['playerid']] = [
      'role' => (int)($a['role'] ?? 0),
      'lane_won' => (int)($a['lane_won'] ?? 0),
      'isCore' => (int)($a['isCore'] ?? 0),
    ];
  }

  lrg_recalc_roles_laning($t_adv, $t_matchlines);

  $changed = 0;
  foreach ($t_adv as $a) {
    $pid = (int)$a['playerid'];
    $b = $before[$pid] ?? null;
    if (!$b
        || $b['role'] !== (int)$a['role']
        || $b['lane_won'] !== (int)$a['lane_won']
        || $b['isCore'] !== (int)$a['isCore']) {
      $changed++;
    }
  }

  if (!$changed) {
    echo "[.] $mid unchanged\n";
    continue;
  }

  if ($dry) {
    echo "[n] $mid would update $changed player(s)\n";
    $updated++;
    continue;
  }

  foreach ($t_adv as $a) {
    $sql = "UPDATE adv_matchlines SET role = ".(int)$a['role'].
      ", lane_won = ".(int)$a['lane_won'].
      ", isCore = ".(int)$a['isCore'].
      " WHERE matchid = $mid AND playerid = ".(int)$a['playerid'];
    if (!$conn->query($sql)) {
      echo "[E] $mid player ".$a['playerid'].": ".$conn->error."\n";
      continue 2;
    }
  }
  echo "[+] $mid updated $changed player(s)\n";
  $updated++;
}

echo "[ ] Done. updated=$updated skipped=$skipped\n";
