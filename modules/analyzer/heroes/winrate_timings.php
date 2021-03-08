<?php 

// foreach hero in pickban
// select duration from matchlines where heroid, limit q, 1
// select winrate early, winrate late

$r = [];
echo "[S] Requested data for HEROES WR TIMINGS.\n";

foreach ($result["pickban"] as $hid => $data) {
  $h = [];

  $matches = (int)$data['matches_picked'];

  if (!$matches) {
    $r[$hid] = [
      'matches' => 0,
      'min_duration' => 0,
      'q1duration' => 0,
      'q2duration' => 0,
      'q3duration' => 0,
      'max_duration' => 0,
      'early_wr' => 0,
      'late_wr' => 0,
      'avg_duration' => 0,
      'std_dev' => 0,
      'winrate_avg' => 0,
      'grad' => 0
    ];
  }

  $q1 = floor($matches * 0.25);
  $q2 = floor($matches * 0.5);
  $q3 = floor($matches * 0.75);

  $h['matches'] = $matches;

  // duration quantiles

  $sql = "SELECT m.duration FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
    WHERE ml.heroid = $hid ORDER BY m.duration ASC LIMIT 1, 1;";

  if ($query_res = $conn->query($sql)) {
    $row = $query_res->fetch_row();
    $h['min_duration'] = (int)$row[0];
  } else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res->close();

  $sql = "SELECT m.duration FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
    WHERE ml.heroid = $hid ORDER BY m.duration ASC LIMIT $q1, 1;";

  if ($query_res = $conn->query($sql)) {
    $row = $query_res->fetch_row();
    $h['q1duration'] = (int)$row[0];
  } else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res->close();

  $sql = "SELECT m.duration FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
    WHERE ml.heroid = $hid ORDER BY m.duration ASC LIMIT $q2, 1;";

  if ($query_res = $conn->query($sql)) {
    $row = $query_res->fetch_row();
    $h['q2duration'] = (int)$row[0];
  } else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res->close();

  $sql = "SELECT m.duration FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
    WHERE ml.heroid = $hid ORDER BY m.duration ASC LIMIT $q3, 1;";

  if ($query_res = $conn->query($sql)) {
    $row = $query_res->fetch_row();
    $h['q3duration'] = (int)$row[0];
  } else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res->close();

  $sql = "SELECT m.duration FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
    WHERE ml.heroid = $hid ORDER BY m.duration DESC LIMIT 1, 1;";

  if ($query_res = $conn->query($sql)) {
    $row = $query_res->fetch_row();
    $h['max_duration'] = (int)$row[0];
  } else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res->close();

  // early winrate

  $sql = "SELECT SUM(1) matches, SUM(NOT m.radiantWin XOR ml.isradiant)/SUM(1) winrate FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
    WHERE ml.heroid = $hid AND m.duration <= ".$h['q1duration'].";";

  if ($query_res = $conn->query($sql)) {
    $row = $query_res->fetch_row();
    // $h['early_matches'] = (int)$row[0];
    $h['early_wr'] = (float)$row[1];
  } else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res->close();

  // late winrate

  $sql = "SELECT SUM(1) matches, SUM(NOT m.radiantWin XOR ml.isradiant)/SUM(1) winrate FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
    WHERE ml.heroid = $hid AND m.duration >= ".$h['q3duration'].";";

  if ($query_res = $conn->query($sql)) {
    $row = $query_res->fetch_row();
    // $h['late_matches'] = (int)$row[0];
    $h['late_wr'] = (float)$row[1];
  } else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res->close();

  // average duration

  $sql = "SELECT AVG(m.duration) FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
    WHERE ml.heroid = $hid;";

  if ($query_res = $conn->query($sql)) {
    $row = $query_res->fetch_row();
    $h['avg_duration'] = round( (float)$row[0] );
  } else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res->close();

  // standart deviation

  $sql = "SELECT m.duration-".$h['avg_duration']." FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
    WHERE ml.heroid = $hid;";

  if ($query_res = $conn->query($sql)) {
    $sum = 0;
    for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
      $sum += pow($row[0], 2);
    }
    $h['std_dev'] = round( sqrt( $sum/($matches-1) ) );
  } else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
  $query_res->close();

  // average winrate
  $h['winrate_avg'] = (float)$data['winrate_picked'];

  // gradient
  $min = (abs($h['q3duration'])-abs($h['q1duration']))/60;
  $h['grad'] = round( ($h['late_wr']-$h['early_wr'])/($min > 1 ? $min : 1) , 4 );

  $r[$hid] = $h;
}


$result["hero_winrate_timings"] = wrap_data(
  $r,
  true,
  true,
  true
);