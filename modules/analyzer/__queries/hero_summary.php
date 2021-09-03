<?php 

function rg_query_hero_summary(&$conn, $cluster = null) {
  $res = [];

  $sql = "SELECT
            am.heroid hid,
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
            SUM(CASE WHEN am.stuns >= 0 THEN am.stuns ELSE 0 END)/SUM(CASE WHEN am.stuns >= 0 THEN 1 ELSE 0 END) stuns,
            SUM(am.lh_at10)/SUM(1) lh_10,
            SUM(ml.lasthits)/(SUM(m.duration)/60) lh,
            SUM(m.duration)/(SUM(1)*60) avg_duration
          FROM adv_matchlines am JOIN
            matchlines ml
                ON am.matchid = ml.matchid AND am.heroid = ml.heroid
              JOIN matches m
                ON m.matchid = am.matchid ".
          ($cluster !== null ? "WHERE m.cluster IN (".implode(",", $cluster).")" : "").
        " GROUP BY hid
          ORDER BY matches DESC, winrate DESC;";

  if ($conn->multi_query($sql) === TRUE);// echo "[S] Requested data for HERO SUMMARY.\n";
  else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    foreach($row as $k => $v) {
      if (!$k) continue;
      $row[$k] = round($row[$k], 4);
    }

    $res[$row[0]] = [
      "matches_s"=> $row[1],
      "winrate_s"=> $row[2],
      "kills" => $row[3],
      "deaths" => $row[4],
      "assists"  => $row[5],
      "gpm"    => $row[6],
      "xpm"    => $row[7],
      "heal_per_min_s" => $row[8],
      "hero_damage_per_min_s" => $row[9],
      "tower_damage_per_min_s"=> $row[10],
      "taken_damage_per_min_s" => $row[11],
      "stuns" => $row[12],
      "lh_at10" => $row[13],
      "lasthits_per_min_s" => $row[14],
      "duration" => $row[15]
    ];
  }

  $query_res->free_result();

  $res = wrap_data($res, true, true);

  return $res;
}