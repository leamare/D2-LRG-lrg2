<?php

$wheres = "";
if (!empty($players_interest)) {
  $wheres = " WHERE playerid in (".implode(',', $players_interest).") ";
}

# kills
$sql  = "SELECT \"kills\", playerid, SUM(kills)/SUM(1) value, SUM(1) mtch FROM matchlines $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
# least deaths
$sql .= "SELECT \"least_deaths\", playerid, SUM(deaths)/SUM(1) value, SUM(1) mtch FROM matchlines $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value ASC LIMIT $avg_limit;";
# most deaths
$sql .= "SELECT \"most_deaths\", playerid, SUM(deaths)/SUM(1) value, SUM(1) mtch FROM matchlines $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
# assists
$sql .= "SELECT \"assists\", playerid, SUM(assists)/SUM(1) value, SUM(1) mtch FROM matchlines $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
# kda
$sql .= "SELECT \"kda\", playerid, (SUM(kills)+SUM(assists))/SUM(deaths) value, SUM(1) mtch  FROM matchlines $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";

# gpm
$sql .= "SELECT \"gpm\", playerid, SUM(gpm)/SUM(1) value, SUM(1) mtch FROM matchlines $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
# xpm
$sql .= "SELECT \"xpm\", playerid, SUM(xpm)/SUM(1) value, SUM(1) mtch FROM matchlines $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
# lane efficiency
$sql .= "SELECT \"lane_efficiency\", playerid, SUM(efficiency_at10)/SUM(1) value, SUM(1) mtch
  FROM adv_matchlines $wheres 
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
# last hits per min
$sql .= "SELECT \"lasthits_per_min\", matchlines.playerid heroid, SUM(matchlines.lastHits/(matches.duration/60))/SUM(1)
  value, SUM(1) mtch  FROM matchlines JOIN matches ON matchlines.matchid = matches.matchid $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
# last hits
$sql .= "SELECT \"lasthits\", matchlines.playerid playerid, SUM(matchlines.lastHits)/SUM(1)
  value, SUM(1) mtch  FROM matchlines JOIN matches ON matchlines.matchid = matches.matchid $wheres 
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
# denies
$sql .= "SELECT \"denies\", playerid, SUM(denies)/SUM(1) value, SUM(1) mtch FROM matchlines $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";

# hero damage / minute
$sql .= "SELECT \"hero_damage_per_min\", matchlines.playerid playerid, SUM(matchlines.heroDamage/(matches.duration/60))/SUM(1)
  value, SUM(1) mtch FROM matchlines JOIN matches ON matchlines.matchid = matches.matchid $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
# tower damage / minute
$sql .= "SELECT \"tower_damage_per_min\", matchlines.playerid playerid, SUM(matchlines.towerDamage/(matches.duration/60))/SUM(1)
  value, SUM(1) mtch FROM matchlines JOIN matches ON matchlines.matchid = matches.matchid $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
# taken damage / minute
$sql .= "SELECT \"taken_damage_per_min\", adv_matchlines.playerid playerid, SUM(adv_matchlines.damage_taken/(matches.duration/60))/SUM(1)
  value, SUM(1) mtch FROM adv_matchlines JOIN matches ON adv_matchlines.matchid = matches.matchid $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
# heal / minute
$sql .= "SELECT \"heal_per_min\", matchlines.playerid playerid, SUM(matchlines.heal/(matches.duration/60))/SUM(1)
  value, SUM(1) mtch FROM matchlines JOIN matches ON matchlines.matchid = matches.matchid $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";

# stuns
$sql .= "SELECT \"stuns\", playerid, SUM(CASE WHEN am.stuns >= 0 THEN am.stuns ELSE 0 END)/SUM(CASE WHEN am.stuns >= 0 THEN 1 ELSE 0 END) value, 
  SUM(CASE WHEN am.stuns >= 0 THEN 1 ELSE 0 END) mtch FROM adv_matchlines am $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
# courier kills
$sql .= "SELECT \"courier_kills\", playerid, SUM(couriers_killed)/SUM(1) value, SUM(1) mtch FROM adv_matchlines $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
# roshan kills by hero's team
if ($lg_settings['main']['teams']) {
  $wheres_tm = "";
  if (!empty($result["teams_interest"])) {
    $wheres_tm = " WHERE teams_matches.teamid in (".implode(',', $result["teams_interest"]).") ";
  }

  $sql .= "SELECT \"roshan_kills_by_team\", teams_matches.teamid, SUM(rs.rshs)/SUM(1) value, SUM(1) mtch FROM (
    SELECT ml.matchid, SUM(am.roshans_killed) rshs, ml.isradiant is_radiant 
    FROM adv_matchlines am JOIN matchlines ml ON am.playerid = ml.playerid AND am.matchid = ml.matchid
    GROUP BY ml.matchid, ml.isradiant
  ) rs
  JOIN teams_matches ON rs.matchid = teams_matches.matchid and rs.is_radiant = teams_matches.is_radiant $wheres_tm
  GROUP BY teams_matches.teamid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
} else {
  $sql .= "SELECT \"roshan_kills_with_team\", playerid, SUM(rs.rshs)/SUM(1) value, SUM(1) mtch FROM matchlines JOIN (
    SELECT ml.matchid, SUM(am.roshans_killed) rshs, ml.isradiant is_radiant 
    FROM adv_matchlines am JOIN matchlines ml ON am.playerid = ml.playerid AND am.matchid = ml.matchid
    GROUP BY ml.matchid, ml.isradiant
  ) rs ON matchlines.matchid = rs.matchid $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
}
# wards destroyed
$sql .= "SELECT \"wards_destroyed\", playerid, SUM(wards_destroyed)/SUM(1) value, SUM(1) mtch FROM adv_matchlines $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";

# longest killstreak
$sql .= "SELECT \"longest_killstreak_in_match\", playerid, SUM(streak)/SUM(1) value, SUM(1) mtch FROM adv_matchlines $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
# stacks
$sql .= "SELECT \"stacks\", playerid, SUM(stacks)/SUM(1) value, SUM(1) mtch FROM adv_matchlines $wheres
  GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
# wards
$sql .= "SELECT \"wards_placed\", playerid, SUM(wards)/SUM(1) value, SUM(1) mtch FROM adv_matchlines $wheres
  GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
# pings per game
$sql .= "SELECT \"pings\", adv_matchlines.playerid playerid, 
  SUM(CASE WHEN adv_matchlines.pings > 0 THEN adv_matchlines.pings ELSE 0 END)/SUM(CASE WHEN adv_matchlines.pings > 0 THEN 1 ELSE 0 END)
  value, SUM(1) mtch FROM adv_matchlines JOIN matches ON adv_matchlines.matchid = matches.matchid $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
# pings per minute
$sql .= "SELECT \"pings_per_minute\", adv_matchlines.playerid playerid, 
  SUM(CASE WHEN adv_matchlines.pings > 0 THEN adv_matchlines.pings/(matches.duration/60) ELSE 0 END)/SUM(CASE WHEN adv_matchlines.pings > 0 THEN 1 ELSE 0 END)
  value, SUM(1) mtch FROM adv_matchlines JOIN matches ON adv_matchlines.matchid = matches.matchid $wheres
  GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
# hero pool size
$sql .= "SELECT \"hero_pool\", playerid, COUNT(DISTINCT heroid) value, SUM(1) mtch FROM matchlines $wheres
  GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
# plyer diversity
$sql .= "SELECT \"diversity\", playerid, (COUNT(DISTINCT heroid)/mhpt.mhp) * (COUNT(DISTINCT heroid)/COUNT(DISTINCT matchid)) value, SUM(1) mtch, COUNT(DISTINCT matchid) matches
          FROM matchlines join ( select max(heropool) mhp from
              ( select COUNT(DISTINCT heroid) heropool, playerid from matchlines group by playerid ) _hp
            ) mhpt $wheres
          GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";

# fantasy MVP points
if ($lg_settings['main']['fantasy'] ?? false) {
  $sql .= "SELECT \"most_mvp_points\", playerid, SUM(total_points)/SUM(1) value, SUM(1) mtch FROM fantasy_mvp_points $wheres
    GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"least_mvp_points\", playerid, SUM(total_points)/SUM(1) value, SUM(1) mtch FROM fantasy_mvp_points $wheres
    GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value ASC LIMIT $avg_limit;";
  $sql .= "SELECT \"most_mvp_awards\", playerid, SUM(mvp + mvp_losing + core*0.6 + support*0.4)/SUM(1) value, SUM(1) mtch FROM fantasy_mvp_awards $wheres
    GROUP BY playerid HAVING $pl_limiter < mtch ORDER BY value DESC LIMIT $avg_limit;";
}

$result["averages_players"] = array();

if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for AVERAGES PLAYERS.\n";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

do {
 $query_res = $conn->store_result();

 $row = $query_res->fetch_row();

 if (!empty($row)) {
  $result["averages_players"][$row[0]] = array();
  
  for ($i=0; $i<$avg_limit && $row != null; $i++, $row = $query_res->fetch_row()) {
      $result["averages_players"][$row[0]][$i] = array (
      "playerid" => $row[1],
      "value"  => $row[2]
    );
  }
 }

 $query_res->free_result();

} while($conn->next_result());
