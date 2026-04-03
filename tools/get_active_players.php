<?php

require_once('head.php');
include_once("modules/commons/utf8ize.php");
$conn = lrg_mysqli_connect($lrg_sql_db);

if ($conn->connect_error) die("[F] Connection to SQL server failed: ".$conn->connect_error."\n");

$conn->set_charset("utf8");

$sql = "SELECT playerid FROM matchlines GROUP BY playerid HAVING COUNT(*) > 1";

$query_res = $conn->query($sql);

while ($row = $query_res->fetch_assoc()) {
  echo $row['playerid']."\n";
}