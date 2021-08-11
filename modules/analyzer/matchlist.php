<?php
$result["matches"] = array();

$sql = "SELECT matchid, heroid, playerid, isRadiant FROM matchlines;";

if ($conn->multi_query($sql) === TRUE) echo "[S] MATCHES LIST.\n";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

for ($row = $query_res->fetch_row();
     $row != null;
     $row = $query_res->fetch_row()) {
  if (!isset($result["matches"][$row[0]])) {
    $result["matches"][$row[0]] = [];
  }
  $result["matches"][$row[0]][] = [
    "hero" => (int)$row[1],
    "player" => (int)$row[2],
    "radiant" => (int)$row[3]
  ];
}


$result["matches_additional"] = array();
foreach ($result["matches"] as $matchid => $matchinfo) {
  $sql = "SELECT duration, cluster, modeID, radiantWin, start_date FROM matches WHERE matchid = $matchid;";

  if ($conn->multi_query($sql) === TRUE);
  else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();
  $row = $query_res->fetch_row();

  $result["matches_additional"][$matchid] = array (
    "duration" => $row[0],
    "cluster" => $row[1],
    "game_mode" => $row[2],
    "radiant_win" => $row[3],
    "date" => $row[4]
  );
  $query_res->free_result();

  $sql = "SELECT SUM(kills), SUM(networth) FROM matchlines WHERE matchid = $matchid GROUP BY isRadiant ORDER BY isRadiant ASC;";

  if ($conn->multi_query($sql) === TRUE);
  else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();
  $row = $query_res->fetch_row();

  $result["matches_additional"][$matchid]["dire_score"] = $row[0];
  $result["matches_additional"][$matchid]["dire_nw"] = $row[1];

  $row = $query_res->fetch_row();

  $result["matches_additional"][$matchid]["radiant_score"] = $row[0];
  $result["matches_additional"][$matchid]["radiant_nw"] = $row[1];

  $query_res->free_result();
}

$sql = "SELECT matchid, is_radiant, hero_id, stage FROM draft WHERE is_pick = 0 ORDER BY stage ASC;";

if ($conn->multi_query($sql) === TRUE);
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if (!isset($result["matches_additional"][$row[0]]['bans'])) {
    $result["matches_additional"][$row[0]]['bans'] = [ [], [] ];
  }
  $result["matches_additional"][$row[0]]['bans'][$row[1]][] = [ (int)$row[2], (int)$row[3] ];
}

$query_res->free_result();

if($lg_settings['main']['teams']) {
  $result["match_participants_teams"] = array();
  $sql = "SELECT matchid, teamid, is_radiant FROM teams_matches;";

  if ($conn->multi_query($sql) === TRUE) echo "[S] PARTICIPANTS LIST.\n";
  else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    if(!isset($result["match_participants_teams"]))
      $result["match_participants_teams"][$row[0]] = array();
    if($row[2])
      $result["match_participants_teams"][$row[0]]["radiant"] = $row[1];
    else
      $result["match_participants_teams"][$row[0]]["dire"] = $row[1];
  }

  $query_res->free_result();
}
?>
