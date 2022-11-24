<?php 

$pls = [];

foreach ($result['records'] as $record) {
  if (!$record['playerid']) continue;
  $pls[ $record['playerid'] ] = null;
}

$_recordext = unwrap_data($result['records_ext']);
foreach ($_recordext as $records) {
  foreach ($records as $record) {
    if (empty($record) || !$record['playerid']) continue;
    $pls[ $record['playerid'] ] = null;
  }
}

if (!empty($result["regions_data"])) {
  foreach ($result["regions_data"] as $reg) {
    if (empty($reg['records'])) continue;
    foreach ($reg['records'] as $record) {
      if (!$record['playerid']) continue;
      $pls[ $record['playerid'] ] = null;
    }

    $_recordext = unwrap_data($reg['records_ext']);
    foreach ($_recordext as $records) {
      foreach ($records as $record) {
        if (empty($record) || !$record['playerid']) continue;
        $pls[ $record['playerid'] ] = null;
      }
    }
  }
}

if (isset($result['milestones']['players'])) {
  foreach ($result['milestones']['players'] as $type => $list) {
    foreach ($list as $pl => $v) {
      if (!$pl) continue;
      $pls[ $pl ] = null;
    }
  }
}

if (!empty($pls)) {
  $sql = "SELECT playerid, nickname FROM players WHERE playerid IN (".implode(',', array_keys($pls)).")";

  if ($conn->multi_query($sql) === TRUE);# echo "[S] Requested data for PLAYER SUMMARY.\n";
  else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    $enc = mb_detect_encoding($row[1], "auto");
    $pls[$row[0]] = $row[1];
  }

  $query_res->free_result();
}

$result['players_unset_nm'] = $pls;