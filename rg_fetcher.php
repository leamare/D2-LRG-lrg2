#!/bin/php
<?php
ini_set('memory_limit', '4000M');
ini_set('mysqli.reconnect', '1');

include_once("head.php");
include_once("modules/fetcher/get_patchid.php");
include_once("modules/fetcher/processRules.php");
include_once("modules/fetcher/fetch.php");
include_once("modules/commons/generate_tag.php");
include_once("modules/commons/metadata.php");

include_once("libs/simple-opendota-php/simple_opendota.php");

echo("\nInitialising...\n");

$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);
$conn->set_charset('utf8mb4');
$meta = new lrg_metadata;

$listen = isset($options['L']);

//$stratz_old_api_endpoint = 3707179408;
$stratz_timeout_retries = 2;

$force_adding = isset($options['F']);
$cache_dir = $options['c'] ?? "cache";
if($cache_dir === "NULL") $cache_dir = "";

if (!empty($options['P'])) {
  $players_list = file_get_contents($options['P']);
  $players_list = json_decode($players_list);
}
if (!empty($options['N'])) {
  $rank_limit = (int)$options['N'];
}

if (!empty($options['d'])) {
  $api_cooldown = ((int)$options['d'])*1000;
}


$update_unparsed = isset($options['u']) || isset($options['U']);

$use_stratz = isset($options['S']) || isset($options['s']);
$require_stratz = isset($options['S']);
$use_full_stratz = isset($options['Z']);
$ignore_stratz = isset($options['Q']);

$request_unparsed = isset($options['R']);

if(!empty($odapikey) && !isset($ignore_api_key))
  $opendota = new \SimpleOpenDotaPHP\odota_api(false, "", $api_cooldown ?? 0, $odapikey);
else
  $opendota = new \SimpleOpenDotaPHP\odota_api(false, "", $api_cooldown ?? 0);

if ($conn->connect_error) die("[F] Connection to SQL server failed: ".$conn->connect_error."\n");

$lrg_input  = "matchlists/".$lrg_league_tag.".list";

$rnum = 1;
$matches = [];
$failed_matches = [];

$scheduled = [];
$first_scheduled = [];

$scheduled_wait_period = (int)($options['w'] ?? 60);

$force_await = isset($options['A']);
//$force_await_retries = (int)$options['A'] ? (int)$options['A'] : 0;

$scheduled_stratz = [];

if (!$listen) {
  if (isset($options['U'])) {
    $sql = "SELECT matchid FROM matches;";
    
    if ($conn->multi_query($sql) === TRUE) echo "[S] Requested MatchIDs.\n";
    else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n");

    $query_res = $conn->store_result();
    for ($matches = [], $row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
      $matches[] = $row[0];
    }
    $query_res->free_result();
  } else {
    $input_cont = file_get_contents($lrg_input);
    $input_cont = str_replace("\r\n", "\n", $input_cont);
    $matches    = explode("\n", trim($input_cont));
  }
  $matches = array_unique($matches);
} else {
  $matches = [];
}

// workaround for matches that have teams data, but report has teams disabled
// and there's a teams table still remaining
// teams data will be recorded regardless UNLESS you manually remove the table
if (!$lg_settings['main']['teams']) {
  $sql = "SELECT COUNT(*)
  FROM information_schema.tables WHERE table_schema = '$lrg_sql_db' 
  AND table_name = 'teams_matches';";
  var_dump($sql);

  $query = $conn->query($sql);
  if (isset($query->num_rows) && $query->num_rows) {
    echo "true";
    $lg_settings['main']['teams'] = true;
  }
}

$json = "";
if ($lg_settings['main']['teams']) {
    $t_teams = [];

    $sql = "SELECT teamid, name, tag FROM teams";
    if ($conn->multi_query($sql)) {
      $res = $conn->store_result();

      while ($row = $res->fetch_row()) {
        $t_teams[$row[0]] = [
          "name"  => $row[1],
          "tag"   => $row[2],
          "added" => true
        ];
      }
      $res->free();
    }
}

$t_players = [];
$sql = "SELECT playerid, nickname FROM players;";
if ($conn->multi_query($sql) === TRUE) {
  $res = $conn->store_result();

  for ($row = $res->fetch_row(); $row != null; $row = $res->fetch_row()) {
    $t_players[(int)$row[0]] = $row[1];
  }
  $res->free_result();
} else die("Something went wrong: ".$conn->error."\n");

$stdin_flag = false;

if ($listen)
  echo "Listening...\n";

// this code is such a shitshow tbh, but I don't want to fix it, c ya in lrg-simon
while(sizeof($matches) || $listen) {
  if (!$stdin_flag && !empty($first_scheduled) && sizeof($matches) < 2 && !$force_await) {
    asort($first_scheduled);
    $first_requested = reset($first_scheduled);
    if (time() - $first_requested < $scheduled_wait_period)
      $stdin_flag = true;
  }

  if (!sizeof($matches) || $stdin_flag) {
    if (feof(STDIN)) break;
    $match_str = fgets(STDIN);
    if (!$match_str || strlen($match_str) === 0) {
      $stdin_flag = false;
    } else {
      array_unshift($matches, trim($match_str));
      $stdin_flag = false;
    }
  }

  $match = array_shift($matches);

  if (strpos($match, '::')) {
    processRules($match_raw);
  } else $match_raw = $match;

  if($request_unparsed && isset($first_scheduled[$match_raw])) {
    if (time() - $first_scheduled[$match_raw] < $scheduled_wait_period) {
      if (!sizeof($matches)) {
        if ($listen && !$force_await) {
          $stdin_flag = true;
        }
        if (!$stdin_flag) {
          echo "[W] Waiting for $scheduled_wait_period seconds\n";
          sleep($scheduled_wait_period);
        }
      }
      if (!in_array($match, $matches))
        array_push($matches, $match);
      continue;
    }
  }

  $r = fetch($match);
  if ($r === FALSE) { //|| ($force_await && $request_unparsed && $r !== TRUE)) {
    array_push($matches, $match);
  } else if ($r === NULL) {
    $failed_matches[] = $match;
  }
}

if (sizeof($failed_matches)) {
  echo "[R] Unparsed matches: \t".sizeof($failed_matches)."\n";

  echo "[_] Recording failed matches to file...\n";

  $output = implode("\n", $failed_matches);
  $filename = "tmp/failed".time();
  $f = fopen($filename, "w");
  fwrite($f, $output);
  fclose($f);

  echo "[S] Recorded failed matches to $filename\n";
}

echo "[S] Fetch complete.\n";

?>
