<?php

$result['items'] = [];

// number of items matches

$q = "SELECT COUNT(DISTINCT matchid) FROM items;";
$items_matches = [];

if ($conn->multi_query($q) === TRUE);
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

$row = $query_res->fetch_row();
$items_matches['total'] = ( $row[0] ?? 0 ) * 10;

$query_res->free_result();

$q = "SELECT hero_id, COUNT(DISTINCT matchid) FROM items GROUP BY hero_id;";

if ($conn->multi_query($q) === TRUE);
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  $items_matches[$row[0]] = ( $row[1] ?? 0 );
}

$query_res->free_result();

require_once("modules/analyzer/items/stats.php");
require_once("modules/analyzer/items/combos.php"); //+ limiters
// require_once("modules/analyzer/items/counters.php"); + limiters
require_once("modules/analyzer/items/progression.php"); //+ limiters
// require_once("modules/analyzer/items/hero_counters.php"); + limiters
// require_once("modules/analyzer/items/records.php");

$result['items']['stats'] = wrap_data(
  $result['items']['stats'],
  true,
  true,
  true
);