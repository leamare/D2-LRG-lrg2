<?php 

require("modules/commons/wrap_data.php");

$input = $argv[1];
$sources = [];

for ($i=2; isset($argv[$i]); $i++) {
  $sources[] = $argv[$i];
}

$src = file_get_contents($input);
$report = json_decode($src, true);

if (empty($report)) die("No such file");

$source_reports = [];
foreach ($sources as $s) {
  $json = file_get_contents($s);
  $rep = json_decode($json, true);
  if (!isset($rep['starting_items'])) {
    $rep['starting_items'] = [];
  }

  $rep['starting_items']['items'] = $report['starting_items']['items'];
  $rep['starting_items']['builds'] = $report['starting_items']['builds'];
  // $rep['starting_items']['matches'] = $report['starting_items']['matches'];

  file_put_contents($s.".old", $json);
  file_put_contents($s, json_encode($rep));
}