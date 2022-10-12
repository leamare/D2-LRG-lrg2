<?php 

function rg_query_player_graph(&$conn, &$psummary, $matches_total, $limiter = 0, $cluster = null, $players = null) {
  global $players_interest;
  if (empty($players) && !empty($players_interest)) {
    $players = $players_interest;
  }

  $res = [];

  $wheres = [];
  if (!empty($cluster)) $wheres[] = "matches.cluster IN (".implode(",", $cluster).")";
  if (!empty($players)) $wheres[] = "( m1.playerid IN (".implode(",", $players).") and m2.playerid IN (".implode(",", $players).") )";

  $sql = "SELECT m1.playerid, m2.playerid, SUM(NOT matches.radiantWin XOR m1.isRadiant) wins, SUM(1) match_count
          FROM matchlines m1 JOIN matchlines m2
            ON m1.matchid = m2.matchid and m1.isRadiant = m2.isRadiant and m1.playerid < m2.playerid
            JOIN matches ON m1.matchid = matches.matchid ".
          (!empty($wheres) ? "WHERE ".implode(" AND ", $wheres) : "").
        " GROUP BY m1.playerid, m2.playerid HAVING match_count > $limiter;";
  # only wis makes more sense for players combo graph

  if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for PLAYER PAIRS.\n";
  else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    $rate1 = ($psummary[$row[0]]['matches_s'] ?? 0) / $matches_total;
    $rate2 = ($psummary[$row[1]]['matches_s'] ?? 0) / $matches_total;
    $expected_pair  = round($rate1 * $rate2 * ($matches_total/2));

    if($row[3]-$expected_pair < $row[3]*0.1) //min deviation 10% of total matches
      continue;

    $wr_diff = ( ($psummary[$row[0]]['winrate_s'] ?? 0.5) + ($psummary[$row[1]]['winrate_s'] ?? 0.5) )/2 - $row[2]/$row[3];
    $dev_pct = $expected_pair ? 1 - $expected_pair/$row[3] : 1;

    $res[] = [
      "playerid1" => $row[0],
      "playerid2" => $row[1],
      "matches" => $row[3],
      "wins" => $row[2],
      "wr_diff" => $wr_diff,
      "expectation" => $expected_pair,
      "dev_pct" => $dev_pct,
    ];
  }

  $query_res->free_result();

  return $res;
}