<?php
require_once('head.php');

include __DIR__ . "/../modules/commons/metadata.php";

$meta = new lrg_metadata;

$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);

include_once("modules/commons/schema.php");

if ($schema['variant']) die("OK\n");

$patches = $meta->facets['id_shifts'];
// krsort($patches);

foreach ($patches as $id => $facets) {
  $hid = null;
  foreach ($facets as $facet => $data) {
    foreach ($meta->facets['heroes'] as $id => $h) {
      foreach ($h as $f) {
        if ($f['name'] == $facet) {
          $hid = $id;
          break;
        }
      }
    }

    $sql = "UPDATE matchlines SET variant = {$data['new']} WHERE variant = {$data['old']} AND hero_id = {$hid};";
    
    if ($conn->multi_query($sql) === TRUE);
    else echo("[F] Unexpected problems when quering database.\n".$conn->error."\n");
  }
}

$sql = "ALTER TABLE matchlines ADD variant SMALLINT UNSIGNED DEFAULT null NULL;";

if ($conn->multi_query($sql) === TRUE);
else echo("[F] Unexpected problems when quering database.\n".$conn->error."\n");

do {
  $conn->store_result();
} while($conn->next_result());