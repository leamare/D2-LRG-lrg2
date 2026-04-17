#!/bin/php
<?php
ini_set('memory_limit', '4000M');
ini_set('mysqli.reconnect', '1');
ini_set('default_socket_timeout', '30');
mysqli_report(MYSQLI_REPORT_OFF);

include_once("head.php");
include_once("modules/commons/array_key_compat.php");
include_once("modules/fetcher/get_patchid.php");
include_once("modules/fetcher/processRules.php");
include_once("modules/fetcher/fetcher_parallel_sync.php");
include_once("modules/fetcher/fetch.php");
include_once("modules/fetcher/stratz.php");
include_once("modules/commons/generate_tag.php");
include_once("modules/commons/metadata.php");
include_once("modules/commons/array_pslice.php");
include_once("libs/simple-opendota-php/simple_opendota.php");

echo("\nInitialising...\n");

$conn = lrg_mysqli_connect($lrg_sql_db);
$conn->set_charset('utf8mb4');
$meta = new lrg_metadata;
$meta_spells_tags_flip = array_flip_flat($meta['spells_tags']);

include_once("modules/fetcher/init_cli_params.php");
include_once("modules/commons/schema.php");
include_once("modules/fetcher/queue_init.php");
include_once("modules/fetcher/objects_prep.php");

$parallel_child = false;
$stdin_flag     = false;
include_once("modules/fetcher/parallel_setup.php");

if ($listen) echo "Listening...\n";

while (sizeof($matches) || $listen || $parallel_child) {
  if ($parallel_child) {
    lrg_fetcher_timer_flush_ready();
    if (empty($matches)) {
      $pulled = lrg_fetcher_queue_pop(1);
      if (empty($pulled)) {
        $info = lrg_fetcher_timer_info();
        if ($info['next'] === null) break;
        $sleepSecs = max(1, $info['next'] - time());
        lrg_fetcher_stdout_lock_for_fetch();
        echo "[W] queue empty, {$info['count']} in wait queue, next retry in {$sleepSecs}s\n";
        if (defined('STDOUT') && is_resource(STDOUT)) fflush(STDOUT);
        lrg_fetcher_stdout_unlock_for_fetch();
        sleep($sleepSecs);
        continue;
      }
      $matches = $pulled;
    }
  }

  // Only read from STDIN when the local queue is fully drained, to prevent
  // a changed matchlist from interrupting an in-progress retry cycle.
  if ($listen && !sizeof($matches)) {
    if (feof(STDIN)) break;
    $read = [STDIN]; $write = null; $except = null;
    $ready = stream_select($read, $write, $except, 1, 0);
    if ($ready) {
      $match_str = fgets(STDIN);
      if ($match_str && strlen(trim($match_str)) > 0) {
        array_unshift($matches, trim($match_str));
      }
    }
    $stdin_flag = false;
  }

  if (!sizeof($matches)) continue;

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
            $query = $conn->query("SELECT matchid FROM adv_matchlines WHERE matchid = " . $matches[$i] . ";");
          } else {
            $query = $conn->query("SELECT matchid FROM matches WHERE matchid = " . $matches[$i] . ";");
          }
          if (isset($query->num_rows) && $query->num_rows) { echo '.'; continue; }
        }
        if (!$rewrite_existing && (file_exists("$cache_dir/" . $matches[$i] . ".lrgcache.json") || file_exists("$cache_dir/" . $matches[$i] . ".json"))) {
          echo ','; continue;
        }
        $group[] = $matches[$i];
      }
      echo "[Z] Requesting group of $stratz_graphql_group matches\n";
      try {
        get_stratz_multiquery($group);
      } catch (\Throwable $e) {
        echo "[E] Error requesting following group: [" . implode(', ', $group) . "], skipping\n";
      }
    }
  }

  $match = array_shift($matches);
  if (strpos($match, '::')) {
    processRules($match_raw);
  } else {
    $match_raw = $match;
  }

  if (!$parallel_child && $request_unparsed && isset($first_scheduled[$match_raw])) {
    if (time() - $first_scheduled[$match_raw] < $scheduled_wait_period) {
      if (!sizeof($matches)) {
        if ($listen && !$force_await) {
          sleep(1);
        } else {
          echo "[W] Waiting for $scheduled_wait_period seconds\n";
          sleep($scheduled_wait_period);
        }
      }
      if (!in_array($match, $matches)) array_push($matches, $match);
      continue;
    }
  }

  lrg_fetcher_parallel_ob_begin();
  try {
    $r = fetch($match);
  } finally {
    lrg_fetcher_parallel_ob_end_flush();
  }

  if ($r === FALSE) {
    if ($parallel_child) {
      lrg_fetcher_timer_add($match, time() + $scheduled_wait_period);
    } else {
      array_push($matches, $match);
    }
  } elseif ($r === NULL) {
    if ($parallel_child) {
      lrg_fetcher_failure_add($match);
    } else {
      $failed_matches[] = $match;
    }
  }
}

if (sizeof($failed_matches)) {
  echo "[R] Unparsed matches: \t" . sizeof($failed_matches) . "\n";
  echo "[_] Recording failed matches to file...\n";
  $filename = "tmp/failed_{$lrg_league_tag}_" . time();
  $f = fopen($filename, "w");
  fwrite($f, implode("\n", $failed_matches));
  fclose($f);
  echo "[S] Recorded failed matches to $filename\n";
}

if (!$parallel_child) {
  echo "[S] Fetch complete.\n";
}
