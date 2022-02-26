<?php 

include_once("head.php");
ini_set('memory_limit', '16192M');
const DISK_CACHE_COUNTER = 25000;
const QUERY_COUNTER = 10000;

$options = getopt("l:f:t:");

$input = $options['f'] ?? '';

$table = $options['t'] ?? '';


if (empty($input) || !file_exists($input))
  die("[E] Can't restore without backup!\n");

$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);

echo("[ ] Restoring $input to $lrg_league_tag\n");

$conn->set_charset('utf8mb4');

$matches = [];

$sql = "SELECT DISTINCT matchid FROM $table;";

if ($conn->multi_query($sql) === TRUE) echo "[S] Requested MatchIDs.\n";
else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n".$sql."\n");

$query_res = $conn->store_result();

for ($matches = [], $row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  $matches[] = $row[0];
}

$query_res->free_result();

$t = $table;
echo("[ ] Adding data to `$t`...");

// counting lines
$_lines = 0;
$handle = fopen($input, "r");
if ($handle) {
  while (($line = fgets($handle)) !== false) {
    $_lines++;
  }

  fclose($handle);
} else {
  die("Error reading the file `$t`\n");
}

$handle = fopen($input, "r");
$schema = fgets($handle);
$_lines--;

$qlines = [];
$qcnt = 0;
$hsz = count(explode(',', $schema));

while (($line = fgets($handle)) !== false) {
  if (empty($line)) continue;

  $qline = "";
  $_vals = explode(',', $line);

  $vals = []; $jstr = false;
  foreach ($_vals as $v) {
    if ($jstr) {
      $vals[ count($vals)-1 ] .= ','.$v;
      if (!empty($v) && $v[strlen($v)-1] == '"') {
        $jstr = false;
      }
    } else {
      if (!empty($v) && $v[0] == '"') {
        $jstr = true;
      }
      $vals[] = $v;
    }
  }

  $_lines--;

  if (in_array($vals[0], $matches)) {
    continue;
  }

  foreach ($vals as $v) {
    if (strpos($v, ',') !== false) {
      $v = substr($v, 1, strlen($v)-2);
      $v = str_replace('""', '"', $v);
    }
    if (!is_numeric($v) && !mb_check_encoding($v, 'UTF-8')) {
      $v = mb_convert_encoding($v, 'UTF-8');
    }
    $qline .= "\"".addcslashes($v, '"\\')."\",";
  }
  $qline[strlen($qline)-1] = ")";

  $qlines[] = '('.$qline;
  $qcnt += $hsz;

  if ($qcnt >= QUERY_COUNTER || $_lines <= 1) {
    $sql = "INSERT INTO $t ($schema) VALUES \n".implode(",\n", $qlines).';';
    if ($conn->multi_query($sql) === TRUE);
    else {
      echo "[E] ERROR: ".$conn->error."\n    Details: `tmp/query_".time().".sql`\n";
      file_put_contents('tmp/query_'.time().'.sql', $sql);
    }

    $qcnt = 0;
    $qlines = [];
  }
}
fclose($handle);
echo "OK.\n";