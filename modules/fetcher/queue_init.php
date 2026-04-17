<?php

if (!$listen) {
  if (isset($options['U'])) {
    $sql = "SELECT matchid FROM matches;";

    if ($conn->multi_query($sql) === TRUE) echo "[S] Requested MatchIDs.\n";
    else die("[F] Unexpected problems when recording to database.\n" . $conn->error . "\n");

    $query_res = $conn->store_result();
    for ($matches = [], $row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
      $matches[] = $row[0];
    }
    $query_res->free_result();

    $parsed_matches = [];
    if (!isset($options['Q'])) {
      $sql = "SELECT matchid FROM adv_matchlines GROUP BY matchid;";

      if ($conn->multi_query($sql) === TRUE);
      else die("[F] Unexpected problems when recording to database.\n" . $conn->error . "\n");

      $query_res = $conn->store_result();
      for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
        $parsed_matches[] = $row[0];
      }
      $query_res->free_result();
    }

    $pmatches = [];
    if (isset($options['p'])) {
      $sql = "SELECT matchid FROM matchlines where playerid < 0 GROUP BY matchid;";

      if ($conn->multi_query($sql) === TRUE);
      else die("[F] Unexpected problems when recording to database.\n" . $conn->error . "\n");

      $query_res = $conn->store_result();
      for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
        $pmatches[] = $row[0];
      }
      $query_res->free_result();
    }

    $matches = array_diff($matches, $parsed_matches);
    if (isset($options['Q'])) {
      $matches = array_intersect($matches, $pmatches);
    } else {
      $matches = array_merge($matches, $pmatches);
    }
    unset($parsed_matches, $pmatches);
  } else {
    $input_cont = file_get_contents($lrg_input);
    $input_cont = str_replace("\r\n", "\n", $input_cont);
    $matches    = explode("\n", trim($input_cont));
  }
  $matches = array_unique($matches);
  echo "[ ] Total: " . count($matches) . "\n";
  echo "[ ] OpenDota cooldown: {$opendota_effective_cooldown_s} s, workers: {$fetch_workers}\n";
} else {
  $matches = [];
  echo "[ ] OpenDota cooldown: {$opendota_effective_cooldown_s} s, workers: {$fetch_workers}\n";
}
