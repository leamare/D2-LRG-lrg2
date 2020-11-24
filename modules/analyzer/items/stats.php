<?php 

echo "[S] Requested data for ITEMS STATS";

$r = [];

$q = <<<SQL
SELECT 
	items.item_id item,
	items.category_id category,
	SUM(1) purchases,
	SUM(NOT matches.radiantWin XOR matchlines.isRadiant) wins,
	min(time) min_time,
	max(time) max_time,
	CAST( SUM(time)/SUM(1) AS SIGNED ) avg_time
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
GROUP BY items.item_id

SQL;

$r['total'] = [];

if ($conn->multi_query($q) === TRUE);
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

for ($row = $query_res->fetch_assoc(); $row != null; $row = $query_res->fetch_assoc()) {
  $item = $row['item'];
  unset($row['item']);
  $r['total'][$item] = $row;
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
	CAST( SUM(time)/SUM(1) AS SIGNED ) avg_time
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
GROUP BY items.item_id, items.hero_id

SQL;

if ($conn->multi_query($q) === TRUE);
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

for ($row = $query_res->fetch_assoc(); $row != null; $row = $query_res->fetch_assoc()) {
  $hid = $row['hero'];
  unset($row['hero']);
  $item = $row['item'];
  unset($row['item']);
  if (!isset($r[$hid])) $r[$hid] = [];
  $r[$hid][$item] = $row;
}

$query_res->free_result();

// MEDIANS AND SHIT

$q = "SELECT items.item_id, items.hero_id, min(items.time), (NOT matches.radiantWin XOR matchlines.isRadiant) win 
  FROM items 
  JOIN matchlines ON matchlines.matchid = items.matchid AND matchlines.heroid = items.hero_id 
  JOIN matches ON matches.matchid = matchlines.matchid
  GROUP BY items.matchid, items.item_id, items.hero_id;";
$dataset = [];
$raw_dataset = [];

if ($conn->multi_query($q) === TRUE) echo ".";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if (!isset($dataset[$row[0]])) $dataset[$row[0]] = [ 'total' => [] ];
  if (!isset($dataset[$row[0]][$row[1]])) $dataset[$row[0]][$row[1]] = [];
  $dataset[$row[0]][$row[1]][] = $row[2];
  $dataset[$row[0]]['total'][] = $row[2];

  if (!isset($raw_dataset[$row[0]])) $raw_dataset[$row[0]] = [ 'total' => [] ];
  if (!isset($raw_dataset[$row[0]][$row[1]])) $raw_dataset[$row[0]][$row[1]] = [];
  $raw_dataset[$row[0]][$row[1]][] = [ 't' => $row[2], 'w' => $row[3] ];
  $raw_dataset[$row[0]]['total'][] = [ 't' => $row[2], 'w' => $row[3], 'i' => $row[1] ];
}

$query_res->free_result();

foreach ($r as $hid => $items) {
  foreach ($items as $iid => $data) {
    $sz = count($dataset[$iid][$hid]);

    if ($sz < 2) {
      $r[$hid][$iid]['std_dev'] = 0;
      $r[$hid][$iid]['q1'] = $data['avg_time'];
      $r[$hid][$iid]['q3'] = $data['avg_time'];
      $r[$hid][$iid]['median'] = $data['avg_time'];
      $r[$hid][$iid]['winrate'] = $data['wins'];
      
      $r[$hid][$iid]['prate'] = round($data['purchases']/$items_matches[$hid], 4);
      $r[$hid][$iid]['early_wr'] = $r[$hid][$iid]['winrate'];
      $r[$hid][$iid]['late_wr'] = $r[$hid][$iid]['winrate'];
      $r[$hid][$iid]['wo_wr'] = $r[$hid][$iid]['winrate'];

      continue;
    }

    $r[$hid][$iid]['std_dev'] = 0;
    $sum = 0;
    foreach ($dataset[$iid][$hid] as $v) {
      $sum += pow($v-$data['avg_time'], 2);
    }

    $r[$hid][$iid]['std_dev'] = round(sqrt( $sum/($sz-1) ), 3);
    $r[$hid][$iid]['q1'] = quantile($dataset[$iid][$hid], 0.25);
    $r[$hid][$iid]['q3'] = quantile($dataset[$iid][$hid], 0.75);
    $r[$hid][$iid]['median'] = quantile($dataset[$iid][$hid], 0.5);
    $r[$hid][$iid]['winrate'] = round($data['wins']/$data['purchases'], 4);
    $r[$hid][$iid]['prate'] = round($data['purchases']/$items_matches[$hid], 4);

    $wins_q1 = 0; $total_q1 = 0;
    $wins_q3 = 0; $total_q3 = 0;
    $q1 = $r[$hid][$iid]['q1'];//$r[$hid][$iid]['median'] - $r[$hid][$iid]['std_dev'];
    $q3 = $r[$hid][$iid]['q3'];//$r[$hid][$iid]['median'] + $r[$hid][$iid]['std_dev'];

    foreach ($raw_dataset[$iid][$hid] as $v) {
      if ($v['t'] <= $q1) {
        $total_q1++;
        $wins_q1 += $v['w'];
      }

      if ($v['t'] >= $q3) {
        $total_q3++;
        $wins_q3 += $v['w'];
      }
    }

    $total_wo = 0; $wins_wo = 0;

    foreach ($raw_dataset as $item => $lines) {
      if ($item == $iid || empty($lines[$hid])) continue;
      foreach ($lines[$hid] as $v) {
        $total_wo++;
        $wins_wo += $v['w'];
      }
    }

    $r[$hid][$iid]['early_wr'] = $total_q1 ? round($wins_q1/$total_q1, 4) : $r[$hid][$iid]['winrate'];
    $r[$hid][$iid]['late_wr'] = $total_q3 ? round($wins_q3/$total_q3, 4) : $r[$hid][$iid]['winrate'];
    $r[$hid][$iid]['wo_wr'] = $total_wo ? round($wins_wo/$total_wo, 4) : 0;
  }
}

// std_dev
// q1 q3 median
// winrate
// purchase rate

echo "\n";

//$result['items']['stats'] = $r;

$result['items']['stats'] = wrap_data(
  $r,
  true,
  true,
  true
);