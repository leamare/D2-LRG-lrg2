<?php 

function rg_query_hero_variants(&$conn, $team = null, $cluster = null, $players = null) {
  $res = [];

  $wheres = [];
  if ($cluster !== null) $wheres[] = "matches.cluster IN (".implode(",", $cluster).")";
  if ($team !== null) $wheres[] = "tm.teamid = $team";
  if ($players !== null) $wheres[] = "ml.playerid in (".implode(',', $players).")";

  $sql = "SELECT
    heroid, 
    variant,
    count(distinct ml.matchid) matches, 
    SUM(matches.radiantWin = ml.isRadiant) wins
    FROM matchlines ml JOIN matches ON ml.matchid = matches.matchid ".
    ($team === null ? "" : "JOIN teams_matches tm ON ml.matchid = tm.matchid AND ml.isRadiant = tm.is_radiant ").
  (!empty($wheres) ? "WHERE ".implode(' AND ', $wheres) : "").
  " GROUP BY 1, 2;";

  if ($conn->multi_query($sql) === TRUE);
  else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();

  $heroes = [];
  $variants = [];

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    [ $hid, $variant, $matches, $wins ] = $row;
    $variants[ "$hid-$variant" ] = [
      +$hid,
      +$variant,
      +$matches,
      +$wins,
    ];
    $heroes[ $hid ] = ($heroes[ $hid ] ?? 0)+$matches;
  }

  foreach ($variants as $stats) {
    [
      $hid,
      $variant,
      $matches,
      $wins,
    ] = $stats;
    $res[ "$hid-$variant" ] = [
      'm' => $matches,
      'w' => $wins,
      'f' => round($matches/$heroes[$hid], 4),
    ];
  }

  $query_res->free_result();

  return $res;
}