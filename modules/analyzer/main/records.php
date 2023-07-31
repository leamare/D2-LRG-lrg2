<?php
# records
$result['records'] = [];
$result['records_ext'] = [];

$limit = $lg_settings['ana']['records_extended'] ?? 6;

# gpm
$sql  = "SELECT \"gpm\" cap, matchid, gpm, playerid, heroid FROM matchlines ORDER BY gpm DESC LIMIT $limit;";
# xpm
$sql .= "SELECT \"xpm\" cap, matchid, xpm, playerid, heroid FROM matchlines ORDER BY xpm DESC LIMIT $limit;";
# kills
$sql .= "SELECT \"kills\" cap, matchid, kills, playerid, heroid FROM matchlines ORDER BY kills DESC LIMIT $limit;";
# deaths
$sql .= "SELECT \"deaths\" cap, matchid, deaths, playerid, heroid FROM matchlines ORDER BY deaths DESC LIMIT $limit;";
# assists
$sql .= "SELECT \"assists\" cap, matchid, assists, playerid, heroid FROM matchlines ORDER BY assists DESC LIMIT $limit;";
# kda0
$sql .= "SELECT \"kda0\" cap, matchid, kills+assists val, playerid, heroid FROM matchlines WHERE deaths = 0 ORDER BY val DESC LIMIT $limit;";
# kda1
$sql .= "SELECT \"kda1\" cap, matchid, (kills+assists)/deaths val, playerid, heroid FROM matchlines WHERE deaths > 0 ORDER BY val DESC LIMIT $limit;";
# gold earned
$sql .= "SELECT \"gold_earned\" cap, matchid, networth, playerid, heroid FROM matchlines ORDER BY networth DESC LIMIT $limit;";
# denies
$sql .= "SELECT \"denies\" cap, matchid, lastHits, playerid, heroid FROM matchlines ORDER BY denies DESC LIMIT $limit;";
# lasthits
$sql .= "SELECT \"lasthits\" cap, matchid, lastHits, playerid, heroid FROM matchlines ORDER BY lastHits DESC LIMIT $limit;";
# lasthits per minute
$sql .= "SELECT \"lasthits_per_min\" cap, m.matchid, ml.lastHits*60/m.duration as val, ml.playerid, ml.heroid FROM matchlines ml
  JOIN matches m ON m.matchid = ml.matchid
  ORDER BY val DESC LIMIT $limit;";
# hero damage
$sql .= "SELECT \"hero_damage\" cap, matchid, heroDamage, playerid, heroid FROM matchlines ORDER BY heroDamage DESC LIMIT $limit;";
# hero damage per minute
$sql .= "SELECT \"hero_damage_per_min\" cap, m.matchid, ml.heroDamage*60/m.duration as val, ml.playerid, ml.heroid FROM matchlines ml
  JOIN matches m ON m.matchid = ml.matchid
  ORDER BY val DESC LIMIT $limit;";
# tower damage
$sql .= "SELECT \"tower_damage\" cap, matchid, towerDamage, playerid, heroid FROM matchlines ORDER BY towerDamage DESC LIMIT $limit;";
# heal
$sql .= "SELECT \"heal\" cap, matchid, heal, playerid, heroid FROM matchlines ORDER BY heal DESC LIMIT $limit;";

# damage taken
$sql .= "SELECT \"damage_taken\" cap, matchid, damage_taken, playerid, heroid FROM adv_matchlines ORDER BY damage_taken DESC LIMIT $limit;";
# lane efficiency
// $sql .= "SELECT \"lane_efficiency\" cap, matchid, efficiency_at10, playerid, heroid FROM adv_matchlines ORDER BY efficiency_at10 DESC LIMIT 1;";
# stacks made
$sql .= "SELECT \"neutral_camps_stacked\" cap, matchid, stacks, playerid, heroid FROM adv_matchlines ORDER BY stacks DESC LIMIT $limit;";
# wards
$sql .= "SELECT \"wards_placed\" cap, matchid, wards, playerid, heroid FROM adv_matchlines ORDER BY wards DESC LIMIT $limit;";
# sentries
$sql .= "SELECT \"sentries_placed\" cap, matchid, sentries, playerid, heroid FROM adv_matchlines ORDER BY sentries DESC LIMIT $limit;";
# wards destroyed
$sql .= "SELECT \"wards_destroyed\" cap, matchid, wards_destroyed, playerid, heroid FROM adv_matchlines ORDER BY wards_destroyed DESC LIMIT $limit;";
# pings by player
$sql .= "SELECT \"pings\" cap, matchid, pings, playerid, heroid FROM adv_matchlines ORDER BY pings DESC LIMIT $limit;";
# stuns
$sql .= "SELECT \"stuns\" cap, matchid, stuns, playerid, heroid FROM adv_matchlines ORDER BY stuns DESC LIMIT $limit;";
# courier kills by player
$sql .= "SELECT \"couriers_killed_by_player\" cap, matchid, couriers_killed, playerid, heroid FROM adv_matchlines ORDER BY couriers_killed DESC LIMIT $limit;";

if ($lg_settings['main']['teams']) {
  # most_kills_by_team
  $sql .= "SELECT \"most_kills_by_team\" cap, matchlines.matchid, SUM(kills) sum_kills, teams_matches.teamid as playerid, 0 heroid
    FROM matchlines JOIN teams_matches
    ON matchlines.matchid = teams_matches.matchid
    AND matchlines.isRadiant = teams_matches.is_radiant
    GROUP BY matchlines.matchid, matchlines.isRadiant
  ORDER BY sum_kills DESC LIMIT $limit;";
  # most_assists_by_team
  $sql .= "SELECT \"most_assists_by_team\" cap, matchlines.matchid, SUM(assists) sum_assists, teams_matches.teamid as playerid, 0 heroid
    FROM matchlines JOIN teams_matches
    ON matchlines.matchid = teams_matches.matchid
    AND matchlines.isRadiant = teams_matches.is_radiant
    GROUP BY matchlines.matchid, matchlines.isRadiant
  ORDER BY sum_assists DESC LIMIT $limit;";
  # fewest_deaths_by_team
  $sql .= "SELECT \"fewest_deaths_by_team\" cap, matchlines.matchid, SUM(deaths) sum_deaths, teams_matches.teamid as playerid, 0 heroid
    FROM matchlines JOIN teams_matches
    ON matchlines.matchid = teams_matches.matchid
    AND matchlines.isRadiant = teams_matches.is_radiant
    GROUP BY matchlines.matchid, matchlines.isRadiant
  ORDER BY sum_deaths ASC LIMIT $limit;";
  # most_deaths_by_team
  $sql .= "SELECT \"most_deaths_by_team\" cap, matchlines.matchid, SUM(deaths) sum_deaths, teams_matches.teamid as playerid, 0 heroid
    FROM matchlines JOIN teams_matches
    ON matchlines.matchid = teams_matches.matchid
    AND matchlines.isRadiant = teams_matches.is_radiant
    GROUP BY matchlines.matchid, matchlines.isRadiant
  ORDER BY sum_deaths DESC LIMIT $limit;";
}

# couriers killed in game
$sql .= "SELECT \"couriers_killed_in_game\" cap, matchid, SUM(couriers_killed) cours, 0 playerid, 0 heroid FROM adv_matchlines GROUP BY matchid ORDER BY cours DESC LIMIT $limit;";
# roshans killed in game
$sql .= "SELECT \"roshans_killed_in_game\" cap, matchid, SUM(roshans_killed) roshs, 0 playerid, 0 heroid FROM adv_matchlines GROUP BY matchid ORDER BY roshs  DESC LIMIT $limit;";

# stomp
$sql .= "SELECT \"stomp\" cap, matchid, stomp, 0 playerid, 0 heroid FROM matches ORDER BY stomp DESC LIMIT $limit;";
# comeback
$sql .= "SELECT \"comeback\" cap, matchid, comeback, 0 playerid, 0 heroid FROM matches ORDER BY comeback DESC LIMIT $limit;";
# closest match
$sql .= "SELECT \"closest_match\" cap, matchid, 
  (CASE WHEN ABS(comeback) > ABS(stomp) THEN ABS(comeback) ELSE ABS(stomp) END) val, 
  0 playerid, 0 heroid FROM matches 
  WHERE stomp <> 0 AND comeback <> 0 
  ORDER BY val ASC, matchid DESC LIMIT $limit;";
# biggest nw difference
$sql .= "SELECT \"biggest_nw_difference\" cap, ml.matchid matchid, ABS(SUM(IF(ml.isRadiant, 1, -1)*ml.networth)) val, 0 playerid, 0 heroid
  from matchlines ml
  join matches m on ml.matchid = m.matchid
  group by ml.matchid
  order by val desc limit $limit; ";
# length
$sql .= "SELECT \"longest_match\" cap, matchid, duration/60, 0 playerid, 0 heroid FROM matches ORDER BY duration DESC LIMIT $limit;";
$sql .= "SELECT \"shortest_match\" cap, matchid, duration/60, 0 playerid, 0 heroid FROM matches ORDER BY duration ASC LIMIT $limit;";
# kills total
$sql .= "SELECT \"kills_combined\" cap, m.matchid, SUM(ml.kills) val, 0 playerid, 0 heroid
          FROM matches m JOIN matchlines ml ON m.matchid = ml.matchid GROUP BY m.matchid ORDER BY val DESC LIMIT $limit;";
# kills per minute
$sql .= "SELECT \"bloodbath\" cap, m.matchid, SUM(ml.kills)/(m.duration / 60) val, 0 playerid, 0 heroid
          FROM matches m JOIN matchlines ml ON m.matchid = ml.matchid GROUP BY m.matchid ORDER BY val DESC LIMIT $limit;";
# rampage with lowest nw difference
$sql .= "SELECT \"lowest_rampage\" cap, m.matchid, (CASE WHEN ABS(comeback) > ABS(stomp) THEN ABS(comeback) ELSE ABS(stomp) END) val, am.playerid playerid, am.heroid heroid 
          FROM matches m JOIN adv_matchlines am ON m.matchid = am.matchid WHERE am.multi_kill > 4 GROUP BY m.matchid ORDER BY val ASC, m.matchid DESC LIMIT $limit;";
# rampage with highest nw difference
$sql .= "SELECT \"highest_rampage\" cap, m.matchid, (CASE WHEN ABS(comeback) > ABS(stomp) THEN ABS(comeback) ELSE ABS(stomp) END) val, am.playerid playerid, am.heroid heroid 
          FROM matches m JOIN adv_matchlines am ON m.matchid = am.matchid WHERE am.multi_kill > 4 GROUP BY m.matchid ORDER BY val DESC, m.matchid DESC LIMIT $limit;";

# rampage with lowest nw difference
$sql .= "SELECT \"lowest_rampage_lost\" cap, m.matchid, (CASE WHEN ABS(comeback) > ABS(stomp) THEN ABS(comeback) ELSE ABS(stomp) END) val, am.playerid playerid, am.heroid heroid 
          FROM matches m JOIN adv_matchlines am ON m.matchid = am.matchid JOIN matchlines ml ON am.matchid = ml.matchid AND am.playerid = ml.playerid
          WHERE am.multi_kill > 4 AND m.radiantWin <> ml.isRadiant GROUP BY m.matchid ORDER BY val ASC, m.matchid DESC LIMIT $limit;";
# rampage with highest nw difference
$sql .= "SELECT \"highest_rampage_lost\" cap, m.matchid, (CASE WHEN ABS(comeback) > ABS(stomp) THEN ABS(comeback) ELSE ABS(stomp) END) val, am.playerid playerid, am.heroid heroid 
          FROM matches m JOIN adv_matchlines am ON m.matchid = am.matchid JOIN matchlines ml ON am.matchid = ml.matchid AND am.playerid = ml.playerid
          WHERE am.multi_kill > 4 AND m.radiantWin <> ml.isRadiant GROUP BY m.matchid ORDER BY val DESC, m.matchid DESC LIMIT $limit;";

# most_matches_player
$sql .= "SELECT \"most_matches_player\" cap, 0 matchid, COUNT(distinct matchlines.matchid) val, playerid, 0 heroid FROM matchlines WHERE playerid > 0 GROUP BY playerid ORDER BY val DESC LIMIT $limit;";
# widest hero pool
$sql .= "SELECT \"widest_hero_pool\" cap, 0 matchid, COUNT(distinct heroid) val, playerid, 0 heroid FROM matchlines GROUP BY playerid ORDER BY val DESC LIMIT $limit;";
# smallest hero pool
$sql .= "SELECT \"smallest_hero_pool\" cap, 0 matchid, COUNT(distinct heroid) val, playerid, 0 heroid FROM matchlines WHERE playerid > 0 GROUP BY playerid ORDER BY val LIMIT $limit;";

if ($lg_settings['main']['teams']) {
   # most_matches_team
   $sql .= "SELECT \"most_matches_team\" cap, 0 matchid, COUNT(distinct matchlines.matchid) val, teams_matches.teamid, 0 heroid
            FROM matchlines JOIN teams_matches ON matchlines.matchid = teams_matches.matchid AND teams_matches.is_radiant = matchlines.isRadiant
            GROUP BY teams_matches.teamid ORDER BY val DESC LIMIT $limit;";
   # widest hero pool team
   $sql .= "SELECT \"widest_hero_pool_team\" cap, 0 matchid, COUNT(distinct heroid) val, teams_matches.teamid, 0 heroid
            FROM matchlines JOIN teams_matches ON matchlines.matchid = teams_matches.matchid AND teams_matches.is_radiant = matchlines.isRadiant
            GROUP BY teams_matches.teamid ORDER BY val DESC LIMIT $limit;";
   # smallest hero pool team
   $sql .= "SELECT \"smallest_hero_pool_team\" cap, 0 matchid, COUNT(distinct heroid) val, teams_matches.teamid, 0 heroid
            FROM matchlines JOIN teams_matches ON matchlines.matchid = teams_matches.matchid AND teams_matches.is_radiant = matchlines.isRadiant
            GROUP BY teams_matches.teamid ORDER BY val ASC LIMIT $limit;";
}


#   playerid and heroid = 0 for matchrecords

if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for RECORDS.\n";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

do {
  $query_res = $conn->store_result();

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    if (!isset($result['records'][$row[0]])) {
      $result['records'][$row[0]] = [
        'matchid'  => $row[1],
        'value'    => $row[2],
        'playerid' => $row[3],
        'heroid'   => $row[4],
      ];
    } else {
      if (!isset($result['records_ext'][$row[0]])) {
        $result['records_ext'][$row[0]] = [];
      }

      $result['records_ext'][$row[0]][] = [
        'matchid'  => $row[1],
        'value'    => $row[2],
        'playerid' => $row[3],
        'heroid'   => $row[4],
      ];
    }
  }

  $query_res->free_result();
} while($conn->next_result());

$result['records_ext'] = wrap_data($result['records_ext'], true, true, true);