<?php

$wheres = "";
if (!empty($players_interest)) {
  $wheres = " WHERE m1.playerid in (".implode(',', $players_interest).") and m2.playerid in (".implode(',', $players_interest).") ";
}

$result["hvh"] = [];

$sql = "SELECT m1.heroid, m2.heroid, 
      SUM(1) match_count, 
      SUM(NOT matches.radiantWin XOR m1.isRadiant) hero1_won, 
      SUM(NOT matches.radiantWin XOR m1.isRadiant)/SUM(1) h1_winrate
    FROM matchlines m1
    JOIN matchlines m2
        ON m1.matchid = m2.matchid and m1.isRadiant <> m2.isRadiant and m1.heroid < m2.heroid
    JOIN matches
      ON m1.matchid = matches.matchid
    $wheres
    GROUP BY m1.heroid, m2.heroid;";

if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for HERO VS HERO.\n";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if (!isset($result['pickban'][$row[0]]) || !isset($result['pickban'][$row[1]])) continue;
  
  $expected_pair  = $result['random']['matches_total'] ? ( $result['pickban'][$row[0]]['matches_picked']
  * $result['pickban'][$row[1]]['matches_picked']
  / $result['random']['matches_total'] )
  / 2
  : 0;

  $result["hvh"][] = array (
    "heroid1" => $row[0],
    "heroid2" => $row[1],
    "matches" => $row[2],
    "h1won" => $row[3],
    "h1winrate" => $row[4],
    "exp" => round($expected_pair)
  );
}

$query_res->free_result();

if ($lg_settings['ana']['hvh_matches']) {
  for ($i=0,$e=sizeof($result['hvh']); $i<$e; $i++) {
    $sql = "SELECT m1.matchid
        FROM matchlines m1
          JOIN matchlines m2
              ON m1.matchid = m2.matchid and m1.isRadiant <> m2.isRadiant
        WHERE m1.heroid = ".$result['hvh'][$i]['hidid1']." AND m2.heroid = ".$result['hvh'][$i]['hid2'].";";

    if ($conn->multi_query($sql) === TRUE)  ;# echo "[S] Requested data for PLAYER AGAINST PLAYER.\n";
    else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

    $query_res = $conn->store_result();

    $result['hvh'][$i]['matchids'] = array();

    for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
      $result['hvh'][$i]['matchids'][] = $row[0];
    }

    $query_res->free_result();
  }
}

$result["hvh"] = wrap_data($result["hvh"], true, true, true);
