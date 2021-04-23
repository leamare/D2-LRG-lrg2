<?php
$result["hero_daily_wr"] = [];
echo "[S] Requested data for HEROES DAILY WR.\n";

// $start_timestamp = $query_res->fetch_row()[0] - 3600;

if ($lg_settings['ana']['heroes_daily_winrate'] !== true && $lg_settings['ana']['heroes_daily_winrate'] > 1) {
  $multiplier = $lg_settings['ana']['heroes_daily_winrate'];
} else {
  $multiplier = 1;
}

$mday = 86400*$multiplier;

$sql = "SELECT
  ml.heroid, ( (start_date-$start_timestamp) DIV $mday ) day, SUM(1) matches, SUM(NOT m.radiantWin XOR ml.isradiant)/SUM(1) winrate
  FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
  WHERE ml.heroid > 0
  GROUP BY ml.heroid, day
  ORDER BY matches DESC;";

if ($conn->multi_query($sql) === TRUE);
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if (!isset($result["hero_daily_wr"][ $row[0] ])) $result["hero_daily_wr"][ $row[0] ] = [];
  $day = $start_timestamp + $mday*$row[1];
  $result["hero_daily_wr"][ $row[0] ][ $day ] = [
    'ms' => (int)$row[2],
    'wr' => (float)$row[3],
    'bn' => 0
  ];
}

$query_res->free_result();

$sql = "SELECT
  dr.hero_id, ( (start_date-$start_timestamp) DIV $mday ) day, SUM(1) matches
  FROM draft dr JOIN matches m ON m.matchid = dr.matchid
  WHERE dr.is_pick = 0 AND dr.hero_id > 0
  GROUP BY dr.hero_id, day
  ORDER BY matches DESC;";

if ($conn->multi_query($sql) === TRUE);
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if (!isset($result["hero_daily_wr"][ $row[0] ])) $result["hero_daily_wr"][ $row[0] ] = [];
  $day = $start_timestamp + $mday*$row[1];
  if (isset($result["hero_daily_wr"][ $row[0] ][ $day ])) {
    $result["hero_daily_wr"][ $row[0] ][ $day ]['bn'] = (int)$row[2];
  } else {
    $result["hero_daily_wr"][ $row[0] ][ $day ] = [
      'ms' => 0,
      'wr' => 0,
      'bn' => (int)$row[2]
    ];
  }
}

$query_res->free_result();

$result["hero_daily_wr"] = wrap_data(
  $result["hero_daily_wr"],
  true,
  true,
  true
);