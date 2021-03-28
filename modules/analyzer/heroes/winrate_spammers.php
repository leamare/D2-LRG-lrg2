<?php 

// foreach hero in pickban
// select duration from matchlines where heroid, limit q, 1
// select winrate early, winrate late

$r = [];
echo "[S] Requested data for HEROES WR SPAMMERS.\n";

foreach ($result["pickban"] as $hid => $data) {
  if ($data['matches_picked'] == 0) continue;

  $h = [];

  // unique players
  $sql = "SELECT COUNT(DISTINCT ml.playerid) mnum FROM matchlines ml WHERE ml.heroid = $hid;";

  if ($query_res = $conn->query($sql)) {
    $row = $query_res->fetch_row();
    $h['players_all'] = (int)$row[0];
  } else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res->close();

  $sql = "SELECT COUNT(DISTINCT ml.matchid) mnum FROM matchlines ml WHERE ml.heroid = $hid GROUP BY ml.playerid HAVING mnum > 1;";

  if ($query_res = $conn->query($sql)) {
    $h['players_1plus'] = (int) $query_res->num_rows;
    $h['players_1only'] = $h['players_all'] - $h['players_1plus'];
  } else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res->close();

  if (!$h['players_1plus']) {
    $r[$hid] = [
      'players_all' => $h['players_all'],
      'players_1plus' => 0,
      'players_1only' => $h['players_all'],
      'q1matches' => 1,
      'q2matches' => 1,
      'q3matches' => 1,
      'min_matches' => 1,
      'max_matches' => 1,
      'min_wr' => (float)$data['winrate_picked'],
      'max_wr' => (float)$data['winrate_picked'],
      'q1_players' => $h['players_all'],
      'q1_matches_avg' => 1,
      'q1_wr_avg' => (float)$data['winrate_picked'],
      'q3_players' => 0,
      'q3_matches_avg' => 1,
      'q3_wr_avg' => (float)$data['winrate_picked'],
      'min_matches' => 1,
      'max_matches' => 1,
      'winrate_avg' => (float)$data['winrate_picked'],
      'avg_matches' => 1,
      'grad' => 0
    ];
    continue;
  }

  $q1 = floor($h['players_1only'] + $h['players_1plus'] * 0.25);
  $q2 = floor($h['players_1only'] + $h['players_1plus'] * 0.5);
  $q3 = floor($h['players_1only'] + $h['players_1plus'] * 0.75);

  // match numbers quantiles

  $sql = "SELECT COUNT(DISTINCT ml.matchid) mnum FROM matchlines ml
    WHERE ml.heroid = $hid GROUP BY ml.playerid ORDER BY mnum ASC LIMIT $q1, 1;";

  if ($query_res = $conn->query($sql)) {
    $row = $query_res->fetch_row();
    $h['q1matches'] = (int)$row[0];
  } else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res->close();

  $sql = "SELECT COUNT(DISTINCT ml.matchid) mnum FROM matchlines ml
    WHERE ml.heroid = $hid GROUP BY ml.playerid ORDER BY mnum ASC LIMIT $q2, 1;";

  if ($query_res = $conn->query($sql)) {
    $row = $query_res->fetch_row();
    $h['q2matches'] = (int)$row[0];
  } else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res->close();

  $sql = "SELECT COUNT(DISTINCT ml.matchid) mnum FROM matchlines ml
    WHERE ml.heroid = $hid GROUP BY ml.playerid ORDER BY mnum ASC LIMIT $q3, 1;";

  if ($query_res = $conn->query($sql)) {
    $row = $query_res->fetch_row();
    $h['q3matches'] = (int)$row[0];
  } else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res->close();

  // q1 winrate

  $sql = "SELECT SUM(1) matches, SUM(NOT m.radiantWin XOR ml.isradiant) wins FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
    WHERE ml.heroid = $hid GROUP BY ml.playerid ORDER BY matches ASC LIMIT 0, $q3;";

  if ($query_res = $conn->query($sql)) {
    $players = 0; $wins = 0; $matches = 0; $winrates = [];
    for ($row = $query_res->fetch_row(); $row != null && $row[0] <= $h['q1matches']; $row = $query_res->fetch_row()) {
      $players++;
      $matches += $row[0];
      $wins += $row[1];
      $winrates[] = $row[1]/$row[0];

      if (!isset($h['min_matches'])) $h['min_matches'] = (int)$row[0];
    }
    
    $h['min_wr'] = count($winrates) ? round( $winrates[ 0 ], 4) : 0;

    $h['q1_players'] = $players;
    $h['q1_matches_avg'] = $players ? round( $matches/$players, 2) : 0;
    // $h['q1_wr_total'] = $wins/$matches;
    $h['q1_wr_avg'] = count($winrates) ? round( array_sum($winrates)/count($winrates), 4) : 0;
  } else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res->close();

  // q3 winrate

  $sql = "SELECT SUM(1) matches, SUM(NOT m.radiantWin XOR ml.isradiant) wins FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
    WHERE ml.heroid = $hid GROUP BY ml.playerid ORDER BY matches ASC LIMIT $q3, ".$h['players_all'].";";

  if ($query_res = $conn->query($sql)) {
    $players = 0; $wins = 0; $matches = 0; $winrates = [];
    for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
      if ($row[0] < $h['q3matches']) continue;
      $players++;
      $matches += $row[0];
      $wins += $row[1];
      $winrates[] = $row[1]/$row[0];

      $h['max_matches'] = (int)$row[0];
    }
    
    $h['max_wr'] = count($winrates) ? round($winrates[ count($winrates)-1 ], 4) : 0;
    $h['q3_players'] = $players;
    $h['q3_matches_avg'] = round($matches/$players, 2);
    // $h['q3_wr_total'] = $wins/$matches;
    $h['q3_wr_avg'] = round( array_sum($winrates)/count($winrates), 4);
  } else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res->close();

  // average winrate
  $h['winrate_avg'] = (float)$data['winrate_picked'];
  $h['avg_matches'] = round($data['matches_picked']/$h['players_all'], 2);

  // gradient
  if ($h['q3_matches_avg']-$h['q1_matches_avg'])
    $h['grad'] = round( ($h['q3_wr_avg']-$h['q1_wr_avg'])/($h['q3_matches_avg']-$h['q1_matches_avg']) , 4 );
  else 
    $h['grad'] = $h['q3_wr_avg']-$h['q1_wr_avg'];

  $r[$hid] = $h;
}

$result["hero_winrate_spammers"] = wrap_data(
  $r,
  true,
  true,
  true
);