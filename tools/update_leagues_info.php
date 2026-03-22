<?php

require_once('head.php');

$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);

if ($conn->connect_error) {
  die("[F] Connection to SQL server failed: " . $conn->connect_error . "\n");
}

include_once("modules/commons/schema.php");

if (empty($schema['leagues'])) {
  die("[F] Report schema has no `leagues` table, exiting.\n");
}

require_once("modules/fetcher/fetch_league_info.php");

$options = getopt("d:");
$cooldown = isset($options['d']) ? (int)$options['d'] : 0;

$col_res = $conn->query("SHOW COLUMNS FROM leagues");
if ($col_res === false) {
  die("[F] Unexpected problems when requesting database.\n" . $conn->error . "\n");
}

$league_cols = [];
while ($row = $col_res->fetch_assoc()) {
  $league_cols[$row['Field']] = $row;
}
$col_res->free();

$has_lid = isset($league_cols['lid']);

if (!isset($league_cols['ticket_id'])) {
  die("[F] `leagues` table has no `ticket_id` column.\n");
}

if ($has_lid) {
  $ticket_meta = $league_cols['ticket_id'] ?? null;
  $ticket_nullable = $ticket_meta && strtoupper($ticket_meta['Null'] ?? '') === 'YES';
  if ($ticket_nullable) {
    $sql = "SELECT lid FROM leagues WHERE ticket_id IS NULL OR ticket_id = 0;";
  } else {
    $sql = "SELECT lid FROM leagues WHERE ticket_id = 0;";
  }
} else {
  $sql = "SELECT DISTINCT m.leagueID AS lid FROM matches m
    LEFT JOIN leagues l ON l.ticket_id = m.leagueID
    WHERE m.leagueID > 0 AND l.ticket_id IS NULL;";
}

$res = $conn->query($sql);
if ($res === false) {
  die("[F] Unexpected problems when requesting database.\n" . $conn->error . "\n");
}

$lids = [];
while ($row = $res->fetch_row()) {
  $lids[] = (int)$row[0];
}
$res->free();

echo "# Leagues to refresh: " . count($lids) . "\n";

foreach ($lids as $lid) {
  echo "..Fetching league info for {$lid}..";
  $info = fetch_league_info($lid);

  if (!$info) {
    echo " not available.\n";
    if ($cooldown > 0) {
      sleep($cooldown);
    }
    continue;
  }

  $tid = (int)$info['ticket_id'];
  $name = $conn->real_escape_string($info['name']);
  $url = $info['url'] !== null && $info['url'] !== ''
    ? "'" . $conn->real_escape_string($info['url']) . "'"
    : "NULL";
  $desc = $info['description'] !== null && $info['description'] !== ''
    ? "'" . $conn->real_escape_string($info['description']) . "'"
    : "NULL";

  if ($has_lid) {
    $sql = "UPDATE leagues SET ticket_id = {$tid}, name = '{$name}', url = {$url}, description = {$desc} WHERE lid = {$lid}";
  } else {
    $sql = "INSERT INTO leagues (ticket_id, name, url, description) VALUES ({$tid}, '{$name}', {$url}, {$desc})
      ON DUPLICATE KEY UPDATE name = VALUES(name), url = VALUES(url), description = VALUES(description);";
  }

  if ($conn->query($sql) === true) {
    echo " OK.\n";
  } else {
    echo " ERROR (" . $conn->error . ").\n";
  }

  if ($cooldown > 0) {
    sleep($cooldown);
  }
}

echo "Done.\n";
