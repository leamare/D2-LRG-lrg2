<?php

require_once('head.php');
include_once("modules/commons/utf8ize.php");
include_once("modules/commons/parallel_workers.php");
$conn = lrg_mysqli_connect($lrg_sql_db);

if ($conn->connect_error) die("[F] Connection to SQL server failed: ".$conn->connect_error."\n");

$conn->set_charset("utf8");

include_once("modules/commons/schema.php");

$skip = isset($options['s']);
$normturbo = $lg_settings['main']['normalize_turbo'] ?? true;
$workers = max(1, (int)($options['j'] ?? 1));

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
  $matches = explode("\n", (string)file_get_contents($matchlist_file));
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

$matches = array_values(array_filter(array_map('trim', $matches), 'strlen'));
$matches = array_values(array_filter($matches, function ($mid) {
  $s = (string)$mid;
  return $s !== '' && $s[0] !== '#' && strpos($s, '[ ]') === false && ctype_digit($s);
}));
$matches = array_map('intval', $matches);
$matches = array_values(array_filter($matches, function ($mid) {
  return $mid > 0;
}));

$sz = sizeof($matches);
$ctx = lrg_parallel_init_context();

$process_match = function (int $m, mysqli $connLocal) use ($cache_file, $skip, $normturbo, $schema, $lg_settings, $sz, &$ctx) {
  $seq = lrg_parallel_alloc_seq($ctx);

  $outfile = $cache_file($m);
  if ($skip && file_exists($outfile)) {
    lrg_parallel_log($ctx, "[ ] ($seq/$sz) $outfile exists, skipping\n");
    return;
  }

  $match = [];

  $q = "select * from matches where matchid = $m;";
  $r = instaquery($connLocal, $q);
  if (empty($r)) return;
  if ($normturbo && $r[0]['modeID'] == 23) {
    $r[0]['duration'] /= 2;
  }

  $match['matches'] = $r[0];

  $q = "select * from matchlines where matchid = $m;";
  $match['matchlines'] = instaquery($connLocal, $q);
  if ($normturbo && $match['matches']['modeID'] == 23) {
    foreach ($match['matchlines'] as $i => $line) {
      $match['matchlines'][$i]['gpm'] *= 2;
      $match['matchlines'][$i]['xpm'] *= 2;
      $match['matchlines'][$i]['lastHits'] /= 2;
      $match['matchlines'][$i]['denies'] /= 2;
    }
  }

  $q = "select * from adv_matchlines where matchid = $m;";
  $match['adv_matchlines'] = instaquery($connLocal, $q);
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
  $match['draft'] = instaquery($connLocal, $q);

  $q = "select players.playerID, players.nickname from players 
    join matchlines on matchlines.playerid = players.playerID 
    where matchlines.matchid = $m;";
  $match['players'] = instaquery($connLocal, $q);

  if ($schema['skill_builds']) {
    $q = "select * from skill_builds where matchid = $m;";
    $match['skill_builds'] = instaquery($connLocal, $q);
  }

  if ($schema['starting_items']) {
    $q = "select * from starting_items where matchid = $m;";
    $match['starting_items'] = instaquery($connLocal, $q);
  }

  if ($schema['wards']) {
    $q = "select * from wards where matchid = $m;";
    $match['wards'] = instaquery($connLocal, $q);
  }

  if($lg_settings['main']['teams']) {
    $q = "select * from teams_matches where matchid = $m;";
    $match['teams_matches'] = instaquery($connLocal, $q);

    $teams = [];
    foreach ($match['teams_matches'] as $tm) {
      $teams[] = $tm['teamid'];
    }

    if (!empty($teams)) {
      $q = "select * from teams where teamid in (".implode(',', $teams).");";
      $match['teams'] = instaquery($connLocal, $q);
    }

    if (!empty($teams)) {
      $q = "select * from teams_rosters where teamid in (".implode(',', $teams).");";
      $match['teams_rosters'] = instaquery($connLocal, $q);
    }
  }

  if (($schema['leagues'] ?? false) && !empty($match['matches']['leagueID']) && (int)$match['matches']['leagueID'] > 0) {
    $lid = (int)$match['matches']['leagueID'];
    $q = "SELECT ticket_id, name, url, description FROM leagues WHERE ticket_id = $lid LIMIT 1;";
    $lr = instaquery($connLocal, $q);
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
    $match['items'] = instaquery($connLocal, $q);

    if ($normturbo && $match['matches']['modeID'] == 23) {
      foreach ($match['items'] as $i => $line) {
        $match['items'][$i]['time'] /= 2;
      }
    }
  }

  $out = json_encode(utf8ize($match));
  if (empty($out)) {
    lrg_parallel_log($ctx, "[E] ($seq/$sz) Empty response for match $m\n");
    return;
  }

  file_put_contents($outfile, $out);
  lrg_parallel_log($ctx, "[ ] ($seq/$sz) backported $m to $outfile\n");
};

$exitCode = lrg_parallel_run($matches, $workers, function ($chunk) use ($process_match, $lrg_sql_db) {
  $connLocal = lrg_mysqli_connect($lrg_sql_db);
  $connLocal->set_charset("utf8");
  foreach ($chunk as $m) {
    $process_match((int)$m, $connLocal);
  }
  $connLocal->close();
});

lrg_parallel_cleanup($ctx);
$conn->close();
if ($exitCode !== 0) {
  exit($exitCode);
}