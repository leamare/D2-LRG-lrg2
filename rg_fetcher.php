#!/bin/php
<?php
ini_set('memory_limit', '4000M');
ini_set('mysqli.reconnect', '1');
mysqli_report(MYSQLI_REPORT_OFF); //FIXME:

include_once("head.php");
include_once("modules/fetcher/get_patchid.php");
include_once("modules/fetcher/processRules.php");
include_once("modules/fetcher/fetcher_parallel_sync.php");
include_once("modules/fetcher/fetch.php");
include_once("modules/fetcher/stratz.php");
include_once("modules/commons/generate_tag.php");
include_once("modules/commons/metadata.php");
include_once("modules/commons/array_pslice.php");

include_once("libs/simple-opendota-php/simple_opendota.php");

$parallel_child = false;

echo("\nInitialising...\n");

if (!function_exists('array_key_first')) {
  function array_key_first(?array $arr) {
    if (!$arr) return null;
    foreach($arr as $key => $unused) {
        return $key;
    }
    return null;
  }
}
if (!function_exists("array_key_last")) {
  function array_key_last(?array $arr) {
    if (!$arr) return null;
    if (!is_array($arr) || empty($arr)) {
        return null;
    }
    
    return array_keys($arr)[count($arr)-1];
  }
}

$conn = lrg_mysqli_connect($lrg_sql_db);
$conn->set_charset('utf8mb4');
$meta = new lrg_metadata;

$meta_spells_tags_flip = array_flip_flat($meta['spells_tags']);

$listen = isset($options['L']);

//$stratz_old_api_endpoint = 3707179408;
$stratz_timeout_retries = 2;

$force_adding = isset($options['F']);
$cache_dir = $options['c'] ?? "cache";
if($cache_dir === "NULL") $cache_dir = "";

if (!empty($options['P'])) {
  /**
   * @var array|string
   */
  $players_list = file_get_contents($options['P']);
  $players_list = json_decode($players_list);
} else if (!empty($lg_settings['players_allowlist'])) {
  $players_list = $lg_settings['players_allowlist'];
}
if (!empty($options['N'])) {
  $rank_limit = (int)$options['N'];
}

$min_duration_seconds = $lg_settings['min_duration'] ?? 600;
$min_score_side = $lg_settings['min_score_side'] ?? 5;

if (!empty($options['d'])) {
  $api_cooldown = ((float)$options['d'])*1000;
  $api_cooldown_seconds = ((float)$options['d']);
} else {
  $api_cooldown_seconds = 2;
}

$rewrite_existing = isset($options['W']);
$addition_mode    = isset($options['a']);
$update_unparsed = isset($options['u']) || isset($options['U']);

$use_stratz = isset($options['S']) || isset($options['s']);
$require_stratz = isset($options['S']);
$use_full_stratz = isset($options['Z']);
$stratz_graphql = isset($options['G']);

$stratz_graphql_group = isset($options['G']) ? (int)($options['G']) : 0;
$stratz_graphql_group_counter = 0;

$fetch_workers = max(1, (int)($options['j'] ?? 1));
if ($fetch_workers > 1) {
  if ($listen || $stratz_graphql_group) {
    echo "[W] Parallel fetch (-j) is not compatible with listen mode (-L) or grouped Stratz (-G N); using one worker.\n";
    $fetch_workers = 1;
  } elseif (!function_exists('pcntl_fork')) {
    echo "[W] pcntl_fork unavailable; parallel fetch (-j) disabled.\n";
    $fetch_workers = 1;
  }
}

$ignore_stratz = isset($options['Q']);

$update_names = isset($options['n']);
if ($update_names) $updated_names = [];

$request_unparsed = isset($options['R']);
$request_unparsed_players = isset($options['p']);

$ignore_abandons = isset($options['f']);

if(!empty($odapikey) && !isset($ignore_api_key))
  $opendota = new \SimpleOpenDotaPHP\odota_api(false, "", $api_cooldown ?? 0, $odapikey);
else
  $opendota = new \SimpleOpenDotaPHP\odota_api(false, "", $api_cooldown ?? 0);

if (!empty($options['d'])) {
  $opendota_effective_cooldown_s = (float)$options['d'];
} elseif (!empty($odapikey) && !isset($ignore_api_key)) {
  $opendota_effective_cooldown_s = 0.25;
} else {
  $opendota_effective_cooldown_s = 1.0;
}

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

  $lp = array_key_last($meta['patchdates']);
  $lastversion = ((int)$lp)*100 + count($meta['patchdates'][$lp]['dates']);

// checking out the schema

include_once("modules/commons/schema.php");

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

    $parsed_matches = [];
    if (!isset($options['Q'])) {
      $sql = "SELECT matchid FROM adv_matchlines GROUP BY matchid;";
      
      if ($conn->multi_query($sql) === TRUE);
      else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n");

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
      else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n");

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
    unset($parsed_matches);
    unset($pmatches);
  } else {
    $input_cont = file_get_contents($lrg_input);
    $input_cont = str_replace("\r\n", "\n", $input_cont);
    $matches    = explode("\n", trim($input_cont));
  }
  $matches = array_unique($matches);
  echo "[ ] Total: ".count($matches)."\n";
  echo "[ ] OpenDota cooldown: {$opendota_effective_cooldown_s} s, workers: {$fetch_workers}\n";
} else {
  $matches = [];
  echo "[ ] OpenDota cooldown: {$opendota_effective_cooldown_s} s, workers: {$fetch_workers}\n";
}

// Feature toggles for this run follow actual tables (see modules/commons/schema.php SHOW FULL TABLES).
$lg_settings['main']['teams'] = !empty($schema['teams']);
$lg_settings['main']['items'] = !empty($schema['items']);
$lg_settings['main']['itemslines'] = !empty($schema['itemslines']);
$lg_settings['main']['skill_builds'] = !empty($schema['skill_builds']);
$lg_settings['main']['starting'] = !empty($schema['starting_items']);

if ($lg_settings['main']['fantasy'] && !$schema['fantasy_mvp']) {
  create_fantasy_mvp_tables($conn);
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

$t_leagues = [];
if ($schema['leagues'] ?? false) {
  $sql = "SELECT ticket_id, name, url, description FROM leagues;";
  if ($conn->multi_query($sql) === TRUE) {
    $res = $conn->store_result();

    for ($row = $res->fetch_row(); $row != null; $row = $res->fetch_row()) {
      $t_leagues[(int)$row[0]] = [
        "name" => $row[1],
        "url" => $row[2],
        "description" => $row[3],
        "added" => true
      ];
    }
    $res->free_result();
  } else die("Something went wrong when loading leagues: ".$conn->error."\n");
}

$stdin_flag = false;

if ($fetch_workers > 1 && count($matches) > 0) {
  $GLOBALS['lrg_fetcher_stdout_lock_path']  = sys_get_temp_dir().'/lrg_fetcher_'.bin2hex(random_bytes(8)).'.flock';
  $GLOBALS['lrg_fetcher_rnum_counter_path'] = sys_get_temp_dir().'/lrg_fetcher_'.bin2hex(random_bytes(8)).'.rnum';
  $GLOBALS['lrg_fetcher_queue_path']        = sys_get_temp_dir().'/lrg_fetcher_'.bin2hex(random_bytes(8)).'.queue';
  $GLOBALS['lrg_fetcher_failures_path']     = sys_get_temp_dir().'/lrg_fetcher_'.bin2hex(random_bytes(8)).'.failures';
  $GLOBALS['lrg_fetcher_timer_path']        = sys_get_temp_dir().'/lrg_fetcher_'.bin2hex(random_bytes(8)).'.timer';
  touch($GLOBALS['lrg_fetcher_stdout_lock_path']);
  file_put_contents($GLOBALS['lrg_fetcher_rnum_counter_path'], "0");
  $GLOBALS['lrg_fetcher_stdout_lock_fp'] = null;
  lrg_fetcher_queue_init($matches);

  $pids = [];
  for ($wi = 0; $wi < $fetch_workers; $wi++) {
    $pid = pcntl_fork();
    if ($pid === -1) {
      echo "[E] pcntl_fork failed after starting ".count($pids)." worker(s); waiting for them, then exit(1).\n";
      foreach ($pids as $wpid) pcntl_waitpid($wpid, $wstatus);
      lrg_fetcher_parallel_cleanup();
      exit(1);
    }
    if ($pid === 0) {
      $parallel_child = true;
      $pids = [];
      $matches = [];  // workers pull from shared queue instead of fixed chunks
      $conn->close();
      $conn = lrg_mysqli_connect($lrg_sql_db);
      $conn->set_charset('utf8mb4');
      // Close inherited stdout-lock handle so child opens its own
      if (!empty($GLOBALS['lrg_fetcher_stdout_lock_fp']) && is_resource($GLOBALS['lrg_fetcher_stdout_lock_fp'])) {
        @fclose($GLOBALS['lrg_fetcher_stdout_lock_fp']);
        $GLOBALS['lrg_fetcher_stdout_lock_fp'] = null;
      }
      break;
    }
    $pids[] = $pid;
  }
  if (!empty($pids)) {
    foreach ($pids as $wpid) pcntl_waitpid($wpid, $wstatus);
    // Collect failures written by all workers and report once
    $all_failed = lrg_fetcher_failures_get();
    if (!empty($all_failed)) {
      echo "[R] Unparsed matches:\t".count($all_failed)."\n";
      echo "[_] Recording failed matches to file...\n";
      $filename = "tmp/failed_$lrg_league_tag".'_'.time();
      file_put_contents($filename, implode("\n", $all_failed));
      echo "[S] Recorded failed matches to $filename\n";
    }
    lrg_fetcher_parallel_cleanup();
    echo "[S] Fetch complete.\n";
    exit(0);
  }
}

if ($listen)
  echo "Listening...\n";

// this code is such a shitshow tbh, but I don't want to fix it, c ya in lrg-simon
while(sizeof($matches) || $listen || $parallel_child) {
  // Parallel workers: flush any timer entries that are now ready back into
  // the work queue, then pull the next match. If both queues are empty,
  // sleep briefly until the nearest timer entry is due rather than blocking.
  if ($parallel_child) {
    lrg_fetcher_timer_flush_ready();
    if (empty($matches)) {
      $pulled = lrg_fetcher_queue_pop(1);
      if (empty($pulled)) {
        $nextTime = lrg_fetcher_timer_next_time();
        if ($nextTime === null) break; // work queue and timer queue both empty
        $sleepSecs = max(1, min($nextTime - time(), 30));
        sleep($sleepSecs);
        continue;
      }
      $matches = $pulled;
    }
  }

  // Only read from STDIN when the local queue is fully drained (no pending
  // retries). This ensures that an updated/appended matchlist file does not
  // bleed into the current run while matches are still being retried.
  if ($listen && !sizeof($matches)) {
    if (feof(STDIN)) break;
    $read   = [STDIN];
    $write  = null;
    $except = null;
    // Wait up to 1 s for a new match ID; loop back if nothing arrives.
    $ready = stream_select($read, $write, $except, 1, 0);
    if ($ready) {
      $match_str = fgets(STDIN);
      if ($match_str && strlen(trim($match_str)) > 0) {
        array_unshift($matches, trim($match_str));
      }
    }
    $stdin_flag = false;
  }

  if ($stratz_graphql_group) {
    if (!$stratz_graphql_group_counter) {
      $stratz_graphql_group_counter = $stratz_graphql_group;

      $stratz_cache = [];

      $group = [];
      for ($i = 0; count($group) < $stratz_graphql_group; $i++) {
        if (!isset($matches[$i])) break;

        if (empty($matches[$i]) || $matches[$i][0] == "#" || strlen($matches[$i]) < 2) continue;

        if (!$rewrite_existing || $update_unparsed || $request_unparsed_players) {
          if ($update_unparsed || $request_unparsed_players) {
            $query = $conn->query("SELECT matchid FROM adv_matchlines WHERE matchid = ".$matches[$i].";");
          } else {
            $query = $conn->query("SELECT matchid FROM matches WHERE matchid = ".$matches[$i].";");
          }

          if (isset($query->num_rows) && $query->num_rows) {
            echo '.';
            continue;
          }
        }

        if (!$rewrite_existing && (file_exists("$cache_dir/".$matches[$i].".lrgcache.json") || file_exists("$cache_dir/".$matches[$i].".json"))) {
          echo ',';
          continue;
        }

        $group[] = $matches[$i];
      }

      // $group = array_slice($matches, 0, $stratz_graphql_group);
      
      echo "[Z] Requesting group of $stratz_graphql_group matches\n";

      try {
        get_stratz_multiquery($group);
      } catch (\Throwable $e) {
        echo "[E] Error requesting following group: [".implode(', ', $group)."], skipping\n";
      }
    }
  }

  $match = array_shift($matches);

  if (strpos($match, '::')) {
    processRules($match_raw);
  } else $match_raw = $match;

  // Single-worker only: gate retries via $first_scheduled.
  // Parallel workers use the timer queue instead (see above).
  if (!$parallel_child && $request_unparsed && isset($first_scheduled[$match_raw])) {
    if (time() - $first_scheduled[$match_raw] < $scheduled_wait_period) {
      if (!sizeof($matches)) {
        if ($listen && !$force_await) {
          // In listen mode pace retries without blocking STDIN.
          sleep(1);
        } else {
          echo "[W] Waiting for $scheduled_wait_period seconds\n";
          sleep($scheduled_wait_period);
        }
      }
      if (!in_array($match, $matches))
        array_push($matches, $match);
      continue;
    }
  }


  lrg_fetcher_parallel_ob_begin();
  try {
    $r = fetch($match);
  } finally {
    lrg_fetcher_parallel_ob_end_flush();
  }
  if ($r === FALSE) { //|| ($force_await && $request_unparsed && $r !== TRUE)) {
    if ($parallel_child) {
      // Don't block: park the match in the shared timer queue and keep working.
      lrg_fetcher_timer_add($match, time() + $scheduled_wait_period);
    } else {
      array_push($matches, $match);
    }
  } else if ($r === NULL) {
    if ($parallel_child) {
      lrg_fetcher_failure_add($match);
    } else {
      $failed_matches[] = $match;
    }
  }
}

if (sizeof($failed_matches)) {
  echo "[R] Unparsed matches: \t".sizeof($failed_matches)."\n";

  echo "[_] Recording failed matches to file...\n";

  $output = implode("\n", $failed_matches);
  $pid_tag = ($parallel_child && function_exists('posix_getpid')) ? '_'.posix_getpid() : '';
  $filename = "tmp/failed_$lrg_league_tag".'_'.time().$pid_tag;
  $f = fopen($filename, "w");
  fwrite($f, $output);
  fclose($f);

  echo "[S] Recorded failed matches to $filename\n";
}

if (!$parallel_child) {
  echo "[S] Fetch complete.\n";
}

?>
