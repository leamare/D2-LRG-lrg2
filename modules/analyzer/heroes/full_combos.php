<?php
$result["hph"] = [];

$sql = "SELECT fm1.heroid, fm2.heroid,
COUNT(distinct matches.matchid) match_count,
SUM(NOT matches.radiantWin XOR fm1.isRadiant) wins,
SUM(fm1.lane = fm2.lane)/SUM(1) lane_rate
FROM
( select m1.matchid, m1.heroid, am1.lane, m1.isRadiant
  from matchlines m1 LEFT JOIN adv_matchlines am1
  ON m1.matchid = am1.matchid AND m1.heroid = am1.heroid ) fm1
JOIN
( select m2.matchid, m2.heroid, am2.lane, m2.isRadiant
  from matchlines m2 LEFT JOIN adv_matchlines am2
  ON m2.matchid = am2.matchid AND m2.heroid = am2.heroid ) fm2
ON fm1.matchid = fm2.matchid and fm1.isRadiant = fm2.isRadiant and fm1.heroid < fm2.heroid
JOIN matches ON fm1.matchid = matches.matchid 
GROUP BY fm1.heroid, fm2.heroid
ORDER BY match_count DESC, wins DESC;";

if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for HERO PLUS HERO.\n";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$res = [];

$query_res = $conn->store_result();

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  $expected_pair  = $result['random']['matches_total'] ? ( $result['pickban'][$row[0]]['matches_picked']
  * $result['pickban'][$row[1]]['matches_picked']
  / $result['random']['matches_total'] )
  / 2
  : 0;

  $wr_diff = $row[3]/$row[2] - ($result['pickban'][$row[0]]['winrate_picked'] + $result['pickban'][$row[1]]['winrate_picked'])/2;

  if (!isset($res[ $row[0] ]))
    $res[ $row[0] ] = [];

  if (!isset($res[ $row[1] ]))
    $res[ $row[1] ] = [];

  $p = [
    // "heroid1" => $row[0],
    // "heroid2" => $row[1],
    "matches" => $row[2],
    "won" => $row[3],
    "winrate" => round($row[3]/$row[2], 4),
    "exp" => round($expected_pair),
    "lane_rate" => round($row[4], 4),
    "wr_diff" => round($wr_diff, 4)
  ];

  $res[ $row[0] ][ $row[1] ] = $p;
  $res[ $row[1] ][ $row[0] ] = true;
  // $res[] = $p;
}

$query_res->free_result();

$result["hph"] = wrap_data($res, true, true, true);
unset($res);