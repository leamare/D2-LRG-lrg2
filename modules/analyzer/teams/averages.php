<?php
$sql = "";

# avg kills
$sql .= "SELECT \"kills\", SUM(ans.sum_kills)/SUM(ans.match_count) FROM (
          SELECT SUM(kills) sum_kills, COUNT(DISTINCT matchlines.matchid) match_count
          FROM matchlines JOIN teams_matches
          ON matchlines.matchid = teams_matches.matchid
          AND matchlines.isRadiant = teams_matches.is_radiant
          WHERE teams_matches.teamid = ".$id."
      ) ans;";

# avg deaths
$sql .= "SELECT \"deaths\", SUM(ans.sum_deaths)/SUM(ans.match_count) FROM (
          SELECT SUM(deaths) sum_deaths, COUNT(DISTINCT matchlines.matchid) match_count
          FROM matchlines JOIN teams_matches
          ON matchlines.matchid = teams_matches.matchid
          AND matchlines.isRadiant = teams_matches.is_radiant
          WHERE teams_matches.teamid = ".$id."
      ) ans;";

# avg assists
$sql .= "SELECT \"assists\", SUM(ans.sum_assists)/SUM(ans.match_count) FROM (
          SELECT SUM(assists) sum_assists, COUNT(DISTINCT matchlines.matchid) match_count
          FROM matchlines JOIN teams_matches
          ON matchlines.matchid = teams_matches.matchid
          AND matchlines.isRadiant = teams_matches.is_radiant
          WHERE teams_matches.teamid = ".$id."
      ) ans;";

# avg xpm
$sql .= "SELECT \"xpm\", SUM(ans.sum_xpm)/SUM(ans.match_count) FROM (
          SELECT SUM(xpm) sum_xpm, COUNT(DISTINCT matchlines.matchid) match_count
          FROM matchlines JOIN teams_matches
          ON matchlines.matchid = teams_matches.matchid
          AND matchlines.isRadiant = teams_matches.is_radiant
          WHERE teams_matches.teamid = ".$id."
          GROUP BY matchlines.matchid
      ) ans;";

# avg gpm
$sql .= "SELECT \"gpm\", SUM(ans.sum_gpm)/SUM(ans.match_count) FROM (
          SELECT SUM(gpm) sum_gpm, COUNT(DISTINCT matchlines.matchid) match_count
          FROM matchlines JOIN teams_matches
          ON matchlines.matchid = teams_matches.matchid
          AND matchlines.isRadiant = teams_matches.is_radiant
          WHERE teams_matches.teamid = ".$id."
          GROUP BY matchlines.matchid
      ) ans;";

# avg wards
$sql .= "SELECT \"wards_placed\", SUM(ans.sum_wards)/SUM(ans.match_count) FROM (
          SELECT SUM(adv_matchlines.wards) sum_wards, COUNT(DISTINCT matchlines.matchid) match_count
          FROM adv_matchlines JOIN matchlines
          ON adv_matchlines.matchid = matchlines.matchid
          AND adv_matchlines.playerid = matchlines.playerid
          JOIN teams_matches
          ON adv_matchlines.matchid = teams_matches.matchid
          AND matchlines.isRadiant = teams_matches.is_radiant
          WHERE teams_matches.teamid = ".$id."
          GROUP BY matchlines.matchid
      ) ans;";

# avg sentries
$sql .= "SELECT \"sentries_placed\", SUM(ans.sum_sentries)/SUM(ans.match_count) FROM (
          SELECT SUM(adv_matchlines.sentries) sum_sentries, COUNT(DISTINCT matchlines.matchid) match_count
          FROM adv_matchlines JOIN matchlines
          ON adv_matchlines.matchid = matchlines.matchid
          AND adv_matchlines.playerid = matchlines.playerid
          JOIN teams_matches
          ON adv_matchlines.matchid = teams_matches.matchid
          AND matchlines.isRadiant = teams_matches.is_radiant
          WHERE teams_matches.teamid = ".$id."
          GROUP BY matchlines.matchid
      ) ans;";

# avg wards destroyed
$sql .= "SELECT \"wards_destroyed\", SUM(ans.sum_wards_destroyed)/SUM(ans.match_count) FROM (
          SELECT SUM(adv_matchlines.wards_destroyed) sum_wards_destroyed, COUNT(DISTINCT matchlines.matchid) match_count
          FROM adv_matchlines JOIN matchlines
          ON adv_matchlines.matchid = matchlines.matchid
          AND adv_matchlines.playerid = matchlines.playerid
          JOIN teams_matches
          ON adv_matchlines.matchid = teams_matches.matchid
          AND matchlines.isRadiant = teams_matches.is_radiant
          WHERE teams_matches.teamid = ".$id."
          GROUP BY matchlines.matchid
      ) ans;";

# avg wards lost
$sql .= "SELECT \"wards_lost\", SUM(ans.sum_wards_destroyed)/SUM(ans.match_count) FROM (
  SELECT SUM(adv_matchlines.wards_destroyed) sum_wards_destroyed, COUNT(DISTINCT matchlines.matchid) match_count
  FROM adv_matchlines JOIN matchlines
  ON adv_matchlines.matchid = matchlines.matchid
  AND adv_matchlines.playerid = matchlines.playerid
  JOIN teams_matches
  ON adv_matchlines.matchid = teams_matches.matchid
  AND matchlines.isRadiant <> teams_matches.is_radiant
  WHERE teams_matches.teamid = ".$id."
  GROUP BY matchlines.matchid
) ans;";

# avg stacks
$sql .= "SELECT \"stacks_s\", SUM(ans.sum_stacks)/SUM(ans.match_count) FROM (
  SELECT SUM(adv_matchlines.stacks) sum_stacks, COUNT(DISTINCT matchlines.matchid) match_count
  FROM adv_matchlines JOIN matchlines
  ON adv_matchlines.matchid = matchlines.matchid
  AND adv_matchlines.playerid = matchlines.playerid
  JOIN teams_matches
  ON adv_matchlines.matchid = teams_matches.matchid
  AND matchlines.isRadiant = teams_matches.is_radiant
  WHERE teams_matches.teamid = ".$id."
  GROUP BY matchlines.matchid
) ans;";

# courier kills
$sql .= "SELECT \"courier_kills\", SUM(adv_matchlines.couriers_killed)/COUNT(DISTINCT matches.matchid) FROM adv_matchlines
          JOIN matchlines ON adv_matchlines.playerid = matchlines.playerid AND adv_matchlines.matchid = matchlines.matchid
          JOIN teams_matches ON teams_matches.matchid = matchlines.matchid AND teams_matches.is_radiant = matchlines.isradiant
          JOIN matches ON teams_matches.matchid = matches.matchid
          WHERE teams_matches.teamid = ".$id.";";

# roshan kills by hero's team
$sql .= "SELECT \"roshan_kills\", SUM(adv_matchlines.roshans_killed)/COUNT(DISTINCT matches.matchid) FROM adv_matchlines
          JOIN matchlines ON adv_matchlines.playerid = matchlines.playerid AND adv_matchlines.matchid = matchlines.matchid
          JOIN teams_matches ON teams_matches.matchid = matchlines.matchid AND teams_matches.is_radiant = matchlines.isradiant
          JOIN matches ON teams_matches.matchid = matches.matchid
          WHERE teams_matches.teamid = ".$id.";";

# hero pool
$sql .= "SELECT \"hero_pool\", COUNT(DISTINCT matchlines.heroid) FROM matchlines JOIN teams_matches
          ON matchlines.matchid = teams_matches.matchid
          AND matchlines.isRadiant = teams_matches.is_radiant
          WHERE teams_matches.teamid = ".$id.";";

# pings
$sql .= "SELECT \"pings\", SUM(CASE WHEN adv_matchlines.pings > 0 THEN adv_matchlines.pings ELSE 0 END)/(SUM(CASE WHEN adv_matchlines.pings > 0 THEN 1 ELSE 0 END)/5) FROM matchlines JOIN teams_matches
          ON matchlines.matchid = teams_matches.matchid
          AND matchlines.isRadiant = teams_matches.is_radiant
          JOIN adv_matchlines ON adv_matchlines.matchid = matchlines.matchid AND adv_matchlines.playerid = matchlines.playerid
          WHERE teams_matches.teamid = ".$id.";";

# diversity
# (COUNT(DISTINCT heroid)/mhpt.mhp) * (COUNT(DISTINCT heroid)/COUNT(DISTINCT matchid))
$sql .= "SELECT \"diversity\", (COUNT(DISTINCT matchlines.heroid)/mhpt.mhp)*(COUNT(DISTINCT matchlines.heroid)/COUNT(DISTINCT matchlines.matchid))
          FROM matchlines JOIN teams_matches JOIN (
            select max(heropool) mhp from (
              select COUNT(DISTINCT matchlines.heroid) heropool
              FROM matchlines JOIN teams_matches
              ON matchlines.matchid = teams_matches.matchid
              AND matchlines.isRadiant = teams_matches.is_radiant
              GROUP BY teams_matches.teamid
            ) _hp
          ) mhpt
          ON matchlines.matchid = teams_matches.matchid
          AND matchlines.isRadiant = teams_matches.is_radiant
          WHERE teams_matches.teamid = ".$id.";";

# radiant ratio
$sql .= "SELECT \"rad_ratio\", SUM(is_radiant)/COUNT(DISTINCT matchid)
          FROM teams_matches
          WHERE teamid = ".$id.";";

# radiant wr
$sql .= "SELECT \"radiant_wr\", SUM(matches.radiantWin)/COUNT(DISTINCT matches.matchid) FROM matches JOIN teams_matches
          ON matches.matchid = teams_matches.matchid
          AND teams_matches.is_radiant = 1
          WHERE teams_matches.teamid = ".$id.";";

# dire wr
$sql .= "SELECT \"dire_wr\", 1-(SUM(matches.radiantWin)/COUNT(DISTINCT matches.matchid)) FROM matches JOIN teams_matches
          ON matches.matchid = teams_matches.matchid
          AND teams_matches.is_radiant = 0
          WHERE teams_matches.teamid = ".$id.";";

if ($schema['matches_opener'] ?? false && $schema['fpable']) {
  # first pick ratio
  $sql .= "SELECT \"opener_ratio\", SUM(radiant_opener = is_radiant)/COUNT(DISTINCT matches.matchid)
      FROM teams_matches join matches on matches.matchid = teams_matches.matchid
      WHERE teamid = ".$id." AND modeID in (".implode(',', FP_ABLE).");";
  # first pick winrate
  $sql .= "SELECT \"opener_pick_winrate\", SUM(radiantWin = is_radiant)/COUNT(DISTINCT matches.matchid)
      FROM teams_matches join matches on matches.matchid = teams_matches.matchid
      WHERE teamid = ".$id." and radiant_opener = is_radiant AND modeID in (".implode(',', FP_ABLE).");";

  # FP Radiant Ratio
  $sql .= "SELECT \"opener_pick_radiant_ratio\", SUM(radiant_opener)/COUNT(DISTINCT matches.matchid)
      FROM teams_matches join matches on matches.matchid = teams_matches.matchid
      WHERE teamid = ".$id." AND is_radiant = true AND modeID in (".implode(',', FP_ABLE).");";

  # FP Radiant
  $sql .= "SELECT \"opener_pick_radiant_winrate\", SUM(radiantWin)/SUM(1)
      FROM teams_matches join matches on matches.matchid = teams_matches.matchid
      WHERE teamid = ".$id." AND is_radiant = true AND radiant_opener = true AND modeID in (".implode(',', FP_ABLE).");";
  # FP Dire
  $sql .= "SELECT \"opener_pick_dire_winrate\", SUM(NOT radiantWin)/SUM(1) 
      FROM teams_matches join matches on matches.matchid = teams_matches.matchid
      WHERE teamid = ".$id." AND is_radiant = false AND radiant_opener = false AND modeID in (".implode(',', FP_ABLE).");";

  # SP Radiant Ratio
  $sql .= "SELECT \"follower_pick_radiant_ratio\", SUM(NOT radiant_opener)/COUNT(DISTINCT matches.matchid)
    FROM teams_matches join matches on matches.matchid = teams_matches.matchid
    WHERE teamid = ".$id." AND is_radiant = true AND modeID in (".implode(',', FP_ABLE).");";

  # SP Radiant
  $sql .= "SELECT \"follower_pick_radiant_winrate\", SUM(radiantWin)/SUM(1)
      FROM teams_matches join matches on matches.matchid = teams_matches.matchid
      WHERE teamid = ".$id." AND is_radiant = true AND radiant_opener = false AND modeID in (".implode(',', FP_ABLE).");";
  # SP Dire
  $sql .= "SELECT \"follower_pick_dire_winrate\", SUM(NOT radiantWin)/SUM(1)
      FROM teams_matches join matches on matches.matchid = teams_matches.matchid
      WHERE teamid = ".$id." AND is_radiant = false AND radiant_opener = true AND modeID in (".implode(',', FP_ABLE).");";
}

# duration
$sql .= "SELECT \"avg_match_len\", (SUM(matches.duration)/60)/COUNT(DISTINCT matches.matchid) FROM matches JOIN teams_matches
          ON matches.matchid = teams_matches.matchid WHERE teams_matches.teamid = ".$id.";";

# median duration
$sql .= "set @rn := 0; select * from ( select *, @rn := @rn + 1 as rn from (
  select \"matches_median_duration\", matches.duration/60 as value
  FROM matches JOIN teams_matches 
  ON matches.matchid = teams_matches.matchid WHERE teams_matches.teamid = ".$id."
  ORDER BY `value` DESC
) a order by a.value DESC ) b where b.rn = @rn div 2;";

# win duration
$sql .= "SELECT \"avg_win_len\", (SUM(matches.duration)/60)/COUNT(DISTINCT matches.matchid) FROM matches JOIN teams_matches
          ON matches.matchid = teams_matches.matchid WHERE teams_matches.teamid = ".$id." AND matches.radiantWin = teams_matches.is_radiant;";

if ($conn->multi_query($sql) === TRUE) echo "[S] Requested data for TEAM $id AVERAGES.\n";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$result['teams'][$id]['averages'] = [];

do {
  $query_res = $conn->store_result();

  if (!is_bool($query_res)) {
    $row = $query_res->fetch_row();
    if (empty($row)) continue;

    $result['teams'][$id]['averages'][$row[0]] = $row[1];

    $query_res->free_result();
  }
} while($conn->next_result());

// Additional stuff 

# rampages
$sql = "SELECT \"rampages_total\", SUM(adv_matchlines.multi_kill > 4) FROM adv_matchlines JOIN matchlines
      ON adv_matchlines.matchid = matchlines.matchid AND adv_matchlines.playerid = matchlines.playerid JOIN teams_matches
      ON matchlines.matchid = teams_matches.matchid AND teams_matches.is_radiant = matchlines.isradiant WHERE teams_matches.teamid = ".$id.";";

# rampages last match
$sql .= "SELECT \"rampages_last_match\", MAX(adv_matchlines.matchid) FROM adv_matchlines JOIN matchlines
      ON adv_matchlines.matchid = matchlines.matchid AND adv_matchlines.playerid = matchlines.playerid JOIN teams_matches
      ON matchlines.matchid = teams_matches.matchid AND teams_matches.is_radiant = matchlines.isradiant WHERE teams_matches.teamid = ".$id." AND adv_matchlines.multi_kill > 4;";

$result['teams'][$id]['add_info'] = [];

if ($conn->multi_query($sql) === TRUE);
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

do {
  $query_res = $conn->store_result();

  if (!is_bool($query_res)) {
    $row = $query_res->fetch_row();
    if (empty($row)) continue;

    $result['teams'][$id]['add_info'][$row[0]] = $row[1];

    $query_res->free_result();
  }
} while($conn->next_result());