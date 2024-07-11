<?php

$wheres = "";
if (!empty($players_interest)) {
  $wheres = " WHERE m1.playerid in (".implode(',', $players_interest).") and m2.playerid in (".implode(',', $players_interest).") ";
}

$result["hvh_v"] = [];

$sql = "SELECT m1.heroid, m1.variant, m2.heroid, m2.variant,
      SUM(1) match_count, 
      SUM(matches.radiantWin = m1.isRadiant) hero1_won
    FROM matchlines m1
    JOIN matchlines m2
        ON m1.matchid = m2.matchid and m1.isRadiant <> m2.isRadiant and m1.heroid < m2.heroid
    JOIN matches
      ON m1.matchid = matches.matchid
    $wheres
    GROUP BY m1.heroid, m2.heroid, m1.variant, m2.variant;";

if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for HERO VS HERO VARIANTS.\n";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if (!isset($result['pickban'][$row[0]]) || !isset($result['pickban'][$row[1]])) continue;
  
  $expected_pair  = $result['random']['matches_total'] ? ( $result['hero_variants'][$row[0].'-'.$row[1]]['m']
  * $result['hero_variants'][$row[2].'-'.$row[3]]['m']
  / $result['random']['matches_total'] )
  / 2
  : 0;

  $result["hvh_v"][] = [
    "heroid1" => $row[0].'-'.$row[1],
    "heroid2" => $row[2].'-'.$row[3],
    "matches" => $row[4],
    "h1won" => $row[5],
    "h1winrate" => round($row[5]/$row[4], 4),
    "exp" => round($expected_pair),
  ];
}

$query_res->free_result();

// $result["hvh_v"] = wrap_data($result["hvh_v"], true, true, true);
