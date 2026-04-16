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
  foreach ([
    'lrg_fetcher_stdout_lock_path',
    'lrg_fetcher_rnum_counter_path',
    'lrg_fetcher_queue_path',
    'lrg_fetcher_failures_path',
    'lrg_fetcher_timer_path',
  ] as $k) {
    $p = $GLOBALS[$k] ?? null;
    if ($p !== null && $p !== '' && is_file($p)) {
      @unlink($p);
    }
    $GLOBALS[$k] = null;
  }
}

// ---------------------------------------------------------------------------
// Shared work queue: workers pop matches instead of processing fixed chunks.
// ---------------------------------------------------------------------------

/** Write all matches to the shared queue file (call once before forking). */
function lrg_fetcher_queue_init(array $items): void {
  $path = $GLOBALS['lrg_fetcher_queue_path'] ?? null;
  if (!$path) return;
  file_put_contents($path, implode("\n", array_map('strval', $items)));
}

/** Append a single match back to the tail of the shared work queue. */
function lrg_fetcher_queue_push(string $match): void {
  $path = $GLOBALS['lrg_fetcher_queue_path'] ?? null;
  if (!$path) return;
  $fp = @fopen($path, 'a');
  if (!$fp) return;
  flock($fp, LOCK_EX);
  fwrite($fp, $match."\n");
  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
}

/** Atomically pop up to $n matches from the queue. Returns [] when empty. */
function lrg_fetcher_queue_pop(int $n = 1): array {
  $path = $GLOBALS['lrg_fetcher_queue_path'] ?? null;
  if (!$path) return [];
  $fp = @fopen($path, 'c+');
  if (!$fp) return [];
  flock($fp, LOCK_EX);
  $content = stream_get_contents($fp);
  $lines = ($content !== '' && $content !== false)
    ? array_values(array_filter(explode("\n", $content), 'strlen'))
    : [];
  $batch = array_splice($lines, 0, $n);
  rewind($fp);
  ftruncate($fp, 0);
  fwrite($fp, implode("\n", $lines));
  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  return $batch;
}

/** Append a match to the shared failures log (written by workers, read by parent). */
function lrg_fetcher_failure_add(string $match): void {
  $path = $GLOBALS['lrg_fetcher_failures_path'] ?? null;
  if (!$path) return;
  $fp = @fopen($path, 'a');
  if (!$fp) return;
  flock($fp, LOCK_EX);
  fwrite($fp, $match."\n");
  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
}

/** Read all failures accumulated by workers (call from parent after they exit). */
function lrg_fetcher_failures_get(): array {
  $path = $GLOBALS['lrg_fetcher_failures_path'] ?? null;
  if (!$path || !is_file($path)) return [];
  $raw = file_get_contents($path);
  if ($raw === false || $raw === '') return [];
  return array_values(array_filter(explode("\n", $raw), 'strlen'));
}

// ---------------------------------------------------------------------------
// Shared timer queue: workers park scheduled retries here instead of blocking.
// Format: one "match_id\tready_timestamp" per line.
// ---------------------------------------------------------------------------

/** Schedule $match to re-enter the work queue at $readyAt (Unix timestamp). */
function lrg_fetcher_timer_add(string $match, int $readyAt): void {
  $path = $GLOBALS['lrg_fetcher_timer_path'] ?? null;
  if (!$path) return;
  $fp = @fopen($path, 'a');
  if (!$fp) return;
  flock($fp, LOCK_EX);
  fwrite($fp, $match."\t".$readyAt."\n");
  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
}

/**
 * Atomically move all ready timer entries (ready_time <= now) into the
 * main work queue so any idle worker can pick them up immediately.
 */
function lrg_fetcher_timer_flush_ready(): void {
  $tpath = $GLOBALS['lrg_fetcher_timer_path'] ?? null;
  if (!$tpath || !is_file($tpath)) return;
  $fp = @fopen($tpath, 'c+');
  if (!$fp) return;
  flock($fp, LOCK_EX);
  $content = stream_get_contents($fp);
  $now     = time();
  $ready   = [];
  $pending = [];
  foreach (explode("\n", (string)$content) as $line) {
    $line = trim($line);
    if ($line === '') continue;
    [$mid, $ts] = explode("\t", $line, 2);
    if ((int)$ts <= $now) {
      $ready[] = $mid;
    } else {
      $pending[] = $line;
    }
  }
  rewind($fp);
  ftruncate($fp, 0);
  fwrite($fp, $pending ? implode("\n", $pending)."\n" : "");
  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  foreach ($ready as $mid) lrg_fetcher_queue_push($mid);
}

/** Return the earliest ready-timestamp in the timer queue, or null if empty. */
function lrg_fetcher_timer_next_time(): ?int {
  $path = $GLOBALS['lrg_fetcher_timer_path'] ?? null;
  if (!$path || !is_file($path)) return null;
  $raw = @file_get_contents($path);
  if ($raw === false || $raw === '') return null;
  $min = null;
  foreach (explode("\n", $raw) as $line) {
    $line = trim($line);
    if ($line === '') continue;
    [, $ts] = explode("\t", $line, 2);
    $t = (int)$ts;
    if ($min === null || $t < $min) $min = $t;
  }
  return $min;
}
