<?php

$purchases_i = [];
$purchases_h = [ 'total' => [] ];

$q = <<<SQL
  SELECT 
    items.item_id item,
    items.category_id category,
    SUM(1) purchases,
    SUM(NOT matches.radiantWin XOR matchlines.isRadiant) wins,
    min(`time`) min_time,
    max(`time`) max_time,
    CAST( SUM(`time`)/SUM(1) AS SIGNED ) avg_time,
    COUNT(items.matchid) matchcount
  FROM (
    SELECT
      items.matchid matchid,
      items.item_id item_id,
      items.hero_id hero_id,
      min(items.time) `time`,
      sum(1) purchases,
      items.playerid playerid,
      items.category_id category_id
    FROM items GROUP BY items.matchid, items.item_id, items.hero_id
  ) items
    JOIN matchlines ON matchlines.matchid = items.matchid AND matchlines.heroid = items.hero_id
    JOIN matches ON matchlines.matchid = matches.matchid
  GROUP BY items.item_id
SQL;

$r['total'] = [];

if ($conn->multi_query($q) === TRUE) echo "!";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

for ($row = $query_res->fetch_assoc(); $row != null; $row = $query_res->fetch_assoc()) {
  $item = $row['item'];
  unset($row['item']);
  $r['total'][$item] = $row;

  $purchases_h['total'][] = (int)$row['purchases'];
}

$query_res->free_result();

// HEROES 

$q = <<<SQL
  SELECT 
    items.item_id item,
    items.hero_id hero,
    items.category_id category,
    SUM(1) purchases,
    SUM(NOT matches.radiantWin XOR matchlines.isRadiant) wins,
    min(time) min_time,
    max(time) max_time,
    CAST( SUM(time)/SUM(1) AS SIGNED ) avg_time,
    COUNT(DISTINCT items.matchid) matchcount
  FROM (
    SELECT
      items.matchid matchid,
      items.item_id item_id,
      items.hero_id hero_id,
      min(items.time) time,
      sum(1) purchases,
      items.playerid playerid,
      items.category_id category_id
    FROM items GROUP BY items.matchid, items.item_id, items.hero_id
  ) items
    JOIN matchlines ON matchlines.matchid = items.matchid AND matchlines.heroid = items.hero_id
    JOIN matches ON matchlines.matchid = matches.matchid
  GROUP BY items.item_id, items.hero_id;
SQL;

if ($conn->multi_query($q) === TRUE) echo " PI-PH ";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

for ($row = $query_res->fetch_assoc(); $row != null; $row = $query_res->fetch_assoc()) {
  $hid = $row['hero'];
  unset($row['hero']);
  $item = $row['item'];
  unset($row['item']);
  if (!isset($r[$hid])) $r[$hid] = [];
  $r[$hid][$item] = $row;

  if (!isset($purchases_i[$item])) $purchases_i[$item] = [];
  $purchases_i[$item][] = (int)$row['purchases'];
  if (!isset($purchases_h[$hid])) $purchases_h[$hid] = [];
  $purchases_h[$hid][] = (int)$row['purchases'];
}

$query_res->free_result();

foreach ($purchases_i as $iid => $dt) {
  $purchases_i[$iid] = [
    'q1' => quantile($dt, 0.25),
    'med' => quantile($dt, 0.5),
    'q3' => quantile($dt, 0.75),
  ];
}
foreach ($purchases_h as $hid => $dt) {
  $purchases_h[$hid] = [
    'q1' => quantile($dt, 0.25),
    'med' => quantile($dt, 0.5),
    'q3' => quantile($dt, 0.75),
  ];
}