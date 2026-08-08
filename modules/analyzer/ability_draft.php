<?php

// Early implementation of Ability Draft (game_mode 18) stats.
// Reuses the skill_builds table: in AD, `hero_id` is only the "body" hero
// (a stat-line proxy), the actually drafted kit lives in `skill_build`
// (ability ids leveled over the game) and `ultimate` (the ability the
// player chose to level like an ultimate).
//
// Packed with wrap_data() to stay consistent with the rest of the report.

const AD_ATTR_TAGS = [ 730, 5002 ];

if (!isset($result['ability_draft'])) $result['ability_draft'] = [];

echo "[ ] ABILITY DRAFT DATA - ";
resetbltime();

$q = "SELECT COUNT(DISTINCT matchid) FROM matches WHERE modeID = 18;";
$query_res = $conn->query($q);
$ad_matches_total = (int)($query_res->fetch_row()[0] ?? 0);

if (!$ad_matches_total) {
  echo "no Ability Draft matches found, skipping.\n";
  unset($result['ability_draft']);
  return;
}

$sql = <<<SQL
  SELECT sb.skill_build, sb.ultimate, m.radiantWin = ml.isRadiant AS win
  FROM skill_builds sb
  JOIN matches m ON m.matchid = sb.matchid AND m.modeID = 18
  JOIN matchlines ml ON sb.matchid = ml.matchid AND sb.playerid = ml.playerid
  WHERE ml.level > 0
SQL;

if ($conn->multi_query($sql) !== TRUE) die("[F] Unexpected problems when requesting database.\n".$conn->error."\n".$sql."\n");

$query_res = $conn->store_result();

echo ' [ '.echobltime().' ] ';

$abilities = [];
$players_total = 0;

for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  [ $skill_build, $ultimate, $win ] = $row;

  $build = json_decode($skill_build, true);
  if (!is_array($build)) continue;

  $players_total++;

  $picked = array_diff(array_unique($build), AD_ATTR_TAGS);

  foreach ($picked as $aid) {
    if (!isset($abilities[$aid])) {
      $abilities[$aid] = [ 'matches' => 0, 'wins' => 0, 'as_ultimate' => 0 ];
    }
    $abilities[$aid]['matches']++;
    $abilities[$aid]['wins'] += $win;
    if ($ultimate && $aid == $ultimate) {
      $abilities[$aid]['as_ultimate']++;
    }
  }
}

$query_res->free_result();

echo ' [ '.echobltime().' ] ';

$rows = [];
foreach ($abilities as $aid => $a) {
  $rows[$aid] = [
    'matches' => $a['matches'],
    'wins' => $a['wins'],
    'winrate' => $a['matches'] ? round($a['wins'] / $a['matches'], 4) : 0,
    'pick_rate' => $players_total ? round($a['matches'] / $players_total, 4) : 0,
    'as_ultimate' => $a['as_ultimate'],
    'ultimate_rate' => $a['matches'] ? round($a['as_ultimate'] / $a['matches'], 4) : 0,
  ];
}

echo " [ ".echobltime()." ] \n";

$result['ability_draft'] = [
  'matches' => $ad_matches_total,
  'players' => $players_total,
  'abilities' => wrap_data($rows, true, true, true),
];
