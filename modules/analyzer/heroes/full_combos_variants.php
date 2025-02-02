<?php

$wheres = "";
if (!empty($players_interest)) {
  $wheres = " WHERE fm1.playerid in (".implode(',', $players_interest).") and fm2.playerid in (".implode(',', $players_interest).") ";
}

$result["hph_v"] = [];

$sql = "SELECT fm1.heroid, fm1.variant, fm2.heroid, fm2.variant,
  COUNT(distinct matches.matchid) match_count,
  SUM(NOT matches.radiantWin XOR fm1.isRadiant) wins,
  SUM(fm1.lane = fm2.lane) lane_m,
  SUM(CASE WHEN fm1.lane = fm2.lane THEN fm1.lane_won ELSE 0 END) lane_won
FROM
( select m1.matchid, m1.heroid, m1.variant, am1.lane, m1.isRadiant, m1.playerid, am1.lane_won
  from matchlines m1 LEFT JOIN adv_matchlines am1
  ON m1.matchid = am1.matchid AND m1.heroid = am1.heroid ) fm1
JOIN
( select m2.matchid, m2.heroid, m2.variant, am2.lane, m2.isRadiant, m2.playerid, am2.lane_won
  from matchlines m2 LEFT JOIN adv_matchlines am2
  ON m2.matchid = am2.matchid AND m2.heroid = am2.heroid ) fm2
ON fm1.matchid = fm2.matchid and fm1.isRadiant = fm2.isRadiant and fm1.heroid < fm2.heroid
JOIN matches ON fm1.matchid = matches.matchid 
$wheres
GROUP BY fm1.heroid, fm1.variant, fm2.heroid, fm2.variant
ORDER BY match_count DESC, wins DESC;";

if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for HERO PLUS HERO VARIANTS.\n";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$res = [];

$query_res = $conn->store_result();

$em = [
  // "heroid1" => $row[0],
  // "heroid2" => $row[1],
  "matches" => -1,
  "won" => 0,
  "winrate" => 0,
  "exp" => 0,
  "wr_diff" => 0,
  "lane_rate" => 0,
  "lane_wr" => 0,
];

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if (!isset($result['hero_variants'][ $row[0].'-'.$row[1] ]) || !isset($result['hero_variants'][ $row[2].'-'.$row[3] ])) continue;

  $expected_pair  = $result['random']['matches_total'] ? ( $result['hero_variants'][ $row[0].'-'.$row[1] ]['m']
  * $result['hero_variants'][ $row[2].'-'.$row[3] ]['m']
  / $result['random']['matches_total'] )
  / 2
  : 0;

  $wr_diff = $row[5]/$row[4] - (
    $result['hero_variants'][ $row[0].'-'.$row[1] ]['w']/$result['hero_variants'][ $row[0].'-'.$row[1] ]['m'] + 
    $result['hero_variants'][ $row[2].'-'.$row[3] ]['w']/$result['hero_variants'][ $row[2].'-'.$row[3] ]['m']
  )/2;

  if (!isset($res[ $row[0].'-'.$row[1] ]))
    $res[ $row[0].'-'.$row[1] ] = [ '_h' => $em ];

  if (!isset($res[ $row[2].'-'.$row[3] ]))
    $res[ $row[2].'-'.$row[3] ] = [ '_h' => $em ];

  $p = [
    // "heroid1" => $row[0],
    // "heroid2" => $row[1],
    "matches" => $row[4],
    "won" => $row[5],
    "winrate" => round($row[5]/$row[4], 4),
    "exp" => round($expected_pair),
    "wr_diff" => round($wr_diff, 4),
    "lane_rate" => round($row[6]/$row[4], 4),
    "lane_wr" => $row[6] ? round($row[7]/($row[6]*2), 4) : 0,
  ];

  $res[ $row[0].'-'.$row[1] ][ $row[2].'-'.$row[3] ] = $p;
  $res[ $row[2].'-'.$row[3] ][ $row[0].'-'.$row[1] ] = [ 'matches' => -1 ];
  // $res[] = $p;
}

$query_res->free_result();

$result["hph_v"] = $res;
unset($res);