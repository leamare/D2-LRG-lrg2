<?php

require_once('head.php');
include_once("modules/commons/utf8ize.php");

$_clean = isset($options['F']);

$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);

if ($conn->connect_error) die("[F] Connection to SQL server failed: ".$conn->connect_error."\n");

$conn->set_charset("utf8");

include_once("modules/commons/schema.php");
include_once("modules/commons/fantasy_mvp.php");

if (!$schema['fantasy_mvp']) {
  create_fantasy_mvp_tables($conn);
}

$matches = [];

if ($_clean) {
  $sql = "DELETE FROM fantasy_mvp_points;";
  $conn->query($sql);
  $sql = "DELETE FROM fantasy_mvp_awards;";
  $conn->query($sql);
}

$sql = "SELECT matchid FROM matches WHERE matchid NOT IN (SELECT DISTINCT matchid FROM fantasy_mvp_points);";

$query_res = $conn->query($sql);

while ($row = $query_res->fetch_assoc()) {
  $matches[] = (int)$row['matchid'];
}

$query_res->free_result();

foreach ($matches as $match) {
  $t_match = [];
  $t_matchlines = [];
  $t_adv_matchlines = [];

  $sql = "SELECT * FROM matches WHERE matchid = ".$match.";";
  $query_res = $conn->query($sql);
  $t_match = $query_res->fetch_assoc();
  $query_res->free_result();

  $sql = "SELECT * FROM matchlines WHERE matchid = ".$match.";";
  $query_res = $conn->query($sql);
  while ($row = $query_res->fetch_assoc()) {
    $t_matchlines[] = $row;
  }
  $query_res->free_result();

  $sql = "SELECT * FROM adv_matchlines WHERE matchid = ".$match.";";
  $query_res = $conn->query($sql);
  while ($row = $query_res->fetch_assoc()) {
    $t_adv_matchlines[] = $row;
  }
  $query_res->free_result();

  echo "[ ] Match ".$match.": ";

  if (file_exists("cache/".$match.".mvplive.json")) {
    [ $fantasy_mvp_points, $fantasy_mvp_awards ] = load_mvp_from_live_cache("cache/".$match.".mvplive.json", $t_matchlines);
    echo "loaded from live cache...";
  } else {
    [ $fantasy_mvp_points, $fantasy_mvp_awards ] = generate_fantasy_mvp($t_match, $t_matchlines, $t_adv_matchlines);
    echo "generated...";
  }

  if (empty($fantasy_mvp_points) || empty($fantasy_mvp_awards)) {
    echo "empty MVP calc, skipping...\n";
    continue;
  }

  $lines = [];

  $sql = "INSERT INTO fantasy_mvp_points (
    matchid,
    playerid,
    heroid,
    kills,
    deaths,
    assists,
    creeps,
    gpm,
    xpm,
    obs_placed,
    stacks,
    stuns,
    teamfight_part,
    damage,
    healing,
    damage_taken,
    hero_damage_taken_bonus,
    hero_damage_taken_penalty,
    tower_damage,
    obs_kills,
    cour_kills,
    buybacks,
    total_points
  ) VALUES ";

  foreach ($fantasy_mvp_points as $row) {
    $lines[] = "(".
      $row['matchid'].", ".
      $row['playerid'].", ".
      $row['heroid'].", ".
      $row['kills'].", ".
      $row['deaths'].", ".
      $row['assists'].", ".
      $row['creeps'].", ".
      $row['gpm'].", ".
      $row['xpm'].", ".
      $row['obs_placed'].", ".
      $row['stacks'].", ".
      $row['stuns'].", ".
      $row['teamfight_part'].", ".
      $row['damage'].", ".
      $row['healing'].", ".
      $row['damage_taken'].", ".
      $row['hero_damage_taken_bonus'].", ".
      $row['hero_damage_taken_penalty'].", ".
      $row['tower_damage'].", ".
      $row['obs_kills'].", ".
      $row['cour_kills'].", ".
      $row['buybacks'].", ".
      $row['total_points'].")";
  }

  $sql .= implode(",\n", $lines).";";

  $conn->query($sql);

  $sql = "INSERT INTO fantasy_mvp_awards (matchid, playerid, heroid, total_points, mvp, mvp_losing, core, support, lvp) VALUES ";

  $lines = [];
  foreach ($fantasy_mvp_awards as $row) {
    $lines[] = "(".
      $row['matchid'].", ".
      $row['playerid'].", ".
      $row['heroid'].", ".
      $row['total_points'].", ".
      $row['mvp'].", ".
      $row['mvp_losing'].", ".
      $row['core'].", ".
      $row['support'].", ".
      $row['lvp'].")";
  }

  $sql .= implode(", ", $lines).";";

  $conn->query($sql);

  echo "OK\n";
}
