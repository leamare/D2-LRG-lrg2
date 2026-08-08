<?php
include_once("head.php");
ini_set('memory_limit', '8192M');

echo("\nConnecting to database...\n");

try {
  $conn = lrg_mysqli_connect($lrg_sql_db);
} catch (Exception $e) {
  die("[F] Error: ".$e->getMessage()."\n");
}
$conn->set_charset('utf8mb4');

if ($conn->connect_error) die("[F] Connection to SQL server failed: ".$conn->connect_error."\n");

error_log("Connected to database, starting test queries.");

$start_time = microtime(true);

// Skill builds stats for heroes
//  - skill priority
//  - firstPointAt
//  - maxedAt
//  - attributes
//  - talents
// Skills stats ability draft 

$q = <<<SQL
SELECT
  sb.hero_id,
  priority,
  maxed_at,
  COUNT(*) as cnt,
  SUM(m.radiantWin = ml.isRadiant) as wins,
  MAX(ml.level) as max_level
FROM 
  skill_builds sb JOIN matchlines ml ON sb.matchid = ml.matchid AND sb.hero_id = ml.heroid
  LEFT JOIN matches m ON ml.matchid = m.matchid
WHERE
  ml.level > 0
GROUP BY
  hero_id,
  priority, first_point_at, maxed_at, talents, attributes
SQL;

$heroes = [];
$maxlevels = [];

$maxed_at_total = [];
$first_point_at_total = [];

$attributes_total = [];

$res = $conn->query($q);

while ($row = $res->fetch_assoc()) {
  if (!isset($heroes[$row['hero_id']])) {
    $heroes[$row['hero_id']] = [];
  }

  $prio = json_decode($row['priority'], true);
  if (count($prio) < 3) {
    continue;
  }

  if (!isset($heroes[$row['hero_id']][ $row['priority'] ])) {
    $heroes[$row['hero_id']][ $row['priority'] ] = [
      'm' => 0,
      'w' => 0,
    ];
  }
  $heroes[$row['hero_id']][ $row['priority'] ]['m'] += $row['cnt'];
  $heroes[$row['hero_id']][ $row['priority'] ]['w'] += $row['wins'];
  $maxlevels[$row['priority']] = $row['max_level'];

  $maxed_at_brk = json_decode($row['maxed_at'], true);
  foreach ($maxed_at_brk as $skill => $level) {
    if (!isset($maxed_at_total[$row['hero_id']])) {
      $maxed_at_total[$row['hero_id']] = [];
    }
    if (!isset($maxed_at_total[$row['hero_id']][$skill])) {
      $maxed_at_total[$row['hero_id']][$skill] = [];
    }
    if (!isset($maxed_at_total[$row['hero_id']][$skill][$level])) {
      $maxed_at_total[$row['hero_id']][$skill][$level] = 0;
    }
    $maxed_at_total[$row['hero_id']][$skill][$level] += $row['cnt'];
  }
}

error_log("Query finished, time: " . (microtime(true) - $start_time));

echo "Maxed at total:\n";
foreach ($maxed_at_total as $hero_id => $skills) {
  echo $hero_id . ":\n";
  foreach ($skills as $skill => $levels) {
    echo "\t" . $skill . ":";
    $avg = array_sum(array_map(function($count, $level) { return $level * $count; }, $levels, array_keys($levels))) / array_sum($levels);
    echo " avg: " . $avg . ", ";

    echo "most common: " . array_search(max($levels), $levels) . "\n";
  }
}

echo "\n\n";

foreach ($heroes as $hero_id => $priorities) {
  foreach ($priorities as $priority => $count) {
    $prio = json_decode($priority, true);
    if (count($prio) <= 3) {
      foreach ($heroes[$hero_id] as $p => $c) {
        $p_d = json_decode($p, true);
        $matches_found = 0;
        foreach ($prio as $skill => $pidx) {
          if (isset($p_d[$skill]) && $p_d[$skill] == $pidx) {
            $matches_found++;
          }
        }
        if ($matches_found == count($prio)) {
          $heroes[$hero_id][$p]['m'] += $count['m'];
          $heroes[$hero_id][$p]['w'] += $count['w'];
          unset($heroes[$hero_id][$priority]);
          break;
        }
      }
    }
  }
}

echo "Priorities:\n";
foreach ($heroes as $hero_id => $priorities) {
  echo $hero_id . ":\n";
  arsort($priorities);
  $total = array_sum(array_column($priorities, 'm'));

  $others = [ 'm' => 0, 'w' => 0 ];

  foreach ($priorities as $priority => $count) {
    $ratio = $count['m'] / $total;
    if ($ratio < 0.01) {
      $others['m'] += $count['m'];
      $others['w'] += $count['w'];
      continue;
    }

    echo "\t" . $priority . ": " . $count['m'] . " (" . round($ratio * 100, 2) . "%) - wr " . round($count['w'] / $count['m'] * 100, 2) . "% " . $maxlevels[$priority] . "\n";
  }

  if ($others['m'] > 0) {
    echo "\tOthers: " . $others['m'] . " (" . round($others['m'] / $total * 100, 2) . "%) - wr " . round($others['w'] / $others['m'] * 100, 2) . "%\n";
  }
}

error_log("Processing completed, full time: " . (microtime(true) - $start_time) . " seconds");