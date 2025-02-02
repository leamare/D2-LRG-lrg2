<?php 

function rg_query_hero_summary_variants(&$conn, $role = 0, $cluster = null, $players = null) {
  global $players_interest;
  if (empty($players) && !empty($players_interest)) {
    $players = $players_interest;
  }

  $res = [ [
    "matches_s"=> 0,
    "winrate_s"=> 0,
    "kills" => 0,
    "deaths" => 0,
    "assists"  => 0,
    "gpm"    => 0,
    "xpm"    => 0,
    "heal_per_min_s" => 0,
    "hero_damage_per_min_s" => 0,
    "tower_damage_per_min_s"=> 0,
    "taken_damage_per_min_s" => 0,
    "stuns" => 0,
    "roshan_kills_with_team" => 0,
    "lh_at10" => 0,
    "lasthits_per_min_s" => 0,
    "duration" => 0,
  ] ];

  $wheres = [];
  if ($role) $wheres[] = "am.role = $role";
  if (!empty($cluster)) $wheres[] = "m.cluster IN (".implode(",", $cluster).")";
  if (!empty($players)) $wheres[] = "ml.playerid IN (".implode(",", $players).")";

  $sql = "SELECT
            ml.heroid hid,
            SUM(1) matches,
            SUM(NOT m.radiantWin XOR ml.isradiant)/SUM(1) winrate,
            SUM(ml.kills)/SUM(1) kills,
            SUM(ml.deaths)/SUM(1) deaths,
            SUM(ml.assists)/SUM(1) assists,
            SUM(ml.gpm)/SUM(1) gpm,
            SUM(ml.xpm)/SUM(1) xpm,
            SUM( ml.heal / (m.duration/60) )/SUM(1) avg_heal,
            SUM( ml.heroDamage / (m.duration/60) )/SUM(1) avg_hero_dmg,
            SUM( ml.towerDamage / (m.duration/60) )/SUM(1) avg_tower_dmg,
            SUM( am.damage_taken / (m.duration/60) )/SUM(1) avg_dmg_taken,
            SUM(CASE WHEN am.stuns >= 0 THEN am.stuns ELSE 0 END) stuns_sum,
            SUM(am.lh_at10)/SUM(1) lh_10,
            SUM(ml.lasthits)/(SUM(m.duration)/60) lh,
            SUM(m.duration)/(SUM(1)*60) avg_duration,
            SUM(CASE WHEN am.stuns >= 0 THEN 1 ELSE 0 END) stuns_cnt,
            SUM(rs.rshs)/SUM(1) roshs_cnt,
            ml.variant variant
          FROM matchlines ml LEFT JOIN
            adv_matchlines am
                ON am.matchid = ml.matchid AND am.heroid = ml.heroid
              JOIN matches m
                ON m.matchid = ml.matchid 
          JOIN (
            SELECT ml.matchid, SUM(am.roshans_killed) rshs, ml.isradiant is_radiant 
            FROM adv_matchlines am JOIN matchlines ml ON am.playerid = ml.playerid AND am.matchid = ml.matchid 
            GROUP BY ml.matchid, ml.isradiant
          ) rs ON ml.matchid = rs.matchid AND ml.isradiant = rs.is_radiant ".
          (!empty($wheres) ? "WHERE ".implode(" AND ", $wheres) : "").
        " GROUP BY hid, variant
          ORDER BY matches DESC, winrate DESC;";

  if ($conn->multi_query($sql) === TRUE);// echo "[S] Requested data for HERO SUMMARY.\n";
  else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    foreach($row as $k => $v) {
      if (!$k) continue;
      $row[$k] = round($row[$k], 4);
    }

    $res[$row[0].'-'.$row[18]] = [
      "matches_s"=> +$row[1],
      "winrate_s"=> +$row[2],
      "kills" => +$row[3],
      "deaths" => +$row[4],
      "assists"  => +$row[5],
      "gpm"    => +$row[6],
      "xpm"    => +$row[7],
      "heal_per_min_s" => +$row[8],
      "hero_damage_per_min_s" => +$row[9],
      "tower_damage_per_min_s"=> +$row[10],
      "taken_damage_per_min_s" => +$row[11],
      "stuns" => $row[12]/($row[16] == 0 ? 1 : $row[16]),
      "roshan_kills_with_team" => +$row[17],
      "lh_at10" => +$row[13],
      "lasthits_per_min_s" => +$row[14],
      "duration" => +$row[15],
    ];
  }

  $query_res->free_result();

  // $res = wrap_data($res, true, true);

  return $res;
}