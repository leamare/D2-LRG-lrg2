<?php 

$schema = [
  // 'runes' => false,
  'skill_builds' => false,
  'starting_items' => false,
  'matches_opener' => false, // radiant_opener, seriesid, analysis_status
  'adv_matchlines_roles' => false, // role, lane_won, networth
  'players_fixname' => false, // name_fixed
  'draft_order' => false, // order
  'teams' => false,
  'items' => false,
  'itemslines' => false,
  'items_blocks' => false,
  'skill_build_attr' => false,
  'starting_consumables' => false,
  'wards' => false,
  'medians_available' => false,
  'percentile_available' => false,
  'variant_supported' => false,
  'variant' => false,
];

echo "[ ] Getting tables\n";

$sql = "SHOW FULL TABLES;";
if ($conn->multi_query($sql) === FALSE)
  die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
$query_res = $conn->store_result();
for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  switch($row[0]) {
    // case "runes":
    case "teams_matches":
      $schema['teams'] = true;
      break;
    case "items":
      $schema['items'] = true;
      break;
    case "itemslines":
      $schema['itemslines'] = true;
      $schema['items'] = true;
      break;
    case "items_blocks":
      $schema['items_blocks'] = true;
      $schema['items'] = true;
      break;
    case "wards":
      $schema['wards'] = true;
      break;
    case "starting_items":
    case "skill_builds":
      $schema[$row[0]] = true;
      break;
  }
}
$query_res->free_result();

$sql = "DESCRIBE matches;";
if ($conn->multi_query($sql) === FALSE)
  die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
$query_res = $conn->store_result();
for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if ($row[0] == "radiant_opener") {
    $schema['matches_opener'] = true;
    break;
  }
}
$query_res->free_result();

$sql = "DESCRIBE matchlines;";
if ($conn->multi_query($sql) === FALSE)
  die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
$query_res = $conn->store_result();
for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if ($row[0] == "variant") {
    $schema['variant_supported'] = true;
    break;
  }
}
$query_res->free_result();

if ($schema['variant_supported']) {
  $sql = "SELECT * FROM matchlines WHERE variant is not null AND variant > 0;";
  $mres = $conn->query($sql);
  if ($mres === FALSE)
    die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  if ($mres->num_rows > 0) {
    $schema['variant'] = true;
  }
}

$sql = "DESCRIBE adv_matchlines;";
if ($conn->multi_query($sql) === FALSE)
  die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
$query_res = $conn->store_result();
for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if ($row[0] == "role") {
    $schema['adv_matchlines_roles'] = true;
    break;
  }
}
$query_res->free_result();

$sql = "DESCRIBE players;";
if ($conn->multi_query($sql) === FALSE)
  die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
$query_res = $conn->store_result();
for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if ($row[0] == "name_fixed") {
    $schema['players_fixname'] = true;
    break;
  }
}
$query_res->free_result();

$sql = "DESCRIBE draft;";
if ($conn->multi_query($sql) === FALSE)
  die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
$query_res = $conn->store_result();
for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  if ($row[0] == "order") {
    $schema['draft_order'] = true;
    break;
  }
}
$query_res->free_result();

if ($schema['skill_builds']) {
  $sql = "DESCRIBE skill_builds;";
  if ($conn->multi_query($sql) === FALSE)
    die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res = $conn->store_result();
  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    if ($row[0] == "attributes") {
      $schema['skill_build_attr'] = true;
      break;
    }
  }
  $query_res->free_result();
}

if ($schema['starting_items']) {
  $sql = "DESCRIBE starting_items;";
  if ($conn->multi_query($sql) === FALSE)
    die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res = $conn->store_result();
  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    if ($row[0] == "consumables") {
      $schema['starting_consumables'] = true;
      break;
    }
  }
  $query_res->free_result();
}

$sql = "SELECT * FROM mysql.func WHERE name like \"percentile_cont\";";
$query_res = $conn->query($sql);
if ($query_res === FALSE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
if ($query_res->num_rows ?? 0) {
  $schema['percentile_available'] = true;
}
$query_res->free();

$sql = "SELECT * FROM mysql.func WHERE name like \"percentile_cont\";";
$query_res = $conn->query($sql);
if ($query_res === FALSE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
if ($query_res->num_rows ?? 0) {
  $schema['medians_available'] = true;
}
$query_res->free();
