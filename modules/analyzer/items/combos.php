<?php 

echo "[S] Requested data for ITEMS COMBOS";

$r = [];

// TOTAL

$q = <<<SQL
SELECT 
	i1.item_id item1,
  i2.item_id item2,
	SUM(1) matches,
	SUM(NOT matches.radiantWin XOR matchlines.isRadiant) wins,
	i1.time - i2.time time_diff
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
) i1 JOIN (
  SELECT
    items.matchid matchid,
    items.item_id item_id,
    items.hero_id hero_id,
    min(items.time) time,
    sum(1) purchases,
    items.playerid playerid,
    items.category_id category_id
  FROM items GROUP BY items.matchid, items.item_id, items.hero_id
) i2 ON i1.hero_id = i2.hero_id AND i1.matchid = i2.matchid AND i1.item_id < i2.item_id
	JOIN matchlines ON matchlines.matchid = i1.matchid AND matchlines.heroid = i1.hero_id
	JOIN matches ON matchlines.matchid = matches.matchid
GROUP BY i1.item_id, i2.item_id
HAVING matches > 1

SQL;

if ($conn->multi_query($q) === TRUE);
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

$ar = [];
$em = [
  '_h' => [
    'matches' => 0,
    'wins' => 0,
    'time_diff' => 0,
    'exp' => 0
  ]
];

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if (!isset($ar[ $row[0] ])) $ar[ $row[0] ] = $em;
  if (!isset($ar[ $row[1] ])) $ar[ $row[1] ] = $em;

  $expected_pair = $items_matches['total'] ? 
    $items_matches['total'] * ( $result['items']['stats']['total'][ $row[0] ]['prate'] ?? 0 ) * ( $result['items']['stats']['total'][ $row[1] ]['prate'] ?? 0 ) :
    0;

  $wr_diff = ($row[3]/$row[2]) - ( ( $result['items']['stats']['total'][ $row[0] ]['winrate'] ?? 0 ) + ( $result['items']['stats']['total'][ $row[1] ]['winrate'] ?? 0 ) ) / 2;

  $ar[ $row[0] ][ $row[1] ] = [
    'matches' => (int)$row[2],
    'wins' => (int)$row[3],
    'time_diff' => (int)$row[4],
    'exp' => round($expected_pair),
    'wr_diff' => $wr_diff
  ];
  $ar[ $row[1] ][ $row[0] ] = [];
}

$query_res->free_result();

$ar;

echo "\n";

// $result['items']['combos'] = $r;

$result['items']['combos'] = wrap_data(
  $ar,
  true,
  true,
  true
);