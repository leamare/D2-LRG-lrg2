<?php 

function rg_query_players_mvp(&$conn, $cluster = null, $players = null) {
  global $players_interest;
  if (empty($players) && !empty($players_interest)) {
    $players = $players_interest;
  }

  $res = [];

  $wheres = [];
  if (!empty($cluster)) $wheres[] = "m.cluster IN (".implode(",", $cluster).")";
  if (!empty($players)) $wheres[] = "fmp.playerid IN (".implode(",", $players).")";

  $sql = "SELECT
            fmp.playerid pid,
            COUNT(DISTINCT fmp.matchid) matches_s,
            -- awards
            SUM(fma.mvp) + SUM(fma.mvp_losing) + 0.6*SUM(fma.core) + 0.4*SUM(fma.support) total_awards,
            SUM(fma.mvp) mvp,
            SUM(fma.mvp_losing) mvp_losing,
            SUM(fma.core) core,
            SUM(fma.support) support,
            SUM(fma.lvp) lvp,
            -- points
            AVG(fmp.total_points) total_points,
            AVG(fmp.kills+fmp.deaths+fmp.assists) kda,
            AVG(fmp.creeps+fmp.gpm+fmp.xpm+fmp.stacks) farm,
            AVG(fmp.stuns +
              fmp.teamfight_part +
              fmp.damage +
              fmp.healing +
              fmp.damage_taken+
              fmp.hero_damage_taken_bonus+
              fmp.hero_damage_taken_penalty) combat,
            AVG(fmp.obs_placed +
              fmp.tower_damage +
              fmp.obs_kills +
              fmp.cour_kills + 
              fmp.buybacks) objectives
          FROM fantasy_mvp_points fmp LEFT JOIN
            fantasy_mvp_awards fma
                ON fma.matchid = fmp.matchid AND fmp.heroid = fma.heroid ".
          (!empty($cluster) ? "JOIN matches m ON fmp.matchid = m.matchid " : "").
          (!empty($wheres) ? "WHERE ".implode(" AND ", $wheres) : "").
        " GROUP BY pid
          ORDER BY total_awards DESC;";

  if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for FANTASY PLAYERS MVP.\n";
  else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    $res[$row[0]] = [
      "matches_s"=> $row[1],
      "total_awards"=> $row[2],
      "mvp"=> $row[3],
      "mvp_losing" => $row[4],
      "core"  => $row[5],
      "support" => $row[6],
      "lvp" => $row[7],
      "total_points" => round($row[8], 2),
      "kda" => round($row[9], 2),
      "farm" => round($row[10], 2),
      "combat" => round($row[11], 2),
      "objectives" => round($row[12], 2),
    ];
  }

  $query_res->free_result();

  $res = wrap_data($res, true, true);

  return $res;
}