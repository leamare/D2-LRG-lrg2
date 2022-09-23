<?php
$modules['recordext'] = "";

function rg_view_generate_records_ext() {
  global $report, $root;
  include("generators/records.php");

  $res = rg_generator_records_ext($report['records'], $report['records_ext']);

  return $res;
}