<?php

require_once('head.php');
include_once("modules/commons/utf8ize.php");
$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);

if ($conn->connect_error) die("[F] Connection to SQL server failed: ".$conn->connect_error."\n");

$conn->set_charset("utf8");

include_once("modules/commons/schema.php");

$skip = isset($options['s']);

if(isset($options['c'])) {
  $file = $options['c'];
  $matches = explode("\n", file_get_contents($file));
} else {
  if(isset($options['T'])) {
    $endt = isset($options['e']) ? $options['e'] : 0;
    $tp = strtotime($options['T'], 0);

    if (!$endt) {
      $sql = "select max(start_date) from matches;";

      if ($conn->multi_query($sql) !== TRUE) die("[F] Unexpected problems when recording to database.\n".$conn->error."\n");

      $query_res = $conn->store_result();
      $row = $query_res->fetch_row();
      if (!$row) $endt = time();
      else $endt = (int)$row[0];
      $query_res->free_result();
    }

    $sql = "SELECT matchid FROM matches WHERE start_date >= ".($endt-$tp)." AND start_date <= $endt".";";
  } else {
    $sql = "SELECT matchid FROM matches;";
  }

  if ($conn->multi_query($sql) === TRUE) echo "[S] Requested MatchIDs.\n";
  else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n");

  $query_res = $conn->store_result();
  for ($matches = [], $row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    $matches[] = $row[0];
  }
  $query_res->free_result();
}

$sz = sizeof($matches);

for ($i = 0; $i < $sz; $i++) {
  $m = $matches[$i];
  if (empty($m) || $m[0] === '#') continue;

  if ($skip && file_exists("cache/$m.lrgcache.json")) {
    echo "[ ] ($i/$sz) cache/$m.lrgcache.json exists, skipping\n";
    continue;
  }

  $match = [];

  $q = "select * from matches where matchid = $m;";
  $r = instaquery($conn, $q);
  if (empty($r)) continue;
  $match['matches'] = $r[0];

  $q = "select * from matchlines where matchid = $m;";
  $match['matchlines'] = instaquery($conn, $q);

  $q = "select * from adv_matchlines where matchid = $m;";
  $match['adv_matchlines'] = instaquery($conn, $q);

  $q = "select * from draft where matchid = $m;";
  $match['draft'] = instaquery($conn, $q);

  $q = "select players.playerID, players.nickname from players 
    join matchlines on matchlines.playerid = players.playerID 
    where matchlines.matchid = $m;";
  $match['players'] = instaquery($conn, $q);

  if ($schema['skill_builds']) {
    $q = "select * from skill_builds where matchid = $m;";
    $match['skill_builds'] = instaquery($conn, $q);
  }

  if ($schema['starting_items']) {
    $q = "select * from starting_items where matchid = $m;";
    $match['starting_items'] = instaquery($conn, $q);
  }

  if ($schema['wards']) {
    $q = "select * from wards where matchid = $m;";
    $match['wards'] = instaquery($conn, $q);
  }

  if($lg_settings['main']['teams']) {
    $q = "select * from teams_matches where matchid = $m;";
    $match['teams_matches'] = instaquery($conn, $q);

    $teams = [];
    foreach ($match['teams_matches'] as $tm) {
      $teams[] = $tm['teamid'];
    }

    if (!empty($teams)) {
      $q = "select * from teams where teamid in (".implode(',', $teams).");";
      $match['teams'] = instaquery($conn, $q);
    }

    if (!empty($teams)) {
      $q = "select * from teams_rosters where teamid in (".implode(',', $teams).");";
      $match['teams_rosters'] = instaquery($conn, $q);
    }
  }

  if($lg_settings['main']['items']) {
    $q = "select * from items where matchid = $m;";
    $match['items'] = instaquery($conn, $q);
  }

  $out = json_encode(utf8ize($match));
  if (empty($out)) {
    echo "[E] ($i/$sz) Empty response for match $m\n";
    $sz++;
    $matches[] = $m;
    continue;
  }

  file_put_contents("cache/$m.lrgcache.json", $out);
  echo "[ ] ($i/$sz) backported $m to cache/$m.lrgcache.json\n";
}