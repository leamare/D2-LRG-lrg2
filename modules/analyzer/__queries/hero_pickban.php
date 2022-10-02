<?php 

function rg_query_hero_pickban(&$conn, $team = null, $cluster = null, $players = null) {
  global $players_interest;
  if (empty($players) && empty($team) && !empty($players_interest)) {
    $players = $players_interest;
  }

  $res = [];

  $sql = "SELECT draft.hero_id hero_id, 
    count(distinct draft.matchid) matches, 
    SUM(NOT matches.radiantWin XOR draft.is_radiant)/count(distinct draft.matchid) winrate
    FROM draft JOIN matches ON draft.matchid = matches.matchid ".
    ($team === null ? "" : "JOIN teams_matches ON draft.matchid = teams_matches.matchid AND draft.is_radiant = teams_matches.is_radiant ").
    ($players === null ? "" : " JOIN matchlines ON matchlines.matchid = draft.matchid AND draft.hero_id = matchlines.heroid ").
  " WHERE is_pick = true ".
    ($cluster !== null ? " AND matches.cluster IN (".implode(",", $cluster).") " : "").
    ($team === null ? "" : " AND teams_matches.teamid = $team ").
    ($players === null ? "" : " AND matchlines.playerid in (".implode(',', $players).")").
  " GROUP BY draft.hero_id;";

  if ($conn->multi_query($sql) === TRUE);
  else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    $res[$row[0]] = array (
    "matches_total"   => $row[1],
    "matches_picked"  => $row[1],
    "winrate_picked"  => $row[2],
    "matches_banned"  => 0,
    "winrate_banned"  => 0
    );
  }

  $query_res->free_result();

  $sql = "SELECT draft.hero_id hero_id, 
    count(distinct draft.matchid) matches, 
    SUM(NOT matches.radiantWin XOR draft.is_radiant)/count(distinct draft.matchid)".($players !== null ? "/5" : "")." winrate
    FROM draft JOIN matches ON draft.matchid = matches.matchid ".
    ($team === null ? "" : "JOIN teams_matches ON draft.matchid = teams_matches.matchid AND draft.is_radiant = teams_matches.is_radiant ").
    ($players === null ? "" : " JOIN (
      select matchid, isRadiant, CONCAT('[', GROUP_CONCAT(playerid), ']') as conc_playerid
      from matchlines 
      where playerid in (".implode(',', $players).")
      group by 1, 2
    ) ml ON ml.matchid = draft.matchid AND draft.is_radiant <> ml.isRadiant ").
  " WHERE is_pick = false ".
    ($cluster !== null ? " AND matches.cluster IN (".implode(",", $cluster).") " : "").
    ($team === null ? "" : " AND teams_matches.teamid = $team ").
  " GROUP BY draft.hero_id;";

  if ($conn->multi_query($sql) === TRUE);
  else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if(isset($res[$row[0]])) {
    $res[$row[0]] = array (
      "matches_total"   => ($res[$row[0]]["matches_total"]+$row[1]),
      "matches_picked"  => $res[$row[0]]["matches_picked"],
      "winrate_picked"  => $res[$row[0]]["winrate_picked"],
      "matches_banned"  => $row[1],
      "winrate_banned"  => $row[2]
    );
  } else
    $res[$row[0]] = array (
      "matches_total"   => $row[1],
      "matches_picked"  => 0,
      "winrate_picked"  => 0,
      "matches_banned"  => $row[1],
      "winrate_banned"  => $row[2]
    );
  }

  $query_res->free_result();

  return $res;
}