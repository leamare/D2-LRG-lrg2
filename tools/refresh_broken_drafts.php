<?php

/**
 * Find Captains Mode matches in the live league DB with broken draft data,
 * remove them (DB + cache), and re-fetch.
 *
 * Broken CM (matches.modeID = 2) means any of:
 *   - no ban rows in draft
 *   - only one distinct draft stage
 *   - all draft rows share the same `order` value
 *
 * Usage (from repo root):
 *   php tools/refresh_broken_drafts.php -lLEAGUE_TAG [-c cache] [-n] [-S]
 *
 *   -c  fetch cache dir (default: cache)
 *   -n  dry-run — list broken match ids only
 *   -S  pass -S to rg_fetcher (Stratz)
 */

$root = dirname(__DIR__);
chdir($root);

require_once $root . '/head.php';

if (!isset($lrg_league_tag) || !isset($lrg_sql_db)) {
    die("[F] Pass -lLEAGUE_TAG (league must exist under leagues/).\n");
}

$tool_opts = getopt('l:c:nS');
$cacheDir = $tool_opts['c'] ?? 'cache';
if (is_array($cacheDir)) {
    $cacheDir = end($cacheDir);
}
$cacheDir = (string)$cacheDir;
$dryRun = isset($tool_opts['n']);
$useStratz = isset($tool_opts['S']);

$conn = lrg_mysqli_connect($lrg_sql_db);

// CM matches whose draft is missing bans, collapsed to one stage, or has a
// single shared order (fetcher fallback writes order=0 on every pick).
$sql = "
  SELECT m.matchid
  FROM matches m
  LEFT JOIN draft d ON d.matchid = m.matchid
  WHERE m.modeID = 2
  GROUP BY m.matchid
  HAVING
    COALESCE(SUM(CASE WHEN d.is_pick = 0 THEN 1 ELSE 0 END), 0) = 0
    OR COUNT(DISTINCT d.stage) <= 1
    OR (
      COUNT(d.matchid) > 0
      AND MIN(d.`order`) = MAX(d.`order`)
    )
  ORDER BY m.matchid ASC
";

if ($conn->multi_query($sql) !== true) {
    die("[F] Draft query failed: " . $conn->error . "\n");
}
$res = $conn->store_result();
if ($res === false) {
    die("[F] Draft query returned no result set: " . $conn->error . "\n");
}

$broken = [];
while ($row = $res->fetch_row()) {
    $broken[] = (string)$row[0];
}
$res->free_result();
while ($conn->more_results() && $conn->next_result()) {
    if ($r = $conn->store_result()) {
        $r->free_result();
    }
}

$count = count($broken);
echo "[ ] Broken CM drafts in `{$lrg_league_tag}`: {$count}\n";
if ($count === 0) {
    echo "[S] Nothing to fix.\n";
    exit(0);
}
foreach ($broken as $mid) {
    echo "  - {$mid}\n";
}

if ($dryRun) {
    echo "[S] Dry-run — no changes made.\n";
    exit(0);
}

$tmpList = $root . '/tmp/broken_drafts_' . $lrg_league_tag . '_' . time() . '.list';
if (!is_dir($root . '/tmp')) {
    mkdir($root . '/tmp', 0755, true);
}
file_put_contents($tmpList, implode("\n", $broken) . "\n");
echo "[ ] Wrote matchlist: {$tmpList}\n";

$php = escapeshellarg(PHP_BINARY);

$rmMatches = escapeshellarg($root . '/tools/remove_matches.php');
$cmd = "{$php} {$rmMatches} -l" . escapeshellarg($lrg_league_tag) . ' -f' . escapeshellarg($tmpList);
echo "[ ] Removing from DB: {$cmd}\n";
passthru($cmd, $code);
if ($code !== 0) {
    die("[F] remove_matches failed (exit {$code})\n");
}

$rmCached = escapeshellarg($root . '/tools/remove_cached.php');
$cmd = "{$php} {$rmCached} -f" . escapeshellarg($tmpList) . ' -c' . escapeshellarg($cacheDir);
echo "[ ] Removing cache: {$cmd}\n";
passthru($cmd, $code);
if ($code !== 0) {
    die("[F] remove_cached failed (exit {$code})\n");
}

$fetcher = escapeshellarg($root . '/rg_fetcher.php');
$ids = implode("\n", $broken);
$fetchFlags = '-l' . escapeshellarg($lrg_league_tag) . ' -L -W -c' . escapeshellarg($cacheDir);
if ($useStratz) {
    $fetchFlags .= ' -S';
}
$cmd = 'echo ' . escapeshellarg($ids) . " | {$php} {$fetcher} {$fetchFlags}";
echo "[ ] Re-fetching: {$cmd}\n";
passthru($cmd, $code);
if ($code !== 0) {
    echo "[W] rg_fetcher exited with code {$code} — check failed dumps in tmp/\n";
}

@unlink($tmpList);
echo "[S] Refreshed {$count} broken CM draft match(es) for `{$lrg_league_tag}`.\n";
