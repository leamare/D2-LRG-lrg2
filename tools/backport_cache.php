<?php

require_once('head.php');
include_once("modules/commons/utf8ize.php");
$conn = lrg_mysqli_connect($lrg_sql_db);

if ($conn->connect_error) die("[F] Connection to SQL server failed: ".$conn->connect_error."\n");

$conn->set_charset("utf8");

include_once("modules/commons/schema.php");

$skip = isset($options['s']);
$normturbo = $lg_settings['main']['normalize_turbo'] ?? true;

$last = function ($v) { return is_array($v) ? end($v) : $v; };
$c = isset($options['c']) ? $last($options['c']) : null;
if (isset($options['M'])) {
  $matchlist_file = $last($options['M']);
  $cache_dir = $c ?? 'cache';
} elseif (is_string($c) && is_file($c)) {
  $matchlist_file = $c;
  $cache_dir = 'cache';
} else {
  $matchlist_file = null;
  $cache_dir = $c ?? 'cache';
}
if ($cache_dir === 'NULL') {
  $cache_dir = '';
}
if ($cache_dir !== '' && !is_dir($cache_dir) && !mkdir($cache_dir, 0775, true) && !is_dir($cache_dir)) {
  die("[F] Could not create cache directory: $cache_dir\n");
}
$cache_file = function ($m) use ($cache_dir) {
  return $cache_dir === '' ? "$m.lrgcache.json" : rtrim($cache_dir, "/\\") . "/$m.lrgcache.json";
};

if ($matchlist_file !== null) {
  $matches = explode("\n", file_get_contents($matchlist_file));
} else {
  if(isset($options['T'])) {
    $endt = isset($options['e']) ? $options['e'] : 0;
    $tp = strtotime($options['T'], 0);

    if (!$endt) {
      $sql = "select max(start_date) from matches;";

      if ($conn->multi_query($sql) !== TRUE) die("[F] Unexpected problems when recording to database.\n".$conn->error."\n");

      $query_res = $conn->store_result();
      $row = $query_res->fetch_row();
      if (!$row) $endt = time();
      else $endt = (int)$row[0];
      $query_res->free_result();
    }

    $sql = "SELECT matchid FROM matches WHERE start_date >= ".($endt-$tp)." AND start_date <= $endt".";";
  } else {
    $sql = "SELECT matchid FROM matches;";
  }

  if ($conn->multi_query($sql) === TRUE) echo "[S] Requested MatchIDs.\n";
  else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n");

  $query_res = $conn->store_result();
  for ($matches = [], $row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    $matches[] = $row[0];
  }
  $query_res->free_result();
}

$sz = sizeof($matches);

for ($i = 0; $i < $sz; $i++) {
  $m = $matches[$i];
  if (empty($m) || $m[0] === '#' || strpos($m, '[ ]') !== false) continue;

  $outfile = $cache_file($m);
  if ($skip && file_exists($outfile)) {
    echo "[ ] ($i/$sz) $outfile exists, skipping\n";
    continue;
  }

  $match = [];

  $q = "select * from matches where matchid = $m;";
  $r = instaquery($conn, $q);
  if (empty($r)) continue;
  if ($normturbo && $r[0]['modeID'] == 23) {
    $r[0]['duration'] /= 2;
  }

  $match['matches'] = $r[0];

  $q = "select * from matchlines where matchid = $m;";
  $match['matchlines'] = instaquery($conn, $q);
  if ($normturbo && $match['matches']['modeID'] == 23) {
    foreach ($match['matchlines'] as $i => $line) {
      $match['matchlines'][$i]['gpm'] *= 2;
      $match['matchlines'][$i]['xpm'] *= 2;
      $match['matchlines'][$i]['lastHits'] /= 2;
      $match['matchlines'][$i]['denies'] /= 2;
    }
  }

  $q = "select * from adv_matchlines where matchid = $m;";
  $match['adv_matchlines'] = instaquery($conn, $q);
  if ($normturbo && $match['matches']['modeID'] == 23) {
    foreach ($match['adv_matchlines'] as $i => $line) {
      $match['adv_matchlines'][$i]['lh_at10'] /= 2;
      $match['adv_matchlines'][$i]['wards_destroyed'] /= 2;
      $match['adv_matchlines'][$i]['wards'] /= 2;
      $match['adv_matchlines'][$i]['sentries'] /= 2;
      $match['adv_matchlines'][$i]['stacks'] /= 2;
    }
  }

  $q = "select * from draft where matchid = $m;";
  $match['draft'] = instaquery($conn, $q);

  $q = "select players.playerID, players.nickname from players 
    join matchlines on matchlines.playerid = players.playerID 
    where matchlines.matchid = $m;";
  $match['players'] = instaquery($conn, $q);

  if ($schema['skill_builds']) {
    $q = "select * from skill_builds where matchid = $m;";
    $match['skill_builds'] = instaquery($conn, $q);
  }

  if ($schema['starting_items']) {
    $q = "select * from starting_items where matchid = $m;";
    $match['starting_items'] = instaquery($conn, $q);
  }

  if ($schema['wards']) {
    $q = "select * from wards where matchid = $m;";
    $match['wards'] = instaquery($conn, $q);
  }

  if($lg_settings['main']['teams']) {
    $q = "select * from teams_matches where matchid = $m;";
    $match['teams_matches'] = instaquery($conn, $q);

    $teams = [];
    foreach ($match['teams_matches'] as $tm) {
      $teams[] = $tm['teamid'];
    }

    if (!empty($teams)) {
      $q = "select * from teams where teamid in (".implode(',', $teams).");";
      $match['teams'] = instaquery($conn, $q);
    }

    if (!empty($teams)) {
      $q = "select * from teams_rosters where teamid in (".implode(',', $teams).");";
      $match['teams_rosters'] = instaquery($conn, $q);
    }
  }

  if (($schema['leagues'] ?? false) && !empty($match['matches']['leagueID']) && (int)$match['matches']['leagueID'] > 0) {
    $lid = (int)$match['matches']['leagueID'];
    $q = "SELECT ticket_id, name, url, description FROM leagues WHERE ticket_id = $lid LIMIT 1;";
    $lr = instaquery($conn, $q);
    if (!empty($lr)) {
      $match['leagues'] = [[
        'ticket_id' => (int)$lr[0]['ticket_id'],
        'name' => $lr[0]['name'],
        'url' => $lr[0]['url'],
        'description' => $lr[0]['description'],
      ]];
    }
  }

  if($lg_settings['main']['items']) {
    $q = "select * from items where matchid = $m;";
    $match['items'] = instaquery($conn, $q);

    if ($normturbo && $match['matches']['modeID'] == 23) {
      foreach ($match['items'] as $i => $line) {
        $match['items'][$i]['time'] /= 2;
      }
    }
  }

  $out = json_encode(utf8ize($match));
  if (empty($out)) {
    echo "[E] ($i/$sz) Empty response for match $m\n";
    $sz++;
    $matches[] = $m;
    continue;
  }

  file_put_contents($outfile, $out);
  echo "[ ] ($i/$sz) backported $m to $outfile\n";
}