<?php

$enchantments_iids = [];
$enchantments_cats = [];
$min_enchantment_tier = 0;

foreach (array_keys($meta['item_categories']) as $i => $category_name) {
  if (strpos($category_name, 'enhancement_tier_') === 0) {
    $enchantments_cats[] = $i;
    foreach ($meta['item_categories'][ $category_name ] as $item_id) {
      if (isset($result['items']['stats']['total'][$item_id])) continue;
      $enchantments_iids[] = $item_id;
    }
  }
}

if (empty($enchantments_iids)) {
  echo "[S] No enchantment items found\n";
  return;
}

$min_enchantment_tier = min($enchantments_cats);

$r = [];

$sql = "SELECT
  i.item_id, i.category_id, COUNT(i.matchid) as matches, SUM(matches.radiantWin = matchlines.isRadiant) as wins
  FROM items i JOIN matchlines ON i.matchid = matchlines.matchid AND i.hero_id = matchlines.heroid
    JOIN matches ON i.matchid = matches.matchid
    WHERE i.item_id IN (".implode(',', $enchantments_iids).") AND i.category_id IN (".implode(',', $enchantments_cats).")
    GROUP BY i.item_id, i.category_id
    ORDER BY i.category_id ASC, matches DESC, i.item_id ASC;";

$query_res = $conn->query($sql);
if ($query_res === FALSE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
else echo "[S] Requested data for ITEMS ENCHANTMENTS - ~ENCH~ ";

$hero_id = 'total';
for ($row = $query_res->fetch_assoc(); $row != null; $row = $query_res->fetch_assoc()) {
  if (!isset($r[$hero_id])) $r[$hero_id] = [ 0 => [] ];
  if (!isset($r[$hero_id][$row['category_id']])) $r[$hero_id][$row['category_id']] = [];
  $r[$hero_id][$row['category_id']][$row['item_id']] = [
    'matches' => $row['matches'],
    'wins' => $row['wins'],
  ];
}

$query_res->free_result();

$sql = "SELECT
  i.item_id, i.hero_id, i.category_id, COUNT(i.matchid) as matches, SUM(matches.radiantWin = matchlines.isRadiant) as wins
  FROM items i JOIN matchlines ON i.matchid = matchlines.matchid AND i.hero_id = matchlines.heroid
    JOIN matches ON i.matchid = matches.matchid
    WHERE i.item_id IN (".implode(',', $enchantments_iids).") AND i.category_id IN (".implode(',', $enchantments_cats).")
    GROUP BY i.item_id, i.hero_id, i.category_id
    ORDER BY i.category_id ASC, matches DESC, i.item_id ASC, i.hero_id ASC;";

$query_res = $conn->query($sql);
if ($query_res === FALSE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
else echo " -HERO~ ";

for ($row = $query_res->fetch_assoc(); $row != null; $row = $query_res->fetch_assoc()) {
  if (!isset($r[$row['hero_id']])) $r[$row['hero_id']] = [ 0 => [] ];
  if (!isset($r[$row['hero_id']][$row['category_id']])) $r[$row['hero_id']][$row['category_id']] = [];
  $r[$row['hero_id']][$row['category_id']][$row['item_id']] = [
    'matches' => $row['matches'],
    'wins' => $row['wins'],
  ];
}

$query_res->free_result();

foreach ($r as $hero_id => $categories) {
  foreach ($categories as $category_id => $items) {
    $wins_total = 0;
    $matches_total = 0;
    foreach ($items as $item_id => $data) {
      $wins_total += $data['wins'];
      $matches_total += $data['matches'];
    }
    foreach ($items as $item_id => $data) {
      $r[$hero_id][$category_id][$item_id]['wr'] = round($data['wins'] / $data['matches'], 4);
      $r[$hero_id][$category_id][$item_id]['matches_wo'] = $matches_total - $data['matches'];
      if ($matches_total == $data['matches']) $r[$hero_id][$category_id][$item_id]['wr_wo'] = 0;
      else {
        $r[$hero_id][$category_id][$item_id]['wr_wo'] = round(($wins_total - $data['wins']) / ($matches_total - $data['matches']), 4);
      }
    }
  }
}

// totals

$sql = "SELECT
  item_id, hero_id,
  COUNT(*) as matches,
  SUM(won) as wins
  FROM (
    SELECT DISTINCT
      i.item_id, i.hero_id, i.matchid,
      (matches.radiantWin = matchlines.isRadiant) as won
    FROM items i 
    JOIN matchlines ON i.matchid = matchlines.matchid AND i.hero_id = matchlines.heroid
    JOIN matches ON i.matchid = matches.matchid
    WHERE i.item_id IN (".implode(',', $enchantments_iids).") AND i.category_id IN (".implode(',', $enchantments_cats).")
  ) as distinct_units
  GROUP BY item_id, hero_id
  ORDER BY matches DESC, item_id ASC, hero_id ASC;";

$query_res = $conn->query($sql);
if ($query_res === FALSE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
else echo " -TOTAL~ ";

$total_data = [];
for ($row = $query_res->fetch_assoc(); $row != null; $row = $query_res->fetch_assoc()) {
  $item_id = $row['item_id'];
  $hero_id = $row['hero_id'];
  
  if (!isset($total_data[$hero_id])) {
    $total_data[$hero_id] = [];
  }
  
  $total_data[$hero_id][$item_id] = [
    'matches' => (int)$row['matches'],
    'wins' => (int)$row['wins'],
  ];
}

$query_res->free_result();

$sql = "SELECT
  item_id,
  COUNT(*) as matches,
  SUM(won) as wins
  FROM (
    SELECT DISTINCT
      i.item_id, i.hero_id, i.matchid,
      (matches.radiantWin = matchlines.isRadiant) as won
    FROM items i 
    JOIN matchlines ON i.matchid = matchlines.matchid AND i.hero_id = matchlines.heroid
    JOIN matches ON i.matchid = matches.matchid
    WHERE i.item_id IN (".implode(',', $enchantments_iids).") AND i.category_id IN (".implode(',', $enchantments_cats).")
  ) as distinct_units
  GROUP BY item_id
  ORDER BY matches DESC, item_id ASC;";

$query_res = $conn->query($sql);
if ($query_res === FALSE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$total_data['total'] = [];
for ($row = $query_res->fetch_assoc(); $row != null; $row = $query_res->fetch_assoc()) {
  $item_id = $row['item_id'];
  
  $total_data['total'][$item_id] = [
    'matches' => (int)$row['matches'],
    'wins' => (int)$row['wins'],
  ];
}

$query_res->free_result();

foreach ($total_data as $hero_id => $items) {
  if (!isset($r[$hero_id])) {
    $r[$hero_id] = [];
  }
  $r[$hero_id][0] = [];
  
  foreach ($items as $item_id => $data) {
    $r[$hero_id][0][$item_id] = [
      'matches' => $data['matches'],
      'wins' => $data['wins'],
    ];
  }
  
  if (!empty($r[$hero_id][0])) {
    $wins_total = 0;
    $matches_total = 0;
    foreach ($r[$hero_id][0] as $item_id => $data) {
      $wins_total += $data['wins'];
      $matches_total += $data['matches'];
    }
    
    foreach ($r[$hero_id][0] as $item_id => $data) {
      $r[$hero_id][0][$item_id]['wr'] = round($data['wins'] / $data['matches'], 4);
      $r[$hero_id][0][$item_id]['matches_wo'] = $matches_total - $data['matches'];
      if ($matches_total == $data['matches']) {
        $r[$hero_id][0][$item_id]['wr_wo'] = 0;
      } else {
        $r[$hero_id][0][$item_id]['wr_wo'] = round(($wins_total - $data['wins']) / ($matches_total - $data['matches']), 4);
      }
    }
  }
}

echo "\n";

if (empty($r) || empty($r['total']) || empty($r['total'][0])) {
  echo "[S] No enchantment data found\n";
  return;
}

// $result['items']['enchantments'] = wrap_data(
//   $r,
//   true,
//   true,
//   true
// );

$result['items']['enchantments'] = $r;