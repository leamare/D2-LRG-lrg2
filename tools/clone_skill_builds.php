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

  $rep['skill_builds'] = $report['skill_builds'];

  file_put_contents($s.".old", $json);
  file_put_contents($s, json_encode($rep));
}