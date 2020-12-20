<?php 

echo "[S] Requested data for ITEMS STATS";

$r = [];
$purchases_i = [];
$purchases_h = [ 'total' => [] ];

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

// MEDIANS AND SHIT

// separating timings/wins runs to reduce memory consumption

// first run: get all the timings, generate q1/q3/medians
$q = "SELECT items.item_id, items.hero_id, min(items.time), (NOT matches.radiantWin XOR matchlines.isRadiant) win 
  FROM items 
  JOIN matchlines ON matchlines.matchid = items.matchid AND matchlines.heroid = items.hero_id 
  JOIN matches ON matches.matchid = matchlines.matchid
  GROUP BY items.matchid, items.item_id, items.hero_id;";
$dataset = [];

if ($conn->multi_query($q) === TRUE) echo ".";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if (!isset($dataset[$row[0]])) $dataset[$row[0]] = [ 'total' => [] ];
  if (!isset($dataset[$row[0]][$row[1]])) $dataset[$row[0]][$row[1]] = [];
  $dataset[$row[0]][$row[1]][] = $row[2];
  $dataset[$row[0]]['total'][] = $row[2];
}

$query_res->free_result();

foreach ($dataset as $i => $dti) {
  foreach ($dti as $j => $dt) {
    $dataset[$i][$j] = [
      'q1' => quantile($dt, 0.25),
      'm' => quantile($dt, 0.5),
      'q3' => quantile($dt, 0.75)
    ];
    $dataset[$i][$j]['sz'] = sizeof($dt);
    $dataset[$i][$j]['sum'] = 0;
    foreach ($dt as $t) {
      $dataset[$i][$j]['sum'] += pow($t-$r[$j][$i]['avg_time'], 2);
    }
  }
}

// second run: wins timings
$raw_dataset = [];
$rd_head = [ 
  'q1t' => 0,
  'q1w' => 0,
  'q3t' => 0,
  'q3w' => 0,
  'w' => 0,
  't' => 0
];

if ($conn->multi_query($q) === TRUE) echo ".";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if (!isset($raw_dataset[$row[0]])) $raw_dataset[$row[0]] = [ 
    'total' => $rd_head
  ];
  if (!isset($raw_dataset[$row[0]][$row[1]])) $raw_dataset[$row[0]][$row[1]] = $rd_head;

  if ($row[2] <= $dataset[$row[0]][$row[1]]['q1']) {
    $raw_dataset[$row[0]][$row[1]]['q1t']++;
    $raw_dataset[$row[0]][$row[1]]['q1w'] += $row[3];
  }

  if ($row[2] <= $dataset[$row[0]]['total']['q1']) {
    $raw_dataset[$row[0]]['total']['q1t']++;
    $raw_dataset[$row[0]]['total']['q1w'] += $row[3];
  }

  if ($row[2] >= $dataset[$row[0]][$row[1]]['q3']) {
    $raw_dataset[$row[0]][$row[1]]['q3t']++;
    $raw_dataset[$row[0]][$row[1]]['q3w'] += $row[3];
  }

  if ($row[2] >= $dataset[$row[0]]['total']['q3']) {
    $raw_dataset[$row[0]]['total']['q3t']++;
    $raw_dataset[$row[0]]['total']['q3w'] += $row[3];
  }

  $raw_dataset[$row[0]][$row[1]]['w'] += $row[3];
  $raw_dataset[$row[0]][$row[1]]['t'] += 1;

  $raw_dataset[$row[0]]['total']['w'] += $row[3];
  $raw_dataset[$row[0]]['total']['t'] += 1;

  // //$raw_dataset[$row[0]][$row[1]][] = [ 't' => $row[2], 'w' => $row[3] ];
  // $raw_dataset[$row[0]][$row[1]][] = [ $row[2], $row[3] ];
  // //$raw_dataset[$row[0]]['total'][] = [ 't' => $row[2], 'w' => $row[3], 'i' => $row[1] ];
  // $raw_dataset[$row[0]]['total'][] = [ $row[2], $row[3] ];
}

$query_res->free_result();

foreach ($r as $hid => $items) {
  foreach ($items as $iid => $data) {
    $sz = $dataset[$iid][$hid]['sz'];

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
      $r[$hid][$iid]['grad'] = 0;

      continue;
    }

    $r[$hid][$iid]['std_dev'] = 0;
    // $sum = 0;
    // foreach ($dataset[$iid][$hid] as $v) {
    //   $sum += pow($v-$data['avg_time'], 2);
    // }

    $r[$hid][$iid]['std_dev'] = round(sqrt( $dataset[$iid][$hid]['sum']/($sz-1) ), 3);
    $r[$hid][$iid]['q1'] = $dataset[$iid][$hid]['q1'];
    $r[$hid][$iid]['q3'] = $dataset[$iid][$hid]['q3'];
    $r[$hid][$iid]['median'] = $dataset[$iid][$hid]['m'];
    $r[$hid][$iid]['winrate'] = round($data['wins']/$data['purchases'], 4);
    $r[$hid][$iid]['prate'] = round($data['purchases']/$items_matches[$hid], 4);

    $wins_q1 = 0; $total_q1 = 0;
    $wins_q3 = 0; $total_q3 = 0;
    $q1 = $r[$hid][$iid]['q1'];//$r[$hid][$iid]['median'] - $r[$hid][$iid]['std_dev'];
    $q3 = $r[$hid][$iid]['q3'];//$r[$hid][$iid]['median'] + $r[$hid][$iid]['std_dev'];

    $total_q1 = $raw_dataset[$iid][$hid]['q1t'];
    $wins_q1 = $raw_dataset[$iid][$hid]['q1w'];
    $total_q3 = $raw_dataset[$iid][$hid]['q3t'];
    $wins_q3 = $raw_dataset[$iid][$hid]['q3w'];
    // foreach ($raw_dataset[$iid][$hid] as $v) {
    //   if ($v[0] <= $q1) {
    //     $total_q1++;
    //     $wins_q1 += $v[1];
    //   }

    //   if ($v[0] >= $q3) {
    //     $total_q3++;
    //     $wins_q3 += $v[1];
    //   }
    // }

    $total_wo = 0; $wins_wo = 0;

    foreach ($raw_dataset as $item => $lines) {
      if ($item == $iid || empty($lines[$hid])) continue;
      $total_wo += $lines[$hid]['t'];
      $wins_wo += $lines[$hid]['w'];
    }

    $r[$hid][$iid]['early_wr'] = $total_q1 ? round($wins_q1/$total_q1, 4) : $r[$hid][$iid]['winrate'];
    $r[$hid][$iid]['late_wr'] = $total_q3 ? round($wins_q3/$total_q3, 4) : $r[$hid][$iid]['winrate'];
    $r[$hid][$iid]['wo_wr'] = $total_wo ? round($wins_wo/$total_wo, 4) : 0;

    if ($sz > $purchases_h[$hid]['med'] && ($q3-$q1)) {
      $min = (abs($q3)-abs($q1))/60;
      $r[$hid][$iid]['grad'] = round( ($r[$hid][$iid]['late_wr']-$r[$hid][$iid]['early_wr'])/($min > 1 ? $min : 1) , 4 );
    } else 
    $r[$hid][$iid]['grad'] = 0;
  }
}

unset($dataset);
unset($raw_dataset);

// std_dev
// q1 q3 median
// winrate
// purchase rate

echo "\n";

$result['items']['stats'] = $r;
$result['items']['pi'] = $purchases_i;
$result['items']['ph'] = $purchases_h;