<?php
require_once('head.php');

$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);

include_once("modules/commons/schema.php");

if ($schema['variant']) die("OK\n");

$sql = "ALTER TABLE matchlines ADD variant SMALLINT UNSIGNED DEFAULT null NULL;";

if ($conn->multi_query($sql) === TRUE);
else echo("[F] Unexpected problems when quering database.\n".$conn->error."\n");

do {
  $conn->store_result();
} while($conn->next_result());