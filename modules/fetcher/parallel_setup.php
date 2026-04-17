<?php

if ($fetch_workers > 1 && count($matches) > 0) {
  $ipc_tag  = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$lrg_league_tag) . '_' . getmypid() . '_' . bin2hex(random_bytes(4));
  $ipc_base = rtrim(sys_get_temp_dir(), "/\\") . '/lrg_fetcher_' . $ipc_tag;

  $GLOBALS['lrg_fetcher_stdout_lock_path']  = $ipc_base . '_stdout.flock';
  $GLOBALS['lrg_fetcher_rnum_counter_path'] = $ipc_base . '_rnum.counter';
  $GLOBALS['lrg_fetcher_queue_path']        = $ipc_base . '_work.queue';
  $GLOBALS['lrg_fetcher_failures_path']     = $ipc_base . '_failures.log';
  $GLOBALS['lrg_fetcher_timer_path']        = $ipc_base . '_wait.timer';
  $GLOBALS['lrg_fetcher_scheduled_path']    = $ipc_base . '_scheduled.log';
  $GLOBALS['lrg_fetcher_ipc_lock_path']     = $ipc_base . '_ipc.flock';
  $GLOBALS['lrg_fetcher_ipc_lock_fp']       = null;
  $GLOBALS['lrg_fetcher_stdout_lock_fp']    = null;

  touch($GLOBALS['lrg_fetcher_stdout_lock_path']);
  touch($GLOBALS['lrg_fetcher_ipc_lock_path']);
  file_put_contents($GLOBALS['lrg_fetcher_rnum_counter_path'], "0");

  lrg_fetcher_queue_init($matches);

  echo "[ ] Parallel IPC base: {$ipc_base}_*\n";

  $pids = [];
  for ($wi = 0; $wi < $fetch_workers; $wi++) {
    $pid = pcntl_fork();
    if ($pid === -1) {
      echo "[E] pcntl_fork failed after starting " . count($pids) . " worker(s).\n";
      foreach ($pids as $wpid) pcntl_waitpid($wpid, $wstatus);
      lrg_fetcher_parallel_cleanup();
      exit(1);
    }
    if ($pid === 0) {
      $parallel_child = true;
      $pids   = [];
      $matches = [];
      $conn->close();
      $conn = lrg_mysqli_connect($lrg_sql_db);
      $conn->set_charset('utf8mb4');
      foreach (['lrg_fetcher_stdout_lock_fp', 'lrg_fetcher_ipc_lock_fp'] as $_fpkey) {
        if (!empty($GLOBALS[$_fpkey]) && is_resource($GLOBALS[$_fpkey])) {
          @fclose($GLOBALS[$_fpkey]);
          $GLOBALS[$_fpkey] = null;
        }
      }
      break;
    }
    $pids[] = $pid;
  }

  if (!empty($pids)) {
    foreach ($pids as $wpid) pcntl_waitpid($wpid, $wstatus);
    $all_failed = lrg_fetcher_failures_get();
    if (!empty($all_failed)) {
      echo "[R] Unparsed matches:\t" . count($all_failed) . "\n";
      echo "[_] Recording failed matches to file...\n";
      $filename = "tmp/failed_{$lrg_league_tag}_" . time();
      file_put_contents($filename, implode("\n", $all_failed));
      echo "[S] Recorded failed matches to $filename\n";
    }
    lrg_fetcher_parallel_cleanup();
    echo "[S] Fetch complete.\n";
    exit(0);
  }
}
