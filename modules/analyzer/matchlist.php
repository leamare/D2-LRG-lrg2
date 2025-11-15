<?php

function _map_row_to_award($row) {
  return ( (int)$row[2] & 0b00001 ) // mvp
    | ( ((int)$row[3] << 1) & 0b00010 ) // mvp_losing
    | ( ((int)$row[4] << 2) & 0b00100 ) // core
    | ( ((int)$row[5] << 3) & 0b01000 ) // support
    | ( ((int)$row[6] << 4) & 0b10000 ); // lvp
}

$result["matches"] = [];

$sql = "SELECT matchid, heroid, playerid, isRadiant".($schema['variant'] ? ", variant" : "")." FROM matchlines;";

if ($conn->multi_query($sql) === TRUE) echo "[S] MATCHES LIST.\n";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

for ($row = $query_res->fetch_row();
     $row != null;
     $row = $query_res->fetch_row()) {
  if (!isset($result["matches"][$row[0]])) {
    $result["matches"][$row[0]] = [];
  }
  $hero = [
    "hero" => (int)$row[1],
    "player" => (int)$row[2],
    "radiant" => (int)$row[3]
  ];

  if ($schema['variant'] && (+$row[4])) {
    $hero['var'] = +$row[4];
  }

  $result["matches"][$row[0]][] = $hero;
}


$result["matches_additional"] = [];
foreach ($result["matches"] as $matchid => $matchinfo) {
  $sql = "SELECT
    duration,
    cluster,
    modeID,
    radiantWin,
    start_date,
    version".
    ($schema['series'] ? ", seriesid" : "").
    (($lg_settings['ana']['tickets'] ?? false) ? ", leagueID" : "").
  " FROM matches WHERE matchid = $matchid;";

  if ($conn->multi_query($sql) === TRUE);
  else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();
  $row = $query_res->fetch_assoc();

  $result["matches_additional"][$matchid] = [
    "duration" => (int)$row['duration'],
    "cluster" => (int)$row['cluster'],
    "game_mode" => (int)$row['modeID'],
    "radiant_win" => (int)$row['radiantWin'],
    "date" => (int)$row['start_date'],
    "seriesid" => $schema['series'] ? (int)$row['seriesid'] : null,
  ];
  if (($lg_settings['ana']['tickets'] ?? false) && isset($row['leagueID'])) {
    $result["matches_additional"][$matchid]["lid"] = (int)$row['leagueID'];
  }
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

if ($schema['draft_order'] ?? false) {
  $sql = "SELECT matchid, hero_id, `order` FROM draft ORDER BY `order` ASC;";

  if ($conn->multi_query($sql) === TRUE);
  else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    if (!isset($result["matches_additional"][$row[0]]['bans'])) {
      $result["matches_additional"][$row[0]]['order'] = [];
    }
    $result["matches_additional"][$row[0]]['order'][] = (int)$row[1];
  }

  $query_res->free_result();
}

if ($lg_settings['main']['fantasy'] && $schema['fantasy_mvp']) {
  $sql = "SELECT matchid, playerid, mvp, mvp_losing, core, support, lvp FROM fantasy_mvp_awards;";

  if ($conn->multi_query($sql) === TRUE);
  else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    if (!isset($result["matches_additional"][$row[0]]['mvp'])) {
      $result["matches_additional"][$row[0]]['mvp'] = [];
    }
    $result["matches_additional"][$row[0]]['mvp'][$row[1]] = _map_row_to_award($row);
  }

  $query_res->free_result();
}

if ($lg_settings['main']['teams']) {
  $result["match_participants_teams"] = [];
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
