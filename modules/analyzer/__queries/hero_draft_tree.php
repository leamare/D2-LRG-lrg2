<?php 

function rg_query_hero_draft_tree(&$conn, $limiter, $cluster = null, $team = null) {
  $res = [];

  $wheres = [];

  if($cluster) $wheres[] = "m.cluster IN (".implode(",", $cluster).")";
  if($team) $wheres[] = "tm.teamid = $team";

  $sql = "SELECT 
    d1.hero_id,
    d2.hero_id,
    (d1.stage * 2 + d1.is_pick),
    (d2.stage * 2 + d2.is_pick),
    SUM(1) count,
    SUM(NOT (d1.is_radiant XOR m.radiantWin) ) wins
  FROM
  draft d1 JOIN draft d2 ON d1.matchid = d2.matchid 
    AND d1.hero_id > d2.hero_id 
    AND d1.is_radiant = d2.is_radiant
    AND ( ( CAST((d1.stage * 2 + d1.is_pick) AS SIGNED) - CAST((d2.stage * 2 + d2.is_pick) AS SIGNED) ) IN (-1, 0, 1)
      OR ( CAST(d1.stage AS SIGNED) - CAST(d2.stage AS SIGNED) in (-1, 0, 1) AND d1.is_pick = d2.is_pick )
    )
  JOIN matches m ON d1.matchid = m.matchid ".
  ($team ? "JOIN teams_matches tm ON tm.matchid = m.matchid AND d1.is_radiant = tm.is_radiant " : "").
  (!empty($wheres) ? "WHERE ".implode(' AND ', $wheres) : "").
  " GROUP BY d1.hero_id, d2.hero_id
  HAVING count > $limiter
  ";


  if ($conn->multi_query($sql) !== TRUE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    $res[] = [
      "hero1" => $row[0],
      "hero2" => $row[1],
      "stage1"=> $row[2],
      "stage2"=> $row[3],
      "count" => $row[4],
      "wins"  => $row[5]
    ];
  }

  $query_res->free_result();

  return wrap_data($res);
  // return $res;
}