<?php

$desc_file = $argv[1] ?? "";
$report_file = $argv[2] ?? "";
$output_file = $argv[3] ?? $report_file;

if (empty($desc_file)) {
  die("[F] Descriptor source is not set\n");
}
if (empty($report_file)) {
  die("[F] Descriptor source is not set\n");
}

$desc = json_decode(file_get_contents($desc_file), true);

if (empty($desc)) {
  die("[F] Incorrect descriptor provided\n");
}

$rep = json_decode(file_get_contents($report_file), true);

if (empty($rep)) {
  die("[F] Incorrect report provided\n");
}

$rep["league_name"]  = $desc['league_name'];
$rep["league_desc"] = $desc['league_desc'];
$rep['league_id'] = $desc['league_id'];
$rep["league_tag"] = $desc['league_tag'];

$rep["sponsors"] = $desc['sponsors'] ?? null;
$rep["orgs"] = $desc['orgs'] ?? null;
$rep["links"] = $desc['links'] ?? null;

$rep["localized"] = $desc['localized'] ?? null;

file_put_contents($output_file, json_encode($rep, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));