<?php 

function rg_query_player_laning(&$conn, $cluster = null, $team = null, $players = null) {
  global $players_interest;
  if (empty($players) && !empty($players_interest)) {
    $players = $players_interest;
  }

  $tie_factor = 0.075;

  $result = [];
  //echo "[S] Requested data for HERO DRAFT\n";

  $sql = "SELECT
  ams_playerid player,
  SUM(1) matches,
  SUM((ams_efficiency_at10 - amr_efficiency_at10) >= $tie_factor) lanes_won,
  SUM(ABS(ams_efficiency_at10 - amr_efficiency_at10) < $tie_factor) lanes_tied,
  SUM((ams_efficiency_at10 - amr_efficiency_at10) <= -$tie_factor) lanes_lost,
  SUM(NOT (ams_q.isRadiant XOR radiantWin)) wins,
  SUM(NOT (ams_q.isRadiant XOR radiantWin))/SUM(1) winrate,
  SUM( IF(ams_efficiency_at10 - amr_efficiency_at10 > 0, ams_efficiency_at10 - amr_efficiency_at10, 0) )/SUM( IF(ams_efficiency_at10 - amr_efficiency_at10 > 0, 1, 0) ) avg_advantage,
  SUM( IF(ams_efficiency_at10 - amr_efficiency_at10 < 0, ams_efficiency_at10 - amr_efficiency_at10, 0) )/SUM( IF(ams_efficiency_at10 - amr_efficiency_at10 < 0, 1, 0) ) avg_disadvantage,
  SUM( (NOT (ams_q.isRadiant XOR radiantWin)) AND ((ams_efficiency_at10 - amr_efficiency_at10) <= -$tie_factor) ) won_from_behind,
  SUM( (NOT (ams_q.isRadiant XOR radiantWin)) AND (ABS(ams_efficiency_at10 - amr_efficiency_at10) < $tie_factor) ) won_from_tie,
  SUM( (NOT (ams_q.isRadiant XOR radiantWin)) AND ((ams_efficiency_at10 - amr_efficiency_at10) >= $tie_factor) ) won_from_won,
  SUM(ams_efficiency_at10 - amr_efficiency_at10)/SUM(1) avg_gold_diff
  FROM
  (
    SELECT
      ml.matchid,
      ams.playerid ams_playerid,
      se.core_lane_eff ams_efficiency_at10,
      ml.isRadiant,
      se.lane_c lane,
      ml.heroid
    FROM adv_matchlines ams
    JOIN matchlines ml ON ams.matchid = ml.matchid AND ams.playerid = ml.playerid
    
    JOIN (
      SELECT MAX(adv_matchlines.efficiency_at10) core_lane_eff, matchlines.isRadiant isRadiant, matchlines.matchid matchid, IF(adv_matchlines.lane > 3, 3, adv_matchlines.lane) lane_c
      FROM adv_matchlines JOIN matchlines ON adv_matchlines.matchid = matchlines.matchid AND adv_matchlines.playerid = matchlines.playerid
      GROUP BY matchlines.matchid, matchlines.isRadiant, lane_c
    ) se ON ml.matchid = se.matchid AND ml.isRadiant = se.isRadiant AND se.lane_c = IF(ams.lane > 3, 3, ams.lane)
  
    GROUP BY ml.matchid, ams_playerid
  ) ams_q
  JOIN 
   (
      SELECT MAX(adv_matchlines.efficiency_at10) amr_efficiency_at10, matchlines.isRadiant isRadiant, matchlines.matchid matchid, IF(adv_matchlines.lane > 3, 3, adv_matchlines.lane) lane_c
      FROM adv_matchlines JOIN matchlines ON adv_matchlines.matchid = matchlines.matchid AND adv_matchlines.playerid = matchlines.playerid
      GROUP BY matchlines.matchid, matchlines.isRadiant, lane_c
  ) amr_q
  ON ams_q.matchid = amr_q.matchid AND ams_q.isRadiant <> amr_q.isRadiant AND ams_q.lane = amr_q.lane_c
  JOIN matches m ON ams_q.matchid = m.matchid ".
    ($team === null ? "" : " JOIN teams_matches ON teams_matches.matchid = m.matchid AND ml.isRadiant = teams_matches.is_radiant ").
    ($team !== null || $cluster !== null ? " WHERE " : "").
    ($cluster === null ? "" : " AND m.cluster IN (".implode(",", $cluster).") ").
    ($team === null ? "" : " AND teams_matches.teamid = ".$team." ").
    (!empty($players) ? " AND ams_q.playerid in (".implode(',', $players).")" : "").
  " GROUP BY player";

  if ($conn->multi_query($sql) !== TRUE) throw new \Exception($conn->error);

  $result['0'] = [];

  $query_res = $conn->store_result();
  for ($row = $query_res->fetch_assoc(); $row != null; $row = $query_res->fetch_assoc()) {
    $row['avg_advantage'] = round($row['avg_advantage'], 4);
    $row['avg_disadvantage'] = round($row['avg_disadvantage'], 4);
    $row['lane_wr'] = round( ( $row['lanes_won']*2+$row['lanes_tied'] )/($row['matches']*2) , 4);
    // $row['wr_from_behind'] = round( $row['lanes_lost'] ? ($row['won_from_behind']/$row['lanes_lost']) : 0, 4);
    // $row['wr_from_tie'] = round( $row['lanes_tied'] ? ($row['won_from_tie']/$row['lanes_tied']) : 0, 4);
    // $row['wr_from_won'] = round( $row['lanes_won'] ? ($row['won_from_won']/$row['lanes_won']) : 0, 4);
    $result['0'][ $row['player'] ] = $row;
  }

  $query_res->free_result();

  // foreach ($result['0'] as $hid => $d) {
  //   echo $hid;

    // $r = [];

    $sql = "SELECT
    ams_playerid,
    amr_playerid player,
    COUNT(DISTINCT ams_q.matchid) matches,
    SUM((ams_efficiency_at10 - amr_efficiency_at10) >= $tie_factor) lanes_won,
    SUM(ABS(ams_efficiency_at10 - amr_efficiency_at10) < $tie_factor) lanes_tied,
    SUM((ams_efficiency_at10 - amr_efficiency_at10) <= -$tie_factor) lanes_lost,
    SUM(NOT (ams_q.isRadiant XOR radiantWin)) wins,
    SUM(NOT (ams_q.isRadiant XOR radiantWin))/SUM(1) winrate,
    SUM( IF(ams_efficiency_at10 - amr_efficiency_at10 > 0, ams_efficiency_at10 - amr_efficiency_at10, 0) )/SUM( IF(ams_efficiency_at10 - amr_efficiency_at10 > 0, 1, 0) ) avg_advantage,
    SUM( IF(ams_efficiency_at10 - amr_efficiency_at10 < 0, ams_efficiency_at10 - amr_efficiency_at10, 0) )/SUM( IF(ams_efficiency_at10 - amr_efficiency_at10 < 0, 1, 0) ) avg_disadvantage,
    SUM( (NOT (ams_q.isRadiant XOR radiantWin)) AND ((ams_efficiency_at10 - amr_efficiency_at10) <= -$tie_factor) ) won_from_behind,
    SUM( (NOT (ams_q.isRadiant XOR radiantWin)) AND (ABS(ams_efficiency_at10 - amr_efficiency_at10) < $tie_factor) ) won_from_tie,
    SUM( (NOT (ams_q.isRadiant XOR radiantWin)) AND ((ams_efficiency_at10 - amr_efficiency_at10) >= $tie_factor) ) won_from_won,
    SUM(ams_efficiency_at10 - amr_efficiency_at10)/SUM(1) avg_gold_diff
    FROM
    (
      SELECT
        ml.matchid,
        ams.playerid ams_playerid,
        se.core_lane_eff ams_efficiency_at10,
        ml.isRadiant,
        IF(ams.lane > 3, 3, ams.lane) lane,
        ml.heroid
      FROM adv_matchlines ams
      JOIN matchlines ml ON ams.matchid = ml.matchid AND ams.heroid = ml.heroid
      
      JOIN (
        SELECT MAX(adv_matchlines.efficiency_at10) core_lane_eff, matchlines.isRadiant isRadiant, matchlines.matchid matchid, IF(adv_matchlines.lane > 3, 3, adv_matchlines.lane) lane_c
        FROM adv_matchlines JOIN matchlines ON adv_matchlines.matchid = matchlines.matchid AND adv_matchlines.heroid = matchlines.heroid
        GROUP BY matchlines.matchid, matchlines.isRadiant, lane_c
      ) se ON ml.matchid = se.matchid AND ml.isRadiant = se.isRadiant AND se.lane_c = IF(ams.lane > 3, 3, ams.lane)
    
      GROUP BY ml.matchid, ams_playerid
    ) ams_q
    JOIN 
    (
      SELECT
        ml.matchid,
        ams.playerid amr_playerid,
        se.core_lane_eff amr_efficiency_at10,
        ml.isRadiant,
        4-IF(ams.lane > 3, 3, ams.lane) lane,
        ml.heroid
      FROM adv_matchlines ams
      JOIN matchlines ml ON ams.matchid = ml.matchid AND ams.playerid = ml.playerid
      
      JOIN (
        SELECT MAX(adv_matchlines.efficiency_at10) core_lane_eff, matchlines.isRadiant isRadiant, matchlines.matchid matchid, IF(adv_matchlines.lane > 3, 3, adv_matchlines.lane) lane_c
        FROM adv_matchlines JOIN matchlines ON adv_matchlines.matchid = matchlines.matchid AND adv_matchlines.heroid = matchlines.heroid
        GROUP BY matchlines.matchid, matchlines.isRadiant, lane_c
      ) se ON ml.matchid = se.matchid AND ml.isRadiant = se.isRadiant AND se.lane_c = IF(ams.lane > 3, 3, ams.lane)
    
      GROUP BY ml.matchid, amr_playerid
    ) amr_q
    ON ams_q.matchid = amr_q.matchid AND ams_q.isRadiant <> amr_q.isRadiant AND ams_q.lane = amr_q.lane
    JOIN matches m ON ams_q.matchid = m.matchid ".
    ($team === null ? "" : " JOIN teams_matches ON teams_matches.matchid = m.matchid AND ml.isRadiant = teams_matches.is_radiant ").
    ($team !== null || $cluster !== null ? " WHERE " : "").
    ($cluster === null ? "" : " AND m.cluster IN (".implode(",", $cluster).") ").
    ($team === null ? "" : " AND teams_matches.teamid = ".$team." ").
    (!empty($players) ? " AND ( ams_q.playerid in (".implode(',', $players).") and amr_q.playerid in (".implode(',', $players).") )" : "").
  " GROUP BY ams_playerid, player";

    if ($conn->multi_query($sql) !== TRUE) throw new \Exception($conn->error);

    $query_res = $conn->store_result();
    for ($row = $query_res->fetch_assoc(); $row != null; $row = $query_res->fetch_assoc()) {
      if (!isset($result[ $row['ams_playerid'] ]))
        $result[ $row['ams_playerid'] ] = [];

      $ams = $row['ams_playerid'];
      unset($row['ams_playerid']);
      $row['avg_advantage'] = round($row['avg_advantage'], 4);
      $row['avg_disadvantage'] = round($row['avg_disadvantage'], 4);
      $row['lane_wr'] = round( ( $row['lanes_won']*2+$row['lanes_tied'] )/($row['matches']*2) , 4);
      // $row['wr_from_behind'] = round( $row['lanes_lost'] ? ($row['won_from_behind']/$row['lanes_lost']) : 0, 4);
      // $row['wr_from_tie'] = round( $row['lanes_tied'] ? ($row['won_from_tie']/$row['lanes_tied']) : 0, 4);
      // $row['wr_from_won'] = round( $row['lanes_won'] ? ($row['won_from_won']/$row['lanes_won']) : 0, 4);
      $result[ $ams ][ $row['player'] ] = $row;
    }

    $query_res->free_result();

    // $result[ $hid ] = $r;

    // echo " ";
  // }

  return $result;
}