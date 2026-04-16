#!/bin/php
<?php
require_once("libs/simple-opendota-php/simple_opendota.php");
require_once("head.php");
include_once("modules/commons/parallel_workers.php");

$workers = max(1, (int)($options['j'] ?? 1));
$ignoreApiKey = isset($options['K']) || (!empty($ignore_api_key));
$useApiKey = !empty($odapikey) && !$ignoreApiKey;
$cooldownSeconds = isset($options['d'])
  ? (float)$options['d']
  : ($useApiKey ? 0.03 : 1.5);
$cooldownMs = (int)max(0, round($cooldownSeconds * 1000));

if(isset($options['f'])) {
  $filename = $options['f'];
  $input_cont = file_get_contents($filename) or die("[F] Error while opening file.\n");
  $input_cont = str_replace("\r\n", "\n", $input_cont);
  $matches = explode("\n", trim($input_cont));
} elseif (isset($options['m'])) {
  $matches = [(string)$options['m']];
} else {
  die("[F] Pass -f<matchlist> or -m<matchid>\n");
}

$matches = array_values(array_filter(array_map('trim', array_unique($matches)), 'strlen'));
$matches = array_values(array_filter($matches, function ($match) {
  return $match !== '' && $match[0] !== '#';
}));

$batchSize   = max(1, (int)($options['b'] ?? 1));
$totalMatches = count($matches);

$ctx = lrg_parallel_init_context();
lrg_parallel_queue_init($ctx, $matches);

lrg_parallel_log(
  $ctx,
  "[ ] OpenDota cooldown: ".number_format($cooldownSeconds, 2)
    ." s, workers: $workers, batch: $batchSize, total: $totalMatches\n"
);

$exitCode = lrg_parallel_run_queue(
  $ctx,
  $workers,
  function (array &$ctx, int $workerIdx, int $totalWorkers)
    use ($batchSize, $cooldownMs, $odapikey, $useApiKey, $totalMatches)
  {
    $opendota = $useApiKey
      ? new \SimpleOpenDotaPHP\odota_api(false, "", $cooldownMs, $odapikey)
      : new \SimpleOpenDotaPHP\odota_api(false, "", $cooldownMs);

    while (true) {
      $batch = lrg_parallel_queue_pop($ctx, $batchSize);
      if (empty($batch)) break;

      foreach ($batch as $match) {
        $seq = lrg_parallel_alloc_seq($ctx);
        lrg_parallel_log(
          $ctx,
          "[ ] #$seq/$totalMatches w".($workerIdx + 1)."/$totalWorkers requesting match $match\n"
        );
        ob_start();
        // Mode 1 avoids recursive retries (mode 0 can hang on repeated API errors).
        $res    = $opendota->request_match($match, 1);
        $libOut = trim((string)ob_get_clean());
        if ($libOut !== '') lrg_parallel_log($ctx, $libOut."\n");

        if ($res === false) {
          lrg_parallel_log($ctx, "[E] #$seq/$totalMatches match $match request failed\n");
          lrg_parallel_failures_add($ctx, [$match]);
        } else {
          lrg_parallel_log($ctx, "[S] #$seq/$totalMatches match $match requested\n");
        }

        if ($cooldownMs > 0) usleep($cooldownMs * 1000);
      }
    }
  }
);

$failures  = lrg_parallel_failures_get($ctx);
$failCount = count($failures);
if ($failCount > 0) {
  lrg_parallel_log($ctx, "[!] $failCount / $totalMatches match(es) failed:\n");
  foreach ($failures as $m) {
    lrg_parallel_log($ctx, "    $m\n");
  }
} else {
  lrg_parallel_log($ctx, "[S] All $totalMatches match(es) requested successfully.\n");
}

lrg_parallel_cleanup($ctx);
exit($exitCode);

?>
