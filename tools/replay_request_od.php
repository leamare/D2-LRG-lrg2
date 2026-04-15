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

$ctx = lrg_parallel_init_context();
lrg_parallel_log($ctx, "[ ] OpenDota cooldown: ".number_format($cooldownSeconds, 2)." s, workers: $workers\n");

$exitCode = lrg_parallel_run($matches, $workers, function ($chunk) use (&$ctx, $cooldownMs, $odapikey, $useApiKey) {
  if($useApiKey) {
    $opendota = new \SimpleOpenDotaPHP\odota_api(false, "", $cooldownMs, $odapikey);
  } else {
    $opendota = new \SimpleOpenDotaPHP\odota_api(false, "", $cooldownMs);
  }

  foreach ($chunk as $match) {
    lrg_parallel_alloc_seq($ctx);
    ob_start();
    // Mode 1 avoids recursive retries inside the library (mode 0 can hang forever
    // on repeated API errors). We throttle manually per worker below.
    $res = $opendota->request_match($match, 1);
    if ($res === false) {
      echo "[E] Request failed for match $match\n";
    }
    lrg_parallel_log($ctx, (string)ob_get_clean());
    if ($cooldownMs > 0) {
      usleep($cooldownMs * 1000);
    }
  }
});

lrg_parallel_log($ctx, "[S] All matches were requested.\n");
lrg_parallel_cleanup($ctx);
exit($exitCode);

?>
