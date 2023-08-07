<?php
# records
$result["regions_data"][$region]["records"] = [];
$result["regions_data"][$region]["records_ext"] = [];

$limit = $lg_settings['ana']['records_extended'] ?? 6;

# gpm
$sql  = "SELECT \"gpm\" cap, matches.matchid, ml.gpm, ml.playerid, ml.heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.gpm DESC LIMIT $limit;";
# xpm
$sql .= "SELECT \"xpm\" cap, matches.matchid, ml.xpm, ml.playerid, ml.heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.xpm DESC LIMIT $limit;";
# kills
$sql .= "SELECT \"kills\" cap, matches.matchid, ml.kills, ml.playerid, ml.heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.kills DESC LIMIT $limit;";
# deaths
$sql .= "SELECT \"deaths\" cap, matches.matchid, ml.deaths, ml.playerid, ml.heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.deaths DESC LIMIT $limit;";
# assists
$sql .= "SELECT \"assists\" cap, matches.matchid, ml.assists, ml.playerid, ml.heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.assists DESC LIMIT $limit;";
# kda0
$sql .= "SELECT \"kda0\" cap, matches.matchid, ml.kills+ml.assists val, ml.playerid, ml.heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).") AND ml.deaths = 0
        ORDER BY val DESC LIMIT $limit;";
# kda1
$sql .= "SELECT \"kda1\" cap, matches.matchid, (ml.kills+ml.assists)/ml.deaths val, ml.playerid, ml.heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).") AND ml.deaths > 0
        ORDER BY val DESC LIMIT $limit;";
# networth
$sql .= "SELECT \"networth\" cap, matches.matchid, ml.networth, ml.playerid, ml.heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.networth DESC LIMIT $limit;";
# lasthits
$sql .= "SELECT \"lasthits\" cap, matches.matchid, ml.lasthits, ml.playerid, ml.heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.lasthits DESC LIMIT $limit;";
# lasthits
$sql .= "SELECT \"lasthits_per_min\" cap, matches.matchid, ml.lasthits*60/matches.duration as val, ml.playerid, ml.heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY val DESC LIMIT $limit;";
# denies
$sql .= "SELECT \"denies\" cap, matches.matchid, ml.denies as val, ml.playerid, ml.heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY val DESC LIMIT $limit;";
# hero damage
$sql .= "SELECT \"hero_damage\" cap, matches.matchid, ml.heroDamage, ml.playerid, ml.heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.heroDamage DESC LIMIT $limit;";
# hero damage per minute
$sql .= "SELECT \"hero_damage_per_min\" cap, matches.matchid, ml.heroDamage*60/matches.duration as val, ml.playerid, ml.heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY val DESC LIMIT $limit;";
# tower damage
$sql .= "SELECT \"tower_damage\" cap, matches.matchid, ml.towerDamage, ml.playerid, ml.heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.towerDamage DESC LIMIT $limit;";
# heal
$sql .= "SELECT \"heal\" cap, matches.matchid, ml.heal, ml.playerid, ml.heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.heal DESC LIMIT $limit;";

# damage taken
$sql .= "SELECT \"damage_taken\" cap, matches.matchid, ml.damage_taken, ml.playerid, ml.heroid FROM adv_matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.damage_taken DESC LIMIT $limit;";
# lane efficiency
// $sql .= "SELECT \"lane_efficiency\" cap, matches.matchid, ml.efficiency_at10, ml.playerid, ml.heroid FROM adv_matchlines ml
//         JOIN matches on ml.matchid = matches.matchid
//         WHERE matches.cluster IN (".implode(",", $clusters).")
//         ORDER BY ml.efficiency_at10 DESC LIMIT $limit;";
# stacks made
$sql .= "SELECT \"neutral_camps_stacked\" cap, matches.matchid, ml.stacks, ml.playerid, ml.heroid FROM adv_matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.stacks DESC LIMIT $limit;";
# wards
$sql .= "SELECT \"wards_placed\" cap, matches.matchid, ml.wards, ml.playerid, ml.heroid FROM adv_matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.wards DESC LIMIT $limit;";
# sentries
$sql .= "SELECT \"sentries_placed\" cap, matches.matchid, ml.sentries, ml.playerid, ml.heroid FROM adv_matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.sentries DESC LIMIT $limit;";
# wards destroyed
$sql .= "SELECT \"wards_destroyed\" cap, matches.matchid, ml.wards_destroyed, ml.playerid, ml.heroid FROM adv_matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.wards_destroyed DESC LIMIT $limit;";
# pings by player
$sql .= "SELECT \"pings\" cap, matches.matchid, ml.pings, ml.playerid, ml.heroid FROM adv_matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.pings DESC LIMIT $limit;";
# stuns
$sql .= "SELECT \"stuns\" cap, matches.matchid, ml.stuns, ml.playerid, ml.heroid FROM adv_matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.stuns DESC LIMIT $limit;";
# courier kills by player
$sql .= "SELECT \"couriers_killed_by_player\" cap, matches.matchid, ml.couriers_killed, ml.playerid, ml.heroid FROM adv_matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        ORDER BY ml.couriers_killed DESC LIMIT $limit;";

# couriers killed in game
$sql .= "SELECT \"couriers_killed_in_game\" cap, matches.matchid, SUM(ml.couriers_killed) cours, 0 playerid, 0 heroid FROM adv_matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        GROUP BY ml.matchid
        ORDER BY cours DESC LIMIT $limit;";
# roshans killed in game
$sql .= "SELECT \"roshans_killed_in_game\" cap, matches.matchid, SUM(ml.roshans_killed) roshs, 0 playerid, 0 heroid FROM adv_matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        GROUP BY ml.matchid
        ORDER BY roshs DESC LIMIT $limit;";
# buybacks in single game total
$sql .= "SELECT \"buybacks_in_game\" cap, matches.matchid, SUM(ml.buybacks) val, 0 playerid, 0 heroid FROM adv_matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        GROUP BY ml.matchid
        ORDER BY val DESC LIMIT $limit;";
# total time dead in a match
$sql .= "SELECT \"time_dead_total_game\" cap, matches.matchid, SUM(ml.time_dead) val, 0 playerid, 0 heroid FROM adv_matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        GROUP BY ml.matchid
        ORDER BY val DESC LIMIT $limit;";

# stomp
$sql .= "SELECT \"stomp\" cap, matchid, stomp, 0 playerid, 0 heroid FROM matches
          WHERE matches.cluster IN (".implode(",", $clusters).") ORDER BY stomp DESC LIMIT $limit;";
# comeback
$sql .= "SELECT \"comeback\" cap, matchid, comeback, 0 playerid, 0 heroid FROM matches
          WHERE matches.cluster IN (".implode(",", $clusters).") ORDER BY comeback DESC LIMIT $limit;";
# closest match
$sql .= "SELECT \"closest_match\" cap, matchid, 
      (CASE WHEN ABS(comeback) > ABS(stomp) THEN ABS(comeback) ELSE ABS(stomp) END) val, 
      0 playerid, 0 heroid FROM matches 
      WHERE matches.cluster IN (".implode(",", $clusters).") AND stomp <> 0 AND comeback <> 0 
      ORDER BY val ASC, matchid DESC LIMIT $limit;";
# biggest nw difference
$sql .= "SELECT \"biggest_nw_difference\" cap, ml.matchid matchid, ABS(SUM(IF(ml.isRadiant, 1, -1)*ml.networth)) val, 0 playerid, 0 heroid
        from matchlines ml
        join matches m on ml.matchid = m.matchid
        WHERE m.cluster IN (".implode(",", $clusters).")
        group by ml.matchid
        order by val desc limit $limit; ";
# length
$sql .= "SELECT \"longest_match\" cap, matchid, duration/60, 0 playerid, 0 heroid FROM matches
          WHERE matches.cluster IN (".implode(",", $clusters).") ORDER BY duration DESC LIMIT $limit;";
$sql .= "SELECT \"shortest_match\" cap, matchid, duration/60, 0 playerid, 0 heroid FROM matches
          WHERE matches.cluster IN (".implode(",", $clusters).") ORDER BY duration ASC LIMIT $limit;";
# kills total
$sql .= "SELECT \"kills_combined\" cap, m.matchid, SUM(ml.kills) val, 0 playerid, 0 heroid
          FROM matches m JOIN matchlines ml ON m.matchid = ml.matchid
          WHERE m.cluster IN (".implode(",", $clusters).")
          GROUP BY m.matchid ORDER BY val DESC LIMIT $limit;";
# kills per minute
$sql .= "SELECT \"bloodbath\" cap, m.matchid, SUM(ml.kills)/(m.duration / 60) val, 0 playerid, 0 heroid
          FROM matches m JOIN matchlines ml ON m.matchid = ml.matchid
          WHERE m.cluster IN (".implode(",", $clusters).")
          GROUP BY m.matchid ORDER BY val DESC LIMIT $limit;";

# rampage with lowest nw difference
$sql .= "SELECT \"lowest_rampage\" cap, m.matchid, (CASE WHEN ABS(comeback) > ABS(stomp) THEN ABS(comeback) ELSE ABS(stomp) END) val, am.playerid playerid, am.heroid heroid 
          FROM matches m JOIN adv_matchlines am ON m.matchid = am.matchid
          WHERE m.cluster IN (".implode(",", $clusters).") AND am.multi_kill > 4 
          GROUP BY m.matchid ORDER BY val ASC, m.matchid DESC LIMIT $limit;";
# rampage with highest nw difference
$sql .= "SELECT \"highest_rampage\" cap, m.matchid, (CASE WHEN ABS(comeback) > ABS(stomp) THEN ABS(comeback) ELSE ABS(stomp) END) val, am.playerid playerid, am.heroid heroid 
          FROM matches m JOIN adv_matchlines am ON m.matchid = am.matchid
          WHERE m.cluster IN (".implode(",", $clusters).") AND am.multi_kill > 4 
          GROUP BY m.matchid ORDER BY val DESC, m.matchid DESC LIMIT $limit;";

# most_matches_player
$sql .= "SELECT \"most_matches_player\" cap, 0 matchid, COUNT(distinct ml.matchid) val, ml.playerid, 0 heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).") AND playerid > 0
        GROUP BY ml.playerid
        ORDER BY val DESC LIMIT $limit;";
# widest hero pool
$sql .= "SELECT \"widest_hero_pool\" cap, 0 matchid, COUNT(distinct ml.heroid) val, ml.playerid, 0 heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).") AND playerid > 0
        GROUP BY ml.playerid
        ORDER BY val DESC LIMIT $limit;";
# smallest hero pool
$sql .= "SELECT \"smallest_hero_pool\" cap, 0 matchid, COUNT(distinct ml.heroid) val, ml.playerid, 0 heroid FROM matchlines ml
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).") AND playerid > 0
        GROUP BY ml.playerid
        ORDER BY val ASC LIMIT $limit;";

if ($lg_settings['main']['teams']) {
   # most_matches_team
   $sql .= "SELECT \"most_matches_team\" cap, 0 matchid, COUNT(distinct ml.matchid) val, teams_matches.teamid, 0 heroid
        FROM matchlines ml JOIN teams_matches ON ml.matchid = teams_matches.matchid AND teams_matches.is_radiant = ml.isRadiant
        JOIN matches on ml.matchid = matches.matchid
        WHERE matches.cluster IN (".implode(",", $clusters).")
        GROUP BY teams_matches.teamid ORDER BY val DESC LIMIT $limit;";
   # widest hero pool team
   $sql .= "SELECT \"widest_hero_pool_team\" cap, 0 matchid, COUNT(distinct ml.heroid) val, teams_matches.teamid, 0 heroid
            FROM matchlines ml JOIN teams_matches ON ml.matchid = teams_matches.matchid AND teams_matches.is_radiant = ml.isRadiant
            JOIN matches on ml.matchid = matches.matchid
            WHERE matches.cluster IN (".implode(",", $clusters).")
            GROUP BY teams_matches.teamid ORDER BY val DESC LIMIT $limit;";
   # smallest hero pool team
   $sql .= "SELECT \"smallest_hero_pool_team\" cap, 0 matchid, COUNT(distinct ml.heroid) val, teams_matches.teamid, 0 heroid
            FROM matchlines ml JOIN teams_matches ON ml.matchid = teams_matches.matchid AND teams_matches.is_radiant = ml.isRadiant
            JOIN matches on ml.matchid = matches.matchid
            WHERE matches.cluster IN (".implode(",", $clusters).")
            GROUP BY teams_matches.teamid ORDER BY val ASC LIMIT $limit;";
}


#   playerid and heroid = 0 for matchrecords

if ($conn->multi_query($sql) === TRUE);# echo "[S] Requested data for RECORDS.\n";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

do {
  $query_res = $conn->store_result();

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    if (empty($row) || empty($row[2])) continue;

    if (!isset($result["regions_data"][$region]["records"][$row[0]])) {
      $result["regions_data"][$region]["records"][$row[0]] = [
        'matchid'  => $row[1],
        'value'    => $row[2],
        'playerid' => $row[3],
        'heroid'   => $row[4],
      ];
    } else {
      if (!isset($result["regions_data"][$region]["records_ext"][$row[0]])) {
        $result["regions_data"][$region]["records_ext"][$row[0]] = [];
      }

      $result["regions_data"][$region]["records_ext"][$row[0]][] = [
        'matchid'  => $row[1],
        'value'    => $row[2],
        'playerid' => $row[3],
        'heroid'   => $row[4],
      ];
    }
  }

  $query_res->free_result();
} while($conn->next_result());

$result["regions_data"][$region]["records_ext"] = wrap_data($result["regions_data"][$region]["records_ext"], true, true, true);
