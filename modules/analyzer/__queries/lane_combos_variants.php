<?php

function rg_query_lane_combo_variants(&$conn, $cluster = null, $players = null) {
  global $players_interest;
  if (empty($players) && !empty($players_interest)) {
    $players = $players_interest;
  }

  $result = [];

  $wheres = [];
  if (!empty($cluster)) $wheres[] = "matches.cluster IN (".implode(",", $cluster).")";
  if (!empty($players)) $wheres[] = "( fm1.playerid IN (".implode(",", $players).") AND fm2.playerid IN (".implode(",", $players).") )";

  $sql = "SELECT fm1.heroid, fm1.variant, fm2.heroid, fm2.variant,
    COUNT(distinct fm1.matchid) match_count,
    SUM(matches.radiantWin = fm1.isRadiant) wins,
    fm1.lane lane,
    SUM(fm1.lane_won) lane_wins
  FROM
    ( select m1.matchid, m1.heroid, m1.variant, am1.lane, m1.isRadiant, m1.playerid, am1.lane_won
      from matchlines m1 JOIN adv_matchlines am1
      ON m1.matchid = am1.matchid AND m1.heroid = am1.heroid ) fm1
  JOIN
    ( select m2.matchid, m2.heroid, m2.variant, am2.lane, m2.isRadiant, m2.playerid, am2.lane_won
      from matchlines m2 JOIN adv_matchlines am2
      ON m2.matchid = am2.matchid AND m2.heroid = am2.heroid ) fm2
  ON fm1.matchid = fm2.matchid and fm1.isRadiant = fm2.isRadiant and fm1.heroid < fm2.heroid
  JOIN matches ON fm1.matchid = matches.matchid
  WHERE fm1.lane = fm2.lane ".
    (!empty($wheres) ? " AND ".implode(" AND ", $wheres) : "").
  " GROUP BY fm1.heroid, fm1.variant, fm2.heroid, fm2.variant
  ORDER BY match_count DESC, wins DESC;";
  # limiting match count for hero pair to 3:
  # 1 match = every possible pair
  # 2 matches = may be a coincedence

  if ($conn->multi_query($sql) === TRUE);# echo "[S] Requested data for HERO LANE COMBOS.\n";
  else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    $result[] = [
      "heroid1"  => +$row[0],
      "variant1" => +$row[1],
      "heroid2"  => +$row[2],
      "variant2" => +$row[3],
      "matches"  => +$row[4],
      "wins"     => +$row[5],
      "lane"     => +$row[6],
      "lane_wins"=> +$row[7],
    ];
  }

  $query_res->free_result();

  return $result;
}
