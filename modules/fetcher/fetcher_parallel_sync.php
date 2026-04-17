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

/** Local timestamp with ms for fetch match lines (e.g. 2026-04-17 12:01:01.567). */
function lrg_fetcher_match_log_timestamp(): string {
  $t   = microtime(true);
  $sec = (int)floor($t);
  $ms  = (int)floor(($t - $sec) * 1000);
  if ($ms > 999) {
    $sec++;
    $ms = 0;
  }
  return date('Y-m-d H:i:s', $sec).'.'.str_pad((string)$ms, 3, '0', STR_PAD_LEFT);
}

// ---------------------------------------------------------------------------
// Single IPC lock for queue + timer + failures (avoids cross-file deadlocks
// when one worker holds the timer lock and another holds the queue lock).
// ---------------------------------------------------------------------------

function lrg_fetcher_ipc_ensure_fp(): void {
  $p = $GLOBALS['lrg_fetcher_ipc_lock_path'] ?? null;
  if (!$p) {
    return;
  }
  if (!empty($GLOBALS['lrg_fetcher_ipc_lock_fp']) && is_resource($GLOBALS['lrg_fetcher_ipc_lock_fp'])) {
    return;
  }
  $fp = @fopen($p, 'cb');
  if ($fp) {
    $GLOBALS['lrg_fetcher_ipc_lock_fp'] = $fp;
  }
}

function lrg_fetcher_ipc_lock(): void {
  $p = $GLOBALS['lrg_fetcher_ipc_lock_path'] ?? null;
  if (!$p) {
    return;
  }
  lrg_fetcher_ipc_ensure_fp();
  $fp = $GLOBALS['lrg_fetcher_ipc_lock_fp'] ?? null;
  if ($fp && is_resource($fp)) {
    flock($fp, LOCK_EX);
  }
}

function lrg_fetcher_ipc_unlock(): void {
  $fp = $GLOBALS['lrg_fetcher_ipc_lock_fp'] ?? null;
  if ($fp && is_resource($fp)) {
    flock($fp, LOCK_UN);
  }
}

/** Append one match line to the work queue; caller must hold IPC lock. */
function lrg_fetcher_queue_append_unlocked(string $match): void {
  $path = $GLOBALS['lrg_fetcher_queue_path'] ?? null;
  if (!$path) {
    return;
  }
  $fp = @fopen($path, 'a');
  if (!$fp) {
    return;
  }
  fwrite($fp, $match."\n");
  fclose($fp);
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
  $ifp = $GLOBALS['lrg_fetcher_ipc_lock_fp'] ?? null;
  if ($ifp !== null && is_resource($ifp)) {
    @fclose($ifp);
    $GLOBALS['lrg_fetcher_ipc_lock_fp'] = null;
  }
  foreach ([
    'lrg_fetcher_stdout_lock_path',
    'lrg_fetcher_rnum_counter_path',
    'lrg_fetcher_queue_path',
    'lrg_fetcher_failures_path',
    'lrg_fetcher_timer_path',
    'lrg_fetcher_scheduled_path',
    'lrg_fetcher_ipc_lock_path',
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
  if (!$path) {
    return;
  }
  lrg_fetcher_ipc_lock();
  try {
    file_put_contents($path, implode("\n", array_map('strval', $items)));
  } finally {
    lrg_fetcher_ipc_unlock();
  }
}

/** Append a single match back to the tail of the shared work queue. */
function lrg_fetcher_queue_push(string $match): void {
  if (empty($GLOBALS['lrg_fetcher_queue_path'])) {
    return;
  }
  lrg_fetcher_ipc_lock();
  try {
    lrg_fetcher_queue_append_unlocked($match);
  } finally {
    lrg_fetcher_ipc_unlock();
  }
}

/** Atomically pop up to $n matches from the queue. Returns [] when empty. */
function lrg_fetcher_queue_pop(int $n = 1): array {
  $path = $GLOBALS['lrg_fetcher_queue_path'] ?? null;
  if (!$path) {
    return [];
  }
  lrg_fetcher_ipc_lock();
  try {
    $fp = @fopen($path, 'c+');
    if (!$fp) {
      return [];
    }
    $content = stream_get_contents($fp);
    $lines = ($content !== '' && $content !== false)
      ? array_values(array_filter(explode("\n", $content), 'strlen'))
      : [];
    $batch = array_splice($lines, 0, $n);
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, implode("\n", $lines));
    fflush($fp);
    fclose($fp);
    return $batch;
  } finally {
    lrg_fetcher_ipc_unlock();
  }
}

/** Append a match to the shared failures log (written by workers, read by parent). */
function lrg_fetcher_failure_add(string $match): void {
  $path = $GLOBALS['lrg_fetcher_failures_path'] ?? null;
  if (!$path) {
    return;
  }
  lrg_fetcher_ipc_lock();
  try {
    $fp = @fopen($path, 'a');
    if (!$fp) {
      return;
    }
    fwrite($fp, $match."\n");
    fclose($fp);
  } finally {
    lrg_fetcher_ipc_unlock();
  }
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
// Shared "already rescheduled" set.
// When a worker requests an OD re-parse it writes the match ID here so that
// other workers who later dequeue the same match know not to request again.
// ---------------------------------------------------------------------------

/** Mark $match as already re-requested (idempotent). */
function lrg_fetcher_scheduled_add(string $match): void {
  $path = $GLOBALS['lrg_fetcher_scheduled_path'] ?? null;
  if (!$path) return;
  lrg_fetcher_ipc_lock();
  try {
    $fp = @fopen($path, 'a');
    if ($fp) { fwrite($fp, $match."\n"); fclose($fp); }
  } finally {
    lrg_fetcher_ipc_unlock();
  }
}

/** Return true if $match has already been re-requested by any worker. */
function lrg_fetcher_scheduled_has(string $match): bool {
  $path = $GLOBALS['lrg_fetcher_scheduled_path'] ?? null;
  if (!$path || !is_file($path)) return false;
  lrg_fetcher_ipc_lock();
  try {
    $raw = @file_get_contents($path);
  } finally {
    lrg_fetcher_ipc_unlock();
  }
  if ($raw === false || $raw === '') return false;
  foreach (explode("\n", $raw) as $line) {
    if (trim($line) === $match) return true;
  }
  return false;
}

// ---------------------------------------------------------------------------
// Shared timer queue: workers park scheduled retries here instead of blocking.
// Format: one "match_id\tready_timestamp" per line.
// ---------------------------------------------------------------------------

/** Schedule $match to re-enter the work queue at $readyAt (Unix timestamp). */
function lrg_fetcher_timer_add(string $match, int $readyAt): void {
  $path = $GLOBALS['lrg_fetcher_timer_path'] ?? null;
  if (!$path) {
    return;
  }
  lrg_fetcher_ipc_lock();
  try {
    $fp = @fopen($path, 'c+');
    if (!$fp) {
      return;
    }
    $raw = stream_get_contents($fp);
    $entries = [];
    foreach (explode("\n", (string)$raw) as $line) {
      $line = trim($line);
      if ($line === '') {
        continue;
      }
      $parts = explode("\t", $line, 2);
      if (count($parts) < 2) {
        continue;
      }
      $mid = (string)$parts[0];
      $ts  = (int)$parts[1];
      if (!isset($entries[$mid]) || $ts < $entries[$mid]) {
        $entries[$mid] = $ts;
      }
    }
    $entries[$match] = $readyAt;
    $rows = [];
    foreach ($entries as $mid => $ts) {
      $rows[] = $mid."\t".$ts;
    }
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, $rows ? implode("\n", $rows)."\n" : '');
    fflush($fp);
    fclose($fp);
  } finally {
    lrg_fetcher_ipc_unlock();
  }
}

/**
 * Atomically move all ready timer entries (ready_time <= now) into the
 * main work queue so any idle worker can pick them up immediately.
 *
 * Ready matches are pushed to the work queue *before* the timer file is
 * rewritten while still holding LOCK_EX. Otherwise another worker can see
 * an empty timer + empty queue between truncate and push and exit early,
 * leaving matches stuck until killed.
 */
function lrg_fetcher_timer_flush_ready(): void {
  $tpath = $GLOBALS['lrg_fetcher_timer_path'] ?? null;
  if (!$tpath || !is_file($tpath)) {
    return;
  }
  lrg_fetcher_ipc_lock();
  try {
    $fp = @fopen($tpath, 'c+');
    if (!$fp) {
      return;
    }
    $content = stream_get_contents($fp);
    $now     = time();
    $ready   = [];
    $pending = [];
    foreach (explode("\n", (string)$content) as $line) {
      $line = trim($line);
      if ($line === '') {
        continue;
      }
      $parts = explode("\t", $line, 2);
      if (count($parts) < 2) {
        continue;
      }
      [$mid, $ts] = $parts;
      if ((int)$ts <= $now) {
        $ready[$mid] = true;
      } else {
        $pending[$mid] = (int)$ts;
      }
    }
    foreach (array_keys($ready) as $mid) {
      lrg_fetcher_queue_append_unlocked($mid);
    }
    $pending_rows = [];
    foreach ($pending as $mid => $ts) {
      $pending_rows[] = $mid."\t".$ts;
    }
    $new_body = $pending_rows ? implode("\n", $pending_rows)."\n" : '';
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, $new_body);
    fflush($fp);
    fclose($fp);
  } finally {
    lrg_fetcher_ipc_unlock();
  }
}

/** Return the earliest ready-timestamp and count of entries in the timer queue. */
function lrg_fetcher_timer_info(): array {
  $path = $GLOBALS['lrg_fetcher_timer_path'] ?? null;
  if (!$path || !is_file($path)) {
    return ['next' => null, 'count' => 0];
  }
  lrg_fetcher_ipc_lock();
  try {
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
      return ['next' => null, 'count' => 0];
    }
    $min   = null;
    $count = 0;
    foreach (explode("\n", $raw) as $line) {
      $line = trim($line);
      if ($line === '') continue;
      $parts = explode("\t", $line, 2);
      if (count($parts) < 2) continue;
      $t = (int)$parts[1];
      $count++;
      if ($min === null || $t < $min) $min = $t;
    }
    return ['next' => $min, 'count' => $count];
  } finally {
    lrg_fetcher_ipc_unlock();
  }
}

/** Return the earliest ready-timestamp in the timer queue, or null if empty. */
function lrg_fetcher_timer_next_time(): ?int {
  $path = $GLOBALS['lrg_fetcher_timer_path'] ?? null;
  if (!$path || !is_file($path)) {
    return null;
  }
  lrg_fetcher_ipc_lock();
  try {
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
      return null;
    }
    $min = null;
    foreach (explode("\n", $raw) as $line) {
      $line = trim($line);
      if ($line === '') {
        continue;
      }
      $parts = explode("\t", $line, 2);
      if (count($parts) < 2) {
        continue;
      }
      $t = (int)$parts[1];
      if ($min === null || $t < $min) {
        $min = $t;
      }
    }
    return $min;
  } finally {
    lrg_fetcher_ipc_unlock();
  }
}
