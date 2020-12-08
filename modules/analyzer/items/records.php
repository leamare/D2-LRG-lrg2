<?php 

echo "[S] Requested data for ITEM RECORDS";

$_items = implode(
  ',',
  array_unique(
    array_merge(
      $meta['item_categories']['medium'], 
      $meta['item_categories']['major']
    )
  )
);

$q = <<<SQL
SELECT 
  items.matchid,
  it.item_id,
  it.hero_id,
  it.tm,
  it.purchases
FROM (
  SELECT 
    item_id,
    hero_id,
    min(items.time) tm,
    SUM(1) purchases
  FROM items
  WHERE item_id in ($_items)
  GROUP BY hero_id, item_id
  HAVING purchases > 1
) it JOIN items ON it.item_id = items.item_id AND it.hero_id = items.hero_id AND it.tm = items.time
SQL;

if ($conn->multi_query($q) === TRUE);
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

$r = [];

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if ($row[4] < $purchases_h[ $row[2] ]['q3']) continue;

  if (!isset($r[ $row[1] ])) $r[ $row[1] ] = [];
  $r[ $row[1] ][ $row[2] ] = [ 
    'match' => $row[0], 
    'time' => $row[3], 
    'diff' => abs($result['items']['stats'][ $row[2] ][ $row[1] ]['median'] - $row[3])
  ];
}

$query_res->free_result();

$ar;

echo "\n";

// $result['items']['combos'] = $r;

$result['items']['records'] = wrap_data(
  $r,
  true,
  true,
  true
);