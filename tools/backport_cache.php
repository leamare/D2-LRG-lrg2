<?php

require_once('head.php');
$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);

if ($conn->connect_error) die("[F] Connection to SQL server failed: ".$conn->connect_error."\n");

$sql = "SELECT matchid FROM matches;";

if ($conn->multi_query($sql) === TRUE) echo "[S] Requested MatchIDs.\n";
else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n");

$query_res = $conn->store_result();
for ($matches = [], $row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  $matches[] = $row[0];
}
$query_res->free_result();

foreach ($matches as $m) {
  $match = [];

  $q = "select * from matches where matchid = $m;";
  $match['matches'] = instaquery($conn, $q)[0];

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

  if($lg_settings['main']['teams']) {
    $q = "select * from teams_matches where matchid = $m;";
    $match['teams_matches'] = instaquery($conn, $q);

    $teams = [];
    foreach ($match['teams_matches'] as $tm) {
      $teams[] = $tm['teamid'];
    }

    $q = "select * from teams where teamid in (".implode(',', $teams).");";
    $match['teams'] = instaquery($conn, $q);

    $q = "select * from teams_rosters where teamid in (".implode(',', $teams).");";
    $match['teams_rosters'] = instaquery($conn, $q);
  }


  $out = json_encode($match, JSON_PRETTY_PRINT);
  file_put_contents("cache/$m.lrgcache.json", $out);
}