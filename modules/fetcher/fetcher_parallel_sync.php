<?php

/**
 * Shared match counter + serialized stdout for rg_fetcher -j workers.
 * Each fetch() runs with a plain ob_start(); the whole log for one match is flushed in
 * one locked write when fetch() returns. That keeps lines coherent across workers while
 * HTTP/DB inside fetch() stay parallel (only the final print is serialized).
 */

function lrg_fetcher_stdout_lock_for_fetch(): void {
  $path = $GLOBALS['lrg_fetcher_stdout_lock_path'] ?? null;
  if ($path === null || $path === '') {
    return;
  }
  if (($GLOBALS['lrg_fetcher_stdout_lock_fp'] ?? null) === null) {
    $fp = @fopen($path, 'cb');
    if (!$fp) {
      return;
    }
    $GLOBALS['lrg_fetcher_stdout_lock_fp'] = $fp;
  }
  flock($GLOBALS['lrg_fetcher_stdout_lock_fp'], LOCK_EX);
}

function lrg_fetcher_stdout_unlock_for_fetch(): void {
  $fp = $GLOBALS['lrg_fetcher_stdout_lock_fp'] ?? null;
  if ($fp !== null) {
    flock($fp, LOCK_UN);
    if (defined('STDOUT') && is_resource(STDOUT)) {
      fflush(STDOUT);
    }
  }
}

/** Next 1-based match index for log prefix; atomic when counter file is set. */
function lrg_fetcher_alloc_match_seq(): int {
  $path = $GLOBALS['lrg_fetcher_rnum_counter_path'] ?? null;
  global $rnum;
  if ($path === null || $path === '') {
    $seq = $rnum;
    $rnum++;
    return $seq;
  }
  $fp = fopen($path, 'c+');
  if (!$fp) {
    $seq = $rnum;
    $rnum++;
    return $seq;
  }
  flock($fp, LOCK_EX);
  $raw = stream_get_contents($fp);
  $n = (int)trim((string)$raw);
  $n++;
  rewind($fp);
  ftruncate($fp, 0);
  fwrite($fp, (string)$n);
  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  return $n;
}

function lrg_fetcher_parallel_ob_begin(): void {
  if (!empty($GLOBALS['lrg_fetcher_stdout_lock_path'])) {
    ob_start();
  }
}

function lrg_fetcher_parallel_ob_end_flush(): void {
  if (empty($GLOBALS['lrg_fetcher_stdout_lock_path']) || ob_get_level() < 1) {
    return;
  }
  $buf = ob_get_clean();
  if ($buf === false || $buf === '') {
    return;
  }
  lrg_fetcher_stdout_lock_for_fetch();
  if (defined('STDOUT') && is_resource(STDOUT)) {
    fwrite(STDOUT, $buf);
  } else {
    echo $buf;
  }
  lrg_fetcher_stdout_unlock_for_fetch();
}

function lrg_fetcher_parallel_cleanup(): void {
  $fp = $GLOBALS['lrg_fetcher_stdout_lock_fp'] ?? null;
  if ($fp !== null && is_resource($fp)) {
    @fclose($fp);
    $GLOBALS['lrg_fetcher_stdout_lock_fp'] = null;
  }
  foreach (['lrg_fetcher_stdout_lock_path', 'lrg_fetcher_rnum_counter_path'] as $k) {
    $p = $GLOBALS[$k] ?? null;
    if ($p !== null && $p !== '' && is_file($p)) {
      @unlink($p);
    }
    $GLOBALS[$k] = null;
  }
}
