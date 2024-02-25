<?php 

// 1. Query for total wins/matches

$_totals = [];

$sql = <<<SQL
  SELECT
    SUM(1),
    SUM(m.radiantWin = ml.isRadiant)
  FROM matchlines ml
    JOIN matches m ON m.matchid = ml.matchid
  WHERE ml.matchid IN (
    SELECT matchid FROM items
  );
SQL;

$query_res = $conn->query($sql);
if ($query_res === FALSE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
echo " ~PERC~ ";
// $query_res = $conn->store_result();
for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  $_totals['total'] = [
    'matches' => $row[0],
    'wins' => $row[1],
  ];
}
$query_res->free_result();

$sql = <<<SQL
  SELECT
    ml.heroid,
    COUNT(DISTINCT ml.matchid),
    SUM(m.radiantWin = ml.isRadiant)
  FROM matchlines ml
    JOIN matches m ON m.matchid = ml.matchid
  WHERE ml.matchid IN (
    SELECT matchid FROM items
  )
  GROUP BY 1;
SQL;

$query_res = $conn->query($sql);
if ($query_res === FALSE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

// $query_res = $conn->store_result();
for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  $_totals[ $row[0] ] = [
    'matches' => $row[1],
    'wins' => $row[2],
  ];
}
$query_res->free_result();

// 2. Query for total without Hero ID

$sql = <<<SQL
  SELECT 
    it.item_id,
    iit.q1_time,
    SUM(CASE WHEN it.`mintime` <= iit.q1_time THEN ml.isRadiant = m.radiantWin ELSE 0 END) q1_wr,
    SUM(CASE WHEN it.`mintime` <= iit.q1_time THEN 1 ELSE 0 END) q1_mtchs,
    iit.q2_time,
    iit.q3_time,
    SUM(CASE WHEN it.`mintime` >= iit.q3_time THEN ml.isRadiant = m.radiantWin ELSE 0 END) q3_wr,
    SUM(CASE WHEN it.`mintime` >= iit.q3_time THEN 1 ELSE 0 END) q3_mtchs,
    iit.min_time,
    iit.max_time,
    SUM(ml.isRadiant = m.radiantWin) total_wins,
    SUM(1) total_matches,
    iit.avg_time
  FROM (
      SELECT *, min(`time`) mintime
      FROM items
      GROUP BY matchid, hero_id, item_id
  ) it
  JOIN matchlines ml ON it.matchid = ml.matchid and it.hero_id = ml.heroid
  JOIN matches m on it.matchid = m.matchid 
  JOIN (
    SELECT 
      it.item_id,
      percentile_cont(it.`mintime`, 0.25) q1_time,
      percentile_cont(it.`mintime`, 0.5) q2_time,
      percentile_cont(it.`mintime`, 0.75) q3_time,
      max(it.`mintime`) max_time,
      min(it.`mintime`) min_time,
      avg(it.`mintime`) avg_time
    FROM (
      SELECT *, min(`time`) mintime
      FROM items
      GROUP BY matchid, hero_id, item_id
    ) it
    GROUP BY 1
  ) iit ON it.item_id = iit.item_id
  GROUP BY 1;
SQL;

$query_res = $conn->query($sql);
if ($query_res === FALSE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

// $query_res = $conn->store_result();
for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  $iid = $row[0];
  $hid = 'total';

  if (!isset($dataset[$iid])) $dataset[$iid] = [];
  $dataset[$iid][$hid] = [];

  $dataset[$iid][$hid]['q1'] = +$row[1];
  $dataset[$iid][$hid]['q1w'] = +$row[2];
  $dataset[$iid][$hid]['q1m'] = +$row[3];

  $dataset[$iid][$hid]['m'] = +$row[4];

  $dataset[$iid][$hid]['q3'] = +$row[5];
  $dataset[$iid][$hid]['q3w'] = +$row[6];
  $dataset[$iid][$hid]['q3m'] = +$row[7];

  $dataset[$iid][$hid]['min'] = +$row[8];
  $dataset[$iid][$hid]['max'] = +$row[9];

  $dataset[$iid][$hid]['w'] = +$row[10];

  // old PH-PI calculations were incorrect because
  // they were using Total Matches - Matches with Item
  // counting one game with the item only once
  // this fixes the issue
  $dataset[$iid][$hid]['wom'] = $_totals[$hid]['matches'] - $row[11]; // $r[$hid][$iid]['matchcount']

  if ($dataset[$iid][$hid]['wom']) {
    $dataset[$iid][$hid]['wow'] = $_totals[$hid]['wins'] - $row[10];  // $r[$hid][$iid]['wins']
  } else {
    $dataset[$iid][$hid]['wom'] = 1;
    $dataset[$iid][$hid]['wow'] = 0;
  }

  $dataset[$iid][$hid]['sz'] = +$row[11];
}
$query_res->free_result();

// 3. Query for Hero-Item pairs

$sql = <<<SQL
  SELECT 
    it.hero_id,
    it.item_id,
    iit.q1_time,
    SUM(CASE WHEN it.`mintime` <= iit.q1_time THEN ml.isRadiant = m.radiantWin ELSE 0 END) q1_wr,
    SUM(CASE WHEN it.`mintime` <= iit.q1_time THEN 1 ELSE 0 END) q1_mtchs,
    
    iit.q2_time,
    
    iit.q3_time,
    SUM(CASE WHEN it.`mintime` >= iit.q3_time THEN ml.isRadiant = m.radiantWin ELSE 0 END) q3_wr,
    SUM(CASE WHEN it.`mintime` >= iit.q3_time THEN 1 ELSE 0 END) q3_mtchs,
    iit.min_time,
    iit.max_time,
    SUM(ml.isRadiant = m.radiantWin) total_wins,
    COUNT(DISTINCT it.matchid) total_matches,
    iit.avg_time
  FROM (
      SELECT *, min(`time`) mintime
      FROM items
      GROUP BY matchid, hero_id, item_id
  ) it
  JOIN matchlines ml ON it.matchid = ml.matchid and it.hero_id = ml.heroid
  JOIN matches m on it.matchid = m.matchid 
  JOIN (
    SELECT 
      it.hero_id,
      it.item_id,
      percentile_cont(it.`mintime`, 0.25) q1_time,
      percentile_cont(it.`mintime`, 0.5) q2_time,
      percentile_cont(it.`mintime`, 0.75) q3_time,
      max(it.`mintime`) max_time,
      min(it.`mintime`) min_time,
      avg(it.`mintime`) avg_time
    FROM (
      SELECT *, min(`time`) mintime
      FROM items
      GROUP BY matchid, hero_id, item_id
    ) it
    GROUP BY 1, 2
  ) iit ON it.item_id = iit.item_id AND it.hero_id = iit.hero_id
  GROUP BY 1, 2;
SQL;

$query_res = $conn->query($sql);
if ($query_res === FALSE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

// $query_res = $conn->store_result();
for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  $iid = $row[1];
  $hid = $row[0];

  if (!isset($dataset[$iid])) $dataset[$iid] = [];
  $dataset[$iid][$hid] = [];

  $dataset[$iid][$hid]['q1'] = +$row[2];
  $dataset[$iid][$hid]['q1w'] = +$row[3];
  $dataset[$iid][$hid]['q1m'] = +$row[4];

  $dataset[$iid][$hid]['m'] = +$row[5];

  $dataset[$iid][$hid]['q3'] = +$row[6];
  $dataset[$iid][$hid]['q3w'] = +$row[7];
  $dataset[$iid][$hid]['q3m'] = +$row[8];

  $dataset[$iid][$hid]['min'] = +$row[9];
  $dataset[$iid][$hid]['max'] = +$row[10];

  $dataset[$iid][$hid]['w'] = +$row[11];

  $dataset[$iid][$hid]['wom'] = $_totals[$hid]['matches'] - $row[12];

  if ($dataset[$iid][$hid]['wom']) {
    $dataset[$iid][$hid]['wow'] = $_totals[$hid]['wins'] - $row[10];
  } else {
    $dataset[$iid][$hid]['wom'] = 1;
    $dataset[$iid][$hid]['wow'] = 0;
  }

  $dataset[$iid][$hid]['sz'] = +$row[12];
}
$query_res->free_result();

echo " Query OK ~ ";