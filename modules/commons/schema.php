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
  'skill_build_attr' => false,
];

echo "[ ] Getting tables\n";

$sql = "SHOW TABLES;";
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
  $sql = "DESCRIBE draft;";
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