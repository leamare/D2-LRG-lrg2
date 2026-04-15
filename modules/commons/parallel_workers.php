<?php

function lrg_parallel_script_name(): string {
  $argv0 = $_SERVER['argv'][0] ?? 'script.php';
  $base = basename((string)$argv0);
  $name = pathinfo($base, PATHINFO_FILENAME);
  if ($name === '') {
    $name = 'script';
  }
  return preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
}

function lrg_parallel_init_context(?string $scriptName = null): array {
  $script = $scriptName ?: lrg_parallel_script_name();
  $token = bin2hex(random_bytes(8));
  $prefix = rtrim(sys_get_temp_dir(), "/\\").'/'.$script.'_'.$token;

  $ctx = [
    'script' => $script,
    'lock_path' => $prefix.'.flock',
    'counter_path' => $prefix.'.counter',
    'fp' => null,
    'owner_pid' => function_exists('posix_getpid') ? posix_getpid() : getmypid(),
  ];

  touch($ctx['lock_path']);
  file_put_contents($ctx['counter_path'], "0");

  return $ctx;
}

function lrg_parallel_context_sync(array &$ctx): void {
  if (!isset($ctx['fp'])) {
    $ctx['fp'] = null;
  }
}

function lrg_parallel_lock(array &$ctx): void {
  lrg_parallel_context_sync($ctx);
  if (empty($ctx['lock_path'])) {
    return;
  }
  if ($ctx['fp'] === null) {
    $ctx['fp'] = @fopen($ctx['lock_path'], 'cb');
    if (!$ctx['fp']) {
      return;
    }
  }
  flock($ctx['fp'], LOCK_EX);
}

function lrg_parallel_unlock(array &$ctx): void {
  if (!empty($ctx['fp'])) {
    flock($ctx['fp'], LOCK_UN);
    if (defined('STDOUT') && is_resource(STDOUT)) {
      fflush(STDOUT);
    }
  }
}

function lrg_parallel_log(array &$ctx, string $message): void {
  lrg_parallel_lock($ctx);
  if (defined('STDOUT') && is_resource(STDOUT)) {
    fwrite(STDOUT, $message);
  } else {
    echo $message;
  }
  lrg_parallel_unlock($ctx);
}

function lrg_parallel_alloc_seq(array &$ctx): int {
  if (empty($ctx['counter_path'])) {
    return 0;
  }
  $fp = @fopen($ctx['counter_path'], 'c+');
  if (!$fp) {
    return 0;
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

function lrg_parallel_cleanup(array &$ctx): void {
  if (!empty($ctx['fp']) && is_resource($ctx['fp'])) {
    @fclose($ctx['fp']);
  }
  $ctx['fp'] = null;

  $pid = function_exists('posix_getpid') ? posix_getpid() : getmypid();
  if (($ctx['owner_pid'] ?? null) !== $pid) {
    return;
  }

  foreach (['lock_path', 'counter_path'] as $k) {
    if (!empty($ctx[$k]) && is_file($ctx[$k])) {
      @unlink($ctx[$k]);
    }
  }
}

function lrg_parallel_run(array $items, int $workers, callable $workerFn): int {
  $items = array_values($items);
  if (empty($items)) {
    return 0;
  }

  $workers = max(1, $workers);
  if ($workers === 1 || !function_exists('pcntl_fork')) {
    $workerFn($items, 0, 1);
    return 0;
  }

  $chunks = array_chunk($items, (int)ceil(count($items) / $workers));
  $chunks = array_values(array_filter($chunks, function ($chunk) {
    return !empty($chunk);
  }));
  if (count($chunks) <= 1) {
    $workerFn($items, 0, 1);
    return 0;
  }

  $pids = [];
  foreach ($chunks as $i => $chunk) {
    $pid = pcntl_fork();
    if ($pid === -1) {
      foreach ($pids as $wpid) {
        pcntl_waitpid($wpid, $status);
      }
      return 1;
    }
    if ($pid === 0) {
      try {
        $workerFn($chunk, $i, count($chunks));
        exit(0);
      } catch (\Throwable $e) {
        fwrite(STDERR, "[E] Worker crash: ".$e->getMessage()."\n");
        exit(1);
      }
    }
    $pids[] = $pid;
  }

  $exitCode = 0;
  foreach ($pids as $wpid) {
    pcntl_waitpid($wpid, $status);
    if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
      $exitCode = 1;
    }
  }
  return $exitCode;
}
