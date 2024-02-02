<?php
require_once('head.php');
$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass);

if ($conn->connect_error) die("[F] Connection to SQL server failed: ".$conn->connect_error."\n");

$reports = [];

$query = "show databases like \"$lrg_db_prefix%\";";
$strlen = strlen($lrg_db_prefix);

if ($conn->multi_query($query) !== TRUE) 
  throw new Exception("[F] Unexpected problems when recording to database.\n".$conn->error."\n");

$query_res = $conn->store_result();
for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  $reports[] = substr($row[0], $strlen+1);
}
$query_res->free_result();

$descriptors = scandir("leagues");

$toremove = [];

foreach ($descriptors as $lf) {
  if ($lf[0] == '.' || strpos($lf, ".json") === false) {
    continue;
  }

  $lm = substr($lf, 0, -5);

  if (!in_array($lm, $reports)) {
    echo "[X] database for report `$lm` does not exist\n";
    $toremove[] = $lm;
  } else {
    echo "[ ] `$lm` exists\n";
  }
}

echo "\n";

if (empty($toremove)) {
  echo "[S] Nothing to remove, cleanup complete.\n";
  exit();
}

echo "[?] There are ".count($toremove)." descriptors to remove. Proceed?\n";
$ans = readline(">>> ");

if ($ans[0] == "y" || $ans[0] == "Y" || $ans[0] == "1" || strpos($ans, "д") === 0 || strpos($ans, "Д") === 0) {
  foreach ($toremove as $lm) {
    if (file_exists("leagues/$lm.json")) unlink("leagues/$lm.json");
    if (file_exists("matchlists/$lm.list")) unlink("matchlists/$lm.list");

    echo "[-] removed $lm\n";
  }
} else {
  die("[ ] Whatever.\n");
}

echo "[S] Cleanup complete.\n";