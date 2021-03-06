<?php
$sql = "SELECT start_date FROM matches WHERE cluster IN (".implode(",", $clusters).") ORDER BY start_date;";

if ($conn->multi_query($sql) === FALSE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
$query_res = $conn->store_result();
$start_timestamp = $query_res->fetch_row()[0] - 3600;

$query_res->free_result();

$result["regions_data"][$region]['overview']["days"] = array();
# 86400 = day = 3600*24
$sql = "SELECT start_date, ( (start_date-$start_timestamp) DIV 86400 ) day FROM matches WHERE cluster IN (".implode(",", $clusters).") GROUP BY day;";

if ($conn->multi_query($sql) === TRUE);# echo "[S] Requested data for DAYS.\n";
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

$query_res = $conn->store_result();

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  $result["regions_data"][$region]['overview']["days"][$row[1]] = array(
    "timestamp" => $row[0],
    "matches_num" => 0
  );
}

$query_res->free_result();

if ($lg_settings['ana']['matchlist']) {
  foreach($result["regions_data"][$region]['overview']["days"] as $day => &$date) {
    $date['matches'] = [];
    $sql = "SELECT matchid FROM matches WHERE cluster IN (".implode(",", $clusters).") 
      AND start_date >= ".$date['timestamp']." AND start_date < ".$date['timestamp']."+86401;";

    if ($conn->multi_query($sql) === FALSE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

    $query_res = $conn->store_result();

    for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
      $date['matches'][] = $row[0];
      $date['matches_num']++;
    }

    $query_res->free_result();
  }
} else {
  foreach($result["regions_data"][$region]['overview']["days"] as $day => &$date) {
    $sql = "SELECT COUNT(DISTINCT matchid) FROM matches WHERE cluster IN (".implode(",", $clusters).") 
      AND start_date >= ".$date['timestamp']." AND start_date < ".$date['timestamp']."+86401;";

    if ($conn->multi_query($sql) === FALSE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

    $query_res = $conn->store_result();

    for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
      $date['matches_num'] += $row[0];
    }

    $query_res->free_result();
  }
}
?>
