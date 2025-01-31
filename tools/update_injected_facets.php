<?php

include __DIR__ . "/../modules/commons/metadata.php";

$meta = new lrg_metadata;

$report_file = $argv[1] ?? "";

if (empty($report_file)) {
  die("No report file specified\n");
}

$report_raw = file_get_contents($report_file);

$report = json_decode($report_raw, true);
if (empty($report)) {
  die("Couldn't open the report\n");
}

if (!isset($report['meta'])) {
  $report['meta'] = [];
}
$report['meta']['variants'] = [];
foreach ($meta['facets']['heroes'] as $hid => $data) {
  if (!isset($report['meta']['variants'][$hid])) {
    $report['meta']['variants'][$hid] = [];
  }
  foreach ($data as $el) {
    $report['meta']['variants'][$hid][ $el['name'] ] = [ $el['icon'], array_search($el['color'], $meta['facets']['colors']) ];
  }
}
$report['meta']['variants_colors'] = $meta['facets']['colors'];

$output = json_encode($report);

file_put_contents($report_file.".old", $report_raw);
file_put_contents($report_file, $output);