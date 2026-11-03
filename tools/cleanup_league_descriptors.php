<?php
require_once('head.php');

$dbs_by_resource = [];
foreach (lrg_resources() as $r_name => $res) {
  if (($res['driver'] ?? 'mysql') !== 'mysql') continue;

  $prefix = (string)($res['prefix'] ?? '');
  if ($prefix !== '') {
    $like = addcslashes($prefix, '_%')."\_%";
  } elseif (!empty($res['database'])) {
    $like = addcslashes($res['database'], '_%');
  } else {
    continue;
  }

  $conn = lrg_resource_connect($res, null);
  if ($conn->connect_error) {
    echo "[W] Can't connect to resource `$r_name`: ".$conn->connect_error."\n";
    continue;
  }

  $dbs_by_resource[$r_name] = [];
  if ($conn->multi_query("show databases like \"$like\";") !== TRUE)
    throw new Exception("[F] Unexpected problems when recording to database.\n".$conn->error."\n");

  $query_res = $conn->store_result();
  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    $dbs_by_resource[$r_name][] = $row[0];
  }
  $query_res->free_result();
  $conn->close();
}

$descriptors = scandir("leagues");

$toremove = [];

foreach ($descriptors as $lf) {
  if ($lf[0] == '.' || strpos($lf, ".json") === false) {
    continue;
  }

  $lm = substr($lf, 0, -5);

  $lg = json_decode(file_get_contents("leagues/$lf"), true);
  $r_name = (string)($lg['resource'] ?? '');
  if ($r_name === '') $r_name = lrg_resource_default_name();
  $res = lrg_resource($r_name);

  if ($res === null || !isset($dbs_by_resource[$r_name])) {
    echo "[?] `$lm` — resource `$r_name` is unavailable, skipping\n";
    continue;
  }

  if (!in_array(lrg_resource_db_name($res, $lm), $dbs_by_resource[$r_name])) {
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