<?php

$result["random"] = [];

# matches total
$sql  = "SELECT \"matches_total\", COUNT(matchid) FROM matches;";
# matches without analysis
$sql .= "SELECT \"matches_unparsed\", COUNT(DISTINCT matchid) FROM adv_matchlines;";
# players on event
$sql .= "SELECT \"players_on_event\", COUNT(playerID) FROM players;";
if($lg_settings['main']['teams']) # teams on event
  $sql .= "SELECT \"teams_on_event\", COUNT(DISTINCT teamid) FROM teams_matches;";

# heroes contested
$sql .= "SELECT \"heroes_contested\", count(distinct hero_id) FROM draft;";
# heroes picked
$sql .= "SELECT \"heroes_picked\", count(distinct hero_id) FROM draft WHERE is_pick = 1;";
# heroes banned
$sql .= "SELECT \"heroes_banned\", count(distinct hero_id) FROM draft WHERE is_pick = 0;";

# ********** Medians

# heroes median picks
$sql .= "set @rn := 0; select * from ( select *, @rn := @rn + 1 as rn from (
        select \"heroes_median_picks\", COUNT(DISTINCT matchlines.matchid) value, matchlines.heroid hid from matchlines GROUP BY matchlines.heroid
        ORDER BY `value`  DESC
        ) a order by a.value DESC ) b where b.rn = @rn div 2;";

# heroes median bans
$sql .= "set @rn := 0; select * from ( select *, @rn := @rn + 1 as rn from (
        select \"heroes_median_bans\", SUM(1) value, draft.hero_id hid
        from draft where draft.is_pick = 0 GROUP BY hid ORDER BY `value`  DESC
        ) a order by a.value DESC ) b where b.rn = @rn div 2;";

# heroes median gpm
$sql .= "set @rn := 0; select * from ( select *, @rn := @rn + 1 as rn from (
        select \"heroes_median_gpm\", matchlines.gpm value from matchlines
        ORDER BY `value`  DESC
        ) a order by a.value DESC ) b where b.rn = @rn div 2;";

# heroes median xpm
$sql .= "set @rn := 0; select * from ( select *, @rn := @rn + 1 as rn from (
        select \"heroes_median_xpm\", matchlines.xpm value from matchlines
        ORDER BY `value`  DESC
        ) a order by a.value DESC ) b where b.rn = @rn div 2;";

# matches median duration
$sql .= "set @rn := 0; select * from ( select *, @rn := @rn + 1 as rn from (
        select \"matches_median_duration\", matches.duration/60 as value
        from matches ORDER BY `value` DESC
        ) a order by a.value DESC ) b where b.rn = @rn div 2;";

# **********

# Radiant winrate
$sql .= "SELECT \"radiant_wr\", SUM(radiantWin)*100/SUM(1) FROM matches;";
# Dire winrate
$sql .= "SELECT \"dire_wr\", (1-(SUM(radiantWin)/SUM(1)))*100 FROM matches;";

# roshans killed
$sql .= "SELECT \"roshans_killed_total\", SUM(roshans_killed) FROM adv_matchlines;";
# Radiant avg roshans
$sql .= "SELECT \"radiant_avg_roshans\", SUM(am.roshans_killed)/COUNT(distinct ml.matchid)
  FROM adv_matchlines am JOIN matchlines ml ON am.matchid = ml.matchid AND am.playerid = ml.playerid 
  WHERE ml.isRadiant = 1;";
# Dire avg roshans
$sql .= "SELECT \"dire_avg_roshans\", SUM(am.roshans_killed)/COUNT(distinct ml.matchid)
  FROM adv_matchlines am JOIN matchlines ml ON am.matchid = ml.matchid AND am.playerid = ml.playerid 
  WHERE ml.isRadiant = 0;";

# total creeps killed (lh+dn)
$sql .= "SELECT \"creeps_killed\", SUM(lasthits+denies) FROM matchlines;";
# Total avg creeps
$sql .= "SELECT \"creeps_killed_avg\", SUM(ml.lasthits)/COUNT(distinct ml.matchid)
  FROM matchlines ml;";
# Radiant avg creeps
$sql .= "SELECT \"radiant_creeps_killed_avg\", SUM(ml.lasthits)/COUNT(distinct ml.matchid)
  FROM matchlines ml WHERE ml.isRadiant = 1;";
# Dire avg creeps
$sql .= "SELECT \"dire_creeps_killed_avg\", SUM(ml.lasthits)/COUNT(distinct ml.matchid)
  FROM matchlines ml WHERE ml.isRadiant = 0;";

# total wards placed
$sql .= "SELECT \"obs_total\", SUM(wards) FROM adv_matchlines;";
# Radiant avg wards
$sql .= "SELECT \"radiant_avg_wards\", SUM(am.wards)/COUNT(distinct ml.matchid)
  FROM adv_matchlines am JOIN matchlines ml ON am.matchid = ml.matchid AND am.playerid = ml.playerid 
  WHERE ml.isRadiant = 1;";
# Dire avg wards
$sql .= "SELECT \"dire_avg_wards\", SUM(am.wards)/COUNT(distinct ml.matchid)
  FROM adv_matchlines am JOIN matchlines ml ON am.matchid = ml.matchid AND am.playerid = ml.playerid 
  WHERE ml.isRadiant = 0;";

# total wards destroyed
$sql .= "SELECT \"obs_killed_total\", SUM(wards_destroyed) FROM adv_matchlines;";
# Radiant avg wards destroyed
$sql .= "SELECT \"radiant_avg_wards_destroyed\", SUM(am.wards_destroyed)/COUNT(distinct ml.matchid)
  FROM adv_matchlines am JOIN matchlines ml ON am.matchid = ml.matchid AND am.playerid = ml.playerid 
  WHERE ml.isRadiant = 1;";
# Dire avg wards destroyed
$sql .= "SELECT \"dire_avg_wards_destroyed\", SUM(am.wards_destroyed)/COUNT(distinct ml.matchid)
  FROM adv_matchlines am JOIN matchlines ml ON am.matchid = ml.matchid AND am.playerid = ml.playerid 
  WHERE ml.isRadiant = 0;";

# couriers killed
$sql .= "SELECT \"couriers_killed_total\", SUM(couriers_killed) FROM adv_matchlines;";
# Radiant avg couriers killed
$sql .= "SELECT \"radiant_avg_couriers_killed\", SUM(am.couriers_killed)/COUNT(distinct ml.matchid)
  FROM adv_matchlines am JOIN matchlines ml ON am.matchid = ml.matchid AND am.playerid = ml.playerid 
  WHERE ml.isRadiant = 1;";
# Dire avg couriers killed
$sql .= "SELECT \"dire_avg_couriers_killed\", SUM(am.couriers_killed)/COUNT(distinct ml.matchid)
  FROM adv_matchlines am JOIN matchlines ml ON am.matchid = ml.matchid AND am.playerid = ml.playerid 
  WHERE ml.isRadiant = 0;";

# buybacks total
$sql .= "SELECT \"buybacks_total\", SUM(buybacks) FROM adv_matchlines;";
# Radiant avg buybacks total
$sql .= "SELECT \"radiant_avg_buybacks_total\", SUM(am.buybacks)/COUNT(distinct ml.matchid)
  FROM adv_matchlines am JOIN matchlines ml ON am.matchid = ml.matchid AND am.playerid = ml.playerid 
  WHERE ml.isRadiant = 1;";
# Dire avg buybacks total
$sql .= "SELECT \"dire_avg_buybacks_total\", SUM(am.buybacks)/COUNT(distinct ml.matchid)
  FROM adv_matchlines am JOIN matchlines ml ON am.matchid = ml.matchid AND am.playerid = ml.playerid 
  WHERE ml.isRadiant = 0;";

# rampages total
$sql .= "SELECT \"rampages_total\", SUM(multi_kill > 4) FROM adv_matchlines;";
# average match length
$sql .= "SELECT \"avg_match_len\", SUM(duration)/(60*COUNT(DISTINCT matchid)) FROM matches;";

# average match length when radiant won
$sql .= "SELECT \"avg_match_len_radiant_win\", SUM(duration)/(60*COUNT(DISTINCT matchid)) FROM matches WHERE radiantWin = 1;";
# average match length when dire won
$sql .= "SELECT \"avg_match_len_dire_win\", SUM(duration)/(60*COUNT(DISTINCT matchid)) FROM matches WHERE radiantWin = 0;";

if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for RANDOM STATS.\n";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

do {
  $query_res = $conn->store_result();

  if(!is_bool($query_res)) {
    $row = $query_res->fetch_row();
    if (!empty($row))
      $result["random"][$row[0]] = $row[1];
    $query_res->free_result();
  }
} while($conn->next_result());

if (isset($result['random']['matches_unparsed'])) {
  // it doesn't really work as intended since it shows PARSED matches, so here's this small hotfix
  $result['random']['matches_unparsed'] = $result['random']['matches_total']-$result['random']['matches_unparsed'];
}

echo "[ ] Matches: ".$result['random']['matches_total']."\n".
  "[ ] Unparsed: ".$result['random']['matches_unparsed']."\n";

