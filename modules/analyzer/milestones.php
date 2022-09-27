<?php

$wheres = "";
if (!empty($players_interest)) {
  $wheres = " WHERE playerid in (".implode(',', $players_interest).") ";
}

$result['milestones'] = [];

// === total milestones

$sql  = "SELECT \"total:matches\", 0, COUNT(distinct matchid) value FROM matches ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"total:kills\", 0, SUM(deaths) value FROM matchlines ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"total:lasthits\", 0, SUM(lastHits) value FROM matchlines ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"total:denies\", 0, SUM(denies) value FROM matchlines ORDER BY value DESC LIMIT $avg_limit;";

$sql .= "SELECT \"total:courier_kills\", 0, SUM(couriers_killed) value FROM adv_matchlines ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"total:roshan_kills\", 0, SUM(roshans_killed) value FROM adv_matchlines ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"total:hero_damage\", 0, SUM(heroDamage) value FROM matchlines ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"total:tower_damage\", 0, SUM(towerDamage) value FROM matchlines ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"total:heal\", 0, SUM(heal) value FROM matchlines ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"total:damage_taken\", 0, SUM(damage_taken) value FROM adv_matchlines ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"total:stuns\", 0, SUM(stuns) value FROM adv_matchlines ORDER BY value DESC LIMIT $avg_limit;";

$sql .= "SELECT \"total:wards_placed\", 0, SUM(wards) value FROM adv_matchlines;";
$sql .= "SELECT \"total:sentries_placed\", 0, SUM(sentries) value FROM adv_matchlines;";
$sql .= "SELECT \"total:wards_destroyed\", 0, SUM(wards_destroyed) value FROM adv_matchlines;";
$sql .= "SELECT \"total:buybacks\", 0, SUM(buybacks) value FROM adv_matchlines;";
$sql .= "SELECT \"total:stacks\", 0, SUM(stacks) value FROM adv_matchlines;";
$sql .= "SELECT \"total:pings\", 0, SUM(pings) value FROM adv_matchlines;";

$sql .= "SELECT \"total:rampages\", 0, SUM(multi_kill >= 5) value FROM adv_matchlines;";
$sql .= "SELECT \"total:godlikes\", 0, SUM(streak >= 9) value FROM adv_matchlines;";
$sql .= "SELECT \"total:playtime\", 0, SUM(duration) value FROM matches m;";
$sql .= "SELECT \"total:time_dead\", 0, SUM(time_dead) value FROM adv_matchlines;";

// === heroes milestones

$sql .= "SELECT \"heroes:matches\", heroid, COUNT(distinct matchid) value FROM matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:wins\", heroid, SUM(NOT m.radiantWin XOR ml.isradiant) value 
  FROM matchlines ml JOIN matches m ON ml.matchid = m.matchid $wheres
  GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";

$sql .= "SELECT \"heroes:kills\", heroid, SUM(kills) value FROM matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:deaths\", heroid, SUM(deaths) value FROM matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:assists\", heroid, SUM(assists) value FROM matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:contribution\", heroid, SUM(kills+assists) value FROM matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:creeps_killed\", heroid, SUM(lastHits) value FROM matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:creeps_denies\", heroid, SUM(denies) value FROM matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";

$sql .= "SELECT \"heroes:courier_kills\", heroid, SUM(couriers_killed) value FROM adv_matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:roshan_kills\", heroid, SUM(roshans_killed) value FROM adv_matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:hero_damage\", heroid, SUM(heroDamage) value FROM matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:tower_damage\", heroid, SUM(towerDamage) value FROM matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:heal\", heroid, SUM(heal) value FROM matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:damage_taken\", heroid, SUM(damage_taken) value FROM adv_matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:stuns\", heroid, SUM(CASE WHEN stuns > 0 THEN stuns ELSE 0 END) value FROM adv_matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";

$sql .= "SELECT \"heroes:wards_placed\", heroid, SUM(wards) value FROM adv_matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:sentries_placed\", heroid, SUM(sentries) value FROM adv_matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:wards_destroyed\", heroid, SUM(wards_destroyed) value FROM adv_matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:buybacks\", heroid, SUM(buybacks) value FROM adv_matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:stacks\", heroid, SUM(stacks) value FROM adv_matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:pings\", heroid, SUM(pings) value FROM adv_matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";

$sql .= "SELECT \"heroes:rampages\", heroid, SUM(multi_kill >= 5) value FROM adv_matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:godlikes\", heroid, SUM(streak >= 9) value FROM adv_matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:playtime\", heroid, SUM(m.duration) value 
  FROM matchlines ml JOIN matches m ON ml.matchid = m.matchid $wheres
  GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";
$sql .= "SELECT \"heroes:time_dead\", heroid, SUM(time_dead) value FROM adv_matchlines $wheres GROUP BY heroid ORDER BY value DESC LIMIT $avg_limit;";

// === players milestones

if (!$lg_settings['ana']['anon_records']) {
  $sql .= "SELECT \"players:matches\", playerid, COUNT(distinct matchid) value FROM matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:wins\", playerid, SUM(NOT m.radiantWin XOR ml.isradiant) value 
    FROM matchlines ml JOIN matches m ON ml.matchid = m.matchid $wheres
    GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";

  $sql .= "SELECT \"players:kills\", playerid, SUM(kills) value FROM matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:deaths\", playerid, SUM(deaths) value FROM matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:assists\", playerid, SUM(assists) value FROM matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:contribution\", playerid, SUM(kills+assists) value FROM matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:creeps_killed\", playerid, SUM(lastHits) value FROM matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:creeps_denies\", playerid, SUM(denies) value FROM matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";

  $sql .= "SELECT \"players:courier_kills\", playerid, SUM(couriers_killed) value FROM adv_matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:roshan_kills\", playerid, SUM(roshans_killed) value FROM adv_matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:hero_damage\", playerid, SUM(heroDamage) value FROM matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:tower_damage\", playerid, SUM(towerDamage) value FROM matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:heal\", playerid, SUM(heal) value FROM matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:damage_taken\", playerid, SUM(damage_taken) value FROM adv_matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:stuns\", playerid, SUM(CASE WHEN stuns > 0 THEN stuns ELSE 0 END) value FROM adv_matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";

  $sql .= "SELECT \"players:wards_placed\", playerid, SUM(wards) value FROM adv_matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:sentries_placed\", playerid, SUM(sentries) value FROM adv_matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:wards_destroyed\", playerid, SUM(wards_destroyed) value FROM adv_matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:buybacks\", playerid, SUM(buybacks) value FROM adv_matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:stacks\", playerid, SUM(stacks) value FROM adv_matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:pings\", playerid, SUM(pings) value FROM adv_matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";

  $sql .= "SELECT \"players:rampages\", playerid, SUM(multi_kill >= 5) value FROM adv_matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:godlikes\", playerid, SUM(streak >= 9) value FROM adv_matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:playtime\", playerid, SUM(m.duration) value 
    FROM matchlines ml JOIN matches m ON ml.matchid = m.matchid $wheres
    GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"players:time_dead\", playerid, SUM(time_dead) value FROM adv_matchlines $wheres GROUP BY playerid ORDER BY value DESC LIMIT $avg_limit;";
}

// === teams milestones

if ($lg_settings['main']['teams']) {
  $wheres_tm = "";
  if (!empty($result["teams_interest"])) {
    $wheres_tm = " WHERE tm.teamid in (".implode(',', $result["teams_interest"]).") ";
  }

  $sql .= "SELECT \"teams:matches\", teamid, COUNT(distinct matchid) value FROM teams_matches tm GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"teams:wins\", teamid, SUM(NOT m.radiantWin XOR tm.is_radiant) value 
    FROM teams_matches tm JOIN matches m ON tm.matchid = m.matchid $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";

  $sql .= "SELECT \"teams:kills\", teamid, SUM(ml.kills) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"teams:deaths\", teamid, SUM(ml.deaths) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"teams:creeps_killed\", teamid, SUM(ml.lastHits) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"teams:creeps_denies\", teamid, SUM(ml.denies) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";

  $sql .= "SELECT \"teams:courier_kills\", teamid, SUM(am.couriers_killed) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant
    JOIN adv_matchlines am ON am.matchid = ml.matchid AND am.playerid = ml.playerid $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"teams:roshan_kills\", teamid, SUM(am.roshans_killed) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant
    JOIN adv_matchlines am ON am.matchid = ml.matchid AND am.playerid = ml.playerid $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"teams:hero_damage\", teamid, SUM(ml.heroDamage) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"teams:tower_damage\", teamid, SUM(ml.towerDamage) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"teams:heal\", teamid, SUM(ml.heal) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"teams:damage_taken\", teamid, SUM(am.damage_taken) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant
    JOIN adv_matchlines am ON am.matchid = ml.matchid AND am.playerid = ml.playerid $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"teams:stuns\", teamid, SUM(CASE WHEN am.stuns > 0 THEN am.stuns ELSE 0 END) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant
    JOIN adv_matchlines am ON am.matchid = ml.matchid AND am.playerid = ml.playerid $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";

  $sql .= "SELECT \"teams:wards_placed\", teamid, SUM(am.wards) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant
    JOIN adv_matchlines am ON am.matchid = ml.matchid AND am.playerid = ml.playerid $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"teams:sentries_placed\", teamid, SUM(am.sentries) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant
    JOIN adv_matchlines am ON am.matchid = ml.matchid AND am.playerid = ml.playerid $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"teams:wards_destroyed\", teamid, SUM(am.wards_destroyed) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant
    JOIN adv_matchlines am ON am.matchid = ml.matchid AND am.playerid = ml.playerid $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"teams:buybacks\", teamid, SUM(am.buybacks) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant
    JOIN adv_matchlines am ON am.matchid = ml.matchid AND am.playerid = ml.playerid $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"teams:stacks\", teamid, SUM(am.stacks) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant
    JOIN adv_matchlines am ON am.matchid = ml.matchid AND am.playerid = ml.playerid $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"teams:pings\", teamid, SUM(am.pings) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant
    JOIN adv_matchlines am ON am.matchid = ml.matchid AND am.playerid = ml.playerid $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";

  $sql .= "SELECT \"teams:rampages\", teamid, SUM(am.multi_kill >= 5) value 
    FROM matchlines ml JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant
    JOIN adv_matchlines am ON am.matchid = ml.matchid AND am.playerid = ml.playerid $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";
  $sql .= "SELECT \"teams:playtime\", teamid, SUM(m.duration) value 
    FROM matches m JOIN teams_matches tm ON m.matchid = tm.matchid $wheres_tm
    GROUP BY teamid ORDER BY value DESC LIMIT $avg_limit;";
}

if ($conn->multi_query($sql) === TRUE) echo "[S] Requested MILESTONES\n";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

do {
  $query_res = $conn->store_result();

  $row = $query_res->fetch_row();

  if (!empty($row)) {
    [ $type, $value ] = explode(':', $row[0]);

    if (!isset($result['milestones'][$type])) $res['milestones'][$type] = [];
    if (!isset($result['milestones'][$type][$value])) $res['milestones'][$type][$value] = [];

    for ($i=0; $i<$avg_limit && $row != null; $i++, $row = $query_res->fetch_row()) {
      if (+$row[1]) {
        $result['milestones'][$type][$value][$row[1]] = (int)$row[2];
      } else {
        $result['milestones'][$type][$value][] = (int)$row[2];
      }
    }
  }

  $query_res->free_result();

} while($conn->next_result());
