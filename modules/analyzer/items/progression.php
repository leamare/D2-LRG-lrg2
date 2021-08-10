<?php 

echo "[S] Requested data for ITEMS PROGRESSION";

$r = [];

$ar = [];
$_matches = [];

// TOTAL

$sq = <<<SQL
select *, 
( CASE
	WHEN matchid = @last AND @lasthero = hero_id THEN
		CASE
			WHEN @lastmin < CAST(time/60 AS SIGNED) THEN (@ord := @ord + 1)
			ELSE @ord
		END
	ELSE (@ord := 0)
END
) AS order_num,
(@lastmin := CAST(time/60 AS SIGNED)) AS purmin,
(@last := matchid) AS lastmatchvar,
(@lasthero := hero_id) AS lastherovar
FROM items
WHERE ((NOT @hero) OR (@hero = hero_id)) AND (category_id NOT IN (2, 3, 4, 6, 7, 8, 9, 10, 11, 12, 13))

SQL;

$q = <<<SQL
set @ord := 0;
set @last := 0;
set @lasthero := 0;
set @lastmin := 0;

set @hero = 0;

SELECT 
	'total' hero,
	oi1.item_id item1,
	oi1.category_id item1_cat,
	oi2.item_id item2,
	oi2.category_id item2_cat,
	SUM(oi2.purmin - oi1.purmin)/SUM(1) min_diff,
	SUM(1) total,
	SUM( NOT ml.isRadiant XOR m.radiantWin ) wins,
	AVG(oi1.order_num) item1_order_num,
	AVG(oi2.order_num) item2_order_num
FROM
(	$sq ) oi1 JOIN 
( $sq ) oi2 ON oi1.matchid = oi2.matchid AND oi1.hero_id = oi2.hero_id AND oi1.order_num = oi2.order_num - 1
JOIN matchlines ml ON oi1.matchid = ml.matchid AND oi1.hero_id = ml.heroid 
JOIN matches m ON m.matchid = ml.matchid 

GROUP BY item1, item2 
HAVING total > 1
ORDER BY total DESC;

SQL;

if ($conn->multi_query($q) === TRUE);
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

while (is_bool($query_res)) {
	$conn->next_result();
	$query_res = $conn->store_result();
}

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if (!isset($ar[ $row[0] ])) {
		$ar[ $row[0] ] = [];
		$_matches[ $row[0] ] = [];
	}

  $ar[ $row[0] ][] = [
		'item1' => (int)$row[1],
		'item1_cat' => (int)$row[2],
		'item2' => (int)$row[3],
		'item2_cat' => (int)$row[4],
		'min_diff' => (float)$row[5],
		'total' => (int)$row[6],
		'wins' => (int)$row[7],
		'winrate' => round($row[7]/$row[6], 4),
		'avgord1' => round($row[8], 1),
		'avgord2' => round($row[9], 1)
	];
	$_matches[ $row[0] ][] = (int)$row[6];
}

$query_res->free_result();

// HEROES

$q = <<<SQL
set @ord := 0;
set @last := 0;
set @lasthero := 0;
set @lastmin := 0;

set @hero = 0;

SELECT 
	oi1.hero_id hero,
	oi1.item_id item1,
	oi1.category_id item1_cat,
	oi2.item_id item2,
	oi2.category_id item2_cat,
	SUM(oi2.purmin - oi1.purmin)/SUM(1) min_diff,
	SUM(1) total,
	SUM( NOT ml.isRadiant XOR m.radiantWin ) wins,
	AVG(oi1.order_num) item1_order_num,
	AVG(oi2.order_num) item2_order_num
FROM
(	$sq ) oi1 JOIN 
( $sq ) oi2 ON oi1.matchid = oi2.matchid AND oi1.hero_id = oi2.hero_id AND oi1.order_num = oi2.order_num - 1
JOIN matchlines ml ON oi1.matchid = ml.matchid AND oi1.hero_id = ml.heroid 
JOIN matches m ON m.matchid = ml.matchid 

GROUP BY item1, item2, hero 
HAVING total > 1
ORDER BY total DESC;

SQL;

if ($conn->multi_query($q) === TRUE);
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

while (is_bool($query_res)) {
	$conn->next_result();
	$query_res = $conn->store_result();
}

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if (!isset($ar[ $row[0] ])) {
		$ar[ $row[0] ] = [];
		$_matches[ $row[0] ] = [];
	}

  $ar[ $row[0] ][] = [
		'item1' => (int)$row[1],
		'item1_cat' => (int)$row[2],
		'item2' => (int)$row[3],
		'item2_cat' => (int)$row[4],
		'min_diff' => (float)$row[5],
		'total' => (int)$row[6],
		'wins' => (int)$row[7],
		'winrate' => round($row[7]/$row[6], 4),
		'avgord1' => round($row[8], 1),
		'avgord2' => round($row[9], 1)
	];
	$_matches[ $row[0] ][] = (int)$row[6];
}

$query_res->free_result();

foreach ($ar as $hero => $pairs) {
	if ($hero == 'total')
		$q1matches = quantile($_matches[ $hero ], 0.75);
	else 
		$q1matches = quantile($_matches[ $hero ], 0.375)+1;

	$ar[ $hero ] = array_filter($pairs, function($v) use ($q1matches) {
		if ($v['total'] <= $q1matches) return false;
		return true;
	});
	$ar[ $hero ] = array_values($ar[ $hero ]);
}

echo "\n";

// $result['items']['progr'] = $ar;

$result['items']['progr'] = wrap_data(
  $ar,
  true,
  true,
  true
);