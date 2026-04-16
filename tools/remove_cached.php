#!/bin/php
<?php
include_once(__DIR__."/../modules/commons/parallel_workers.php");

$options = getopt("f:j:c:");
if(isset($options['f'])) {
  $filename = $options['f'];
  $input_cont = file_get_contents($filename) or die("[F] Error while opening file.\n");
  $input_cont = str_replace("\r\n", "\n", $input_cont);
  $matches    = explode("\n", trim($input_cont));
  $matches = array_unique($matches);
} else die();

$workers = max(1, (int)($options['j'] ?? 1));
$cacheDir = $options['c'] ?? "cache";
$cacheDir = rtrim($cacheDir, "/\\");
$ctx = lrg_parallel_init_context();
$matches = array_values(array_filter(array_map('trim', $matches), 'strlen'));

$exitCode = lrg_parallel_run($matches, $workers, function ($chunk) use (&$ctx, $cacheDir) {
  foreach ($chunk as $match) {
    if ($match === '' || $match[0] === '#') {
      continue;
    }

    $json = $cacheDir."/$match.json";
    $lrg = $cacheDir."/$match.lrgcache.json";
    if(file_exists($json)) {
      unlink($json);
    }
    if(file_exists($lrg)) {
      unlink($lrg);
    }
    lrg_parallel_log($ctx, "[ ] RM $match\n");
  }
});

lrg_parallel_log($ctx, "[S] All matches were removed.\n");
lrg_parallel_cleanup($ctx);
exit($exitCode);
