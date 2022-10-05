<?php 

function rg_query_player_positions(&$conn, $team = null, $cluster = null, $players = null) {
  global $players_interest;
  if (empty($players) && !empty($players_interest)) {
    $players = $players_interest;
  }

  $res = [];

  $wheres = [];
  if (!empty($cluster)) $wheres[] = "m.cluster IN (".implode(",", $cluster).")";
  if (!empty($players)) $wheres[] = "ml.playerid IN (".implode(",", $players).")";
  if (!empty($team)) $wheres[] = "teams_matches.teamid = $team";

  for ($core = 0; $core < 2; $core++) {
    if (!isset($res[$core])) $res[$core] = [];
    for ($lane = 1; $lane > 0 && $lane < 6; $lane++) {
      if (!$core) { $lane = $lane == 1 ? 1 : 3; }
      $res[$core][$lane] = [];
  
      $sql = "SELECT
                am.playerid playerid,
                SUM(1) matches,
                SUM(NOT m.radiantWin XOR ml.isradiant)/SUM(1) winrate,
                SUM(ml.gpm)/SUM(1) gpm,
                SUM(ml.xpm)/SUM(1) xpm,
                SUM( ml.heal / (m.duration/60) )/SUM(1) avg_heal,
                SUM( ml.heroDamage / (m.duration/60) )/SUM(1) avg_hero_dmg,
                SUM( ml.towerDamage / (m.duration/60) )/SUM(1) avg_tower_dmg,
                SUM( am.damage_taken / (m.duration/60) )/SUM(1) avg_dmg_taken,
                SUM(am.stuns)/SUM(1) stuns,
                SUM(am.lh_at10)/SUM(1) lh_10,
                SUM(ml.denies)/SUM(1) denies,
                SUM(m.duration)/(SUM(1)*60) avg_duration,
                (SUM(ml.kills)+SUM(ml.assists))/(SUM(ml.deaths)) kills,
                COUNT(DISTINCT ml.heroid) heropool,
                (COUNT(DISTINCT ml.heroid)/mhpt.mhp)*(COUNT(DISTINCT ml.heroid)/SUM(1)) diversity,
                SUM(ml.lasthits)/(SUM(m.duration)/60) lh
              FROM adv_matchlines am JOIN
                matchlines ml
                    ON am.matchid = ml.matchid AND am.playerid = ml.playerid
                  JOIN matches m
                    ON m.matchid = am.matchid 
                  join ( select max(heropool) mhp from
                  ( select COUNT(DISTINCT heroid) heropool, playerid from matchlines group by playerid ) _hp
                ) mhpt 
              WHERE ".
            //  ($core == 0 ? "am.isCore = 0" : "am.isCore = 1 AND am.lane = $lane").
             ($core == 0 ? "am.isCore = 0 AND am.lane ".($lane == 1 ? '= 1' : '<> 1') :"am.isCore = 1 AND am.lane = $lane").
             (!empty($wheres) ? " AND ".implode(" AND ", $wheres) : "").
             " GROUP BY am.playerid
              ORDER BY matches DESC, winrate DESC;";
  
      if ($conn->multi_query($sql) === TRUE);# echo "[S] Requested data for PLAYER POSITIONS $core $lane.\n";
      else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  
      $query_res = $conn->store_result();
  
      for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
        $res[$core][$lane][$row[0]] = array (
          "matches_s"=> $row[1],
          "winrate_s"=> $row[2],
          "kda" => $row[13],
          "hero_pool" => $row[14],
          "diversity" => $row[15],
          "gpm"  => $row[3],
          "xpm" => $row[4],
          "heal_per_min_s" => $row[5],
          "hero_damage_per_min_s" => $row[6],
          "tower_damage_per_min_s"=> $row[7],
          "taken_damage_per_min_s" => $row[8],
          "stuns" => $row[9],
          "lh_at10" => $row[10],
          "lasthits_per_min_s" => $row[16],
          "denies_s" => $row[11],
          "duration" => $row[12]
        );
      }
  
      $query_res->free_result();
      if (!$core && $lane == 3) { break; }
    }
  }

  return $res;
}

function rg_query_player_positions_matches(&$conn, &$context) {
  $res = [];

  for ($core = 0; $core < 2; $core++) {
    if (!isset($res[$core])) $res[$core] = [];
    for ($lane = 1; $lane < 6; $lane++) {
      if (!$core) { $lane = $lane == 1 ? 1 : 3; }
      $res[$core][$lane] = [];

      foreach ($context[$core][$lane] as $id => $hero) {
        $res[$core][$lane][$id] = [];

        $sql = "SELECT matchid
            FROM adv_matchlines WHERE ".
          // ($core == 0 ? "isCore = 0" : "isCore = 1 AND lane = $lane")
          ($core == 0 ? "isCore = 0 AND lane ".($lane == 1 ? '= 1' : '<> 1') :"isCore = 1 AND lane = $lane")
          ." AND playerid = ".$id.";";

        if ($conn->multi_query($sql) === TRUE);
        else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

        $query_res = $conn->store_result();

        for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
          $res[$core][$lane][$id][] = $row[0];
        }

        $query_res->free_result();
      }
      if (!$core && $lane == 3) { break; }
    }
  }

  return $res;
}