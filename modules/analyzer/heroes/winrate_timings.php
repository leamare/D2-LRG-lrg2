<?php 

// foreach hero in pickban
// select duration from matchlines where heroid, limit q, 1
// select winrate early, winrate late

$r = [];
echo "[S] Requested data for HEROES WR TIMINGS.\n";

$wheres = "";
if (!empty($players_interest)) {
  $wheres = " AND ml.playerid in (".implode(',', $players_interest).")";
}

/** @var mysqli|null $conn */
$db = $conn;
if (!$db) {
  die("[F] Unexpected problems when requesting database.\n");
}

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
    continue;
  }

  $q1 = floor($matches * 0.25);
  $q2 = floor($matches * 0.5);
  $q3 = floor($matches * 0.75);

  $h['matches'] = $matches;

  // duration quantiles
  $fetch_duration = function(string $order, int $offset, int $fallback = 0) use (&$db, &$hid, &$wheres): int {
    $sql = "SELECT m.duration FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
      WHERE ml.heroid = $hid $wheres ORDER BY m.duration $order LIMIT $offset, 1;";
    $query_res = $db->query($sql);
    if (!$query_res) die("[F] Unexpected problems when requesting database.\n".$db->error."\n");
    $row = $query_res->fetch_row();
    $query_res->close();
    return isset($row[0]) ? (int)$row[0] : $fallback;
  };
  $h['min_duration'] = $fetch_duration('ASC', 0, 0);
  $h['q1duration'] = $fetch_duration('ASC', $q1, $h['min_duration']);
  $h['q2duration'] = $fetch_duration('ASC', $q2, $h['q1duration']);
  $h['q3duration'] = $fetch_duration('ASC', $q3, $h['q2duration']);
  $h['max_duration'] = $fetch_duration('DESC', 0, $h['q3duration']);

  // early winrate

  $sql = "SELECT SUM(1) matches, SUM(NOT m.radiantWin XOR ml.isradiant)/SUM(1) winrate FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
    WHERE ml.heroid = $hid AND m.duration <= ".$h['q1duration']." $wheres ;";

  $query_res = $db->query($sql);
  if (!$query_res) die("[F] Unexpected problems when requesting database.\n".$db->error."\n");
  $row = $query_res->fetch_row();
  // $h['early_matches'] = (int)$row[0];
  $h['early_wr'] = isset($row[1]) ? (float)$row[1] : 0.0;
  $query_res->close();

  // late winrate

  $sql = "SELECT SUM(1) matches, SUM(NOT m.radiantWin XOR ml.isradiant)/SUM(1) winrate FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
    WHERE ml.heroid = $hid AND m.duration >= ".$h['q3duration']." $wheres ;";

  $query_res = $db->query($sql);
  if (!$query_res) die("[F] Unexpected problems when requesting database.\n".$db->error."\n");
  $row = $query_res->fetch_row();
  // $h['late_matches'] = (int)$row[0];
  $h['late_wr'] = isset($row[1]) ? (float)$row[1] : 0.0;
  $query_res->close();

  // average duration

  $sql = "SELECT AVG(m.duration) FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
    WHERE ml.heroid = $hid $wheres ;";

  $query_res = $db->query($sql);
  if (!$query_res) die("[F] Unexpected problems when requesting database.\n".$db->error."\n");
  $row = $query_res->fetch_row();
  $h['avg_duration'] = round( (float)$row[0] );
  $query_res->close();

  // standart deviation

  $sql = "SELECT m.duration-".$h['avg_duration']." FROM matchlines ml JOIN matches m ON m.matchid = ml.matchid
    WHERE ml.heroid = $hid $wheres ;";

  $query_res = $db->query($sql);
  if (!$query_res) die("[F] Unexpected problems when requesting database.\n".$db->error."\n");
  $sum = 0;
  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    $sum += pow($row[0], 2);
  }
  $h['std_dev'] = $matches == 1 ? 0 : round( sqrt( $sum/($matches-1) ) );
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