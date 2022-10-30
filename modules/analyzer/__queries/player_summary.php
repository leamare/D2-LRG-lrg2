<?php 

function rg_query_player_summary(&$conn, $cluster = null, $players = null) {
  global $players_interest;
  if (empty($players) && !empty($players_interest)) {
    $players = $players_interest;
  }

  $res = [];

  $wheres = [];
  if (!empty($cluster)) $wheres[] = "m.cluster IN (".implode(",", $cluster).")";
  if (!empty($players)) $wheres[] = "ml.playerid IN (".implode(",", $players).")";

  $sql = "SELECT
            ml.playerid pid,
            SUM(1) matches,
            SUM(NOT m.radiantWin XOR ml.isradiant)/SUM(1) winrate,
            (SUM(ml.kills)+SUM(ml.assists))/(SUM(ml.deaths)) kda,
            COUNT(DISTINCT ml.heroid) heropool,
            ((COUNT(DISTINCT ml.heroid)/mhpt.mhp) * (COUNT(DISTINCT ml.heroid)/SUM(1))) diversity,
            SUM(ml.gpm)/SUM(1) gpm,
            SUM(ml.xpm)/SUM(1) xpm,
            SUM( ml.heal / (m.duration/60) )/count(1) avg_heal,
            SUM( ml.heroDamage / (m.duration/60) )/SUM(1) avg_hero_dmg,
            SUM( ml.towerDamage / (m.duration/60) )/SUM(1) avg_tower_dmg,
            SUM( am.damage_taken / (m.duration/60) )/count(distinct am.matchid) avg_dmg_taken,
            SUM(am.stuns)/count(distinct am.matchid) stuns,
            SUM(am.lh_at10)/count(distinct am.matchid) lh_10,
            SUM(ml.lasthits)/(SUM(m.duration)/60) lh,
            SUM(m.duration)/(SUM(1)*60) avg_duration,
            SUM(ml.kills)/count(distinct am.matchid) kills,
            SUM(ml.deaths)/count(distinct am.matchid) deaths,
            SUM(ml.assists)/count(distinct am.matchid) assists,
            SUM(am.pings)/count(distinct am.matchid) pings
          FROM matchlines ml LEFT JOIN
            adv_matchlines am
                ON am.matchid = ml.matchid AND am.heroid = ml.heroid
              JOIN matches m
                ON m.matchid = ml.matchid
              join ( select max(heropool) mhp from
                ( select COUNT(DISTINCT heroid) heropool, playerid from matchlines group by playerid ) _hp
              ) mhpt ".
          (!empty($wheres) ? "WHERE ".implode(" AND ", $wheres) : "").
        " GROUP BY pid
          ORDER BY matches DESC, winrate DESC;";

  if ($conn->multi_query($sql) === TRUE);# echo "[S] Requested data for PLAYER SUMMARY.\n";
  else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    $res[$row[0]] = [
      "matches_s"=> $row[1],
      "winrate_s"=> $row[2],
      "hero_pool" => $row[4],
      "diversity" => $row[5],
      "kda"  => $row[3],
      "kills_s"  => $row[16],
      "deaths_s"  => $row[17],
      "assists_s"  => $row[18],
      "gpm"    => $row[6],
      "xpm"    => $row[7],
      "heal_per_min_s" => $row[8],
      "hero_damage_per_min_s" => $row[9],
      "tower_damage_per_min_s"=> $row[10],
      "taken_damage_per_min_s" => $row[11],
      "stuns" => $row[12],
      "lh_at10" => $row[13],
      "lasthits_per_min_s" => $row[14],
      "pings" => $row[19],
      "duration" => $row[15],
    ];
  }

  $query_res->free_result();

  return $res;
}