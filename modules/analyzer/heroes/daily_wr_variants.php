<?php
$result["hero_daily_wr_v"] = [];
echo "[S] Requested data for HEROES DAILY WR.\n";

// $start_timestamp = $query_res->fetch_row()[0] - 3600;

if ($lg_settings['ana']['heroes_daily_winrate'] !== true && $lg_settings['ana']['heroes_daily_winrate'] > 1) {
  $multiplier = $lg_settings['ana']['heroes_daily_winrate'];
} else {
  $multiplier = 1;
}

$wheres = "";
if (!empty($players_interest)) {
  $wheres = " AND ml.playerid in (".implode(',', $players_interest).") ";
}

$mday = 86400*$multiplier;

$sql = "SELECT
  ml.heroid, ml.variant, ( (start_date-$start_timestamp) DIV $mday ) day, SUM(1) matches, SUM(NOT m.radiantWin XOR ml.isradiant)/SUM(1) winrate
  FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
  WHERE ml.heroid > 0 
  $wheres
  GROUP BY ml.heroid, ml.variant, day
  ORDER BY matches DESC;";

if ($conn->multi_query($sql) === TRUE);
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if (!isset($result["hero_daily_wr_v"][ $row[0].'-'.$row[1] ])) $result["hero_daily_wr_v"][ $row[0].'-'.$row[1] ] = [];
  $day = $start_timestamp + $mday*$row[2];
  $result["hero_daily_wr_v"][ $row[0].'-'.$row[1] ][ $day ] = [
    'ms' => (int)$row[3],
    'wr' => (float)$row[4],
  ];
}

$query_res->free_result();

$result["hero_daily_wr_v"] = wrap_data(
  $result["hero_daily_wr_v"],
  true,
  true,
  true
);