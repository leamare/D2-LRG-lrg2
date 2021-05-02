<?php 

include_once("head.php");
ini_set('memory_limit', '14192M');

$options = getopt("l:RrFf:o:");

$restore = isset($options['R']);

$make_report = isset($options['r']);

$remove = isset($options['F']);

$input = $options['f'] ?? '';

$output_path = $options['o'] ?? 'backups/'.$lrg_league_tag.'_'.time().'.tar';
if (!is_dir('backups')) mkdir('backups');

// 1. get through all the tables in the database
// (optionally) generate a report
// 2. save it into a tarball with backported matchlist and league config
// optionally - remove all data
// optionally - restore a report

if ($restore) {
  if (empty($input))
    die("[E] Can't restore without backup!\n");
  
  echo("[ ] Restoring $input to $lrg_league_tag\n");

  echo("[ ] Unpacking...\n");
  $a = new PharData($input);
  $dir = '_restore_'.$lrg_league_tag;
  mkdir($dir);
  $a->extractTo($dir);

  echo("[ ] Initializing db...\n");
  exec("php rg_init.php -l$lrg_league_tag -Nq -Dq");
  $lrg_sql_db   = $lrg_db_prefix."_".$lrg_league_tag;

  echo("[ ] Copying descriptor and matchlist...\n");
  copy($dir.'/descriptor.json', 'leagues/'.$lrg_league_tag.'.json');
  copy($dir.'/matchlist.list', 'matchlists/'.$lrg_league_tag.'.list');

  $conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);
  $conn->set_charset('utf8mb4');

  $tables = [
    'players',
    'matches',
    'matchlines',
    'adv_matchlines',
    'draft',
    'teams_matches',
    'teams',
    'teams_rosters',
  ];

  foreach ($tables as $t) {
    if (file_exists($dir.'/'.$t.'.csv')) {
      echo("[ ] Adding data to `$t`...");
      $table = $t;
      $buffer = file_get_contents($dir.'/'.$t.'.csv');
      $buffer = explode("\n", $buffer);
      $schema = $buffer[0];
      unset($buffer[0]);
      foreach ($buffer as $line) {
        $sql = "INSERT INTO $t ($schema) VALUES (";
        $vals = explode(',', $line);
        foreach ($vals as $v) {
          if (strpos($v, ',') !== false) {
            $v = substr($v, 1, strlen($v)-2);
            $v = str_replace('""', '"', $v);
          }
          $sql .= "\"".addcslashes($v, '"')."\",";
        }
        $sql[strlen($sql)-1] = ")";
        $sql .= ';';

        if ($conn->multi_query($sql) === TRUE);
        else {
          echo "[E] ERROR: ".$conn->error."\n";
        }
      }
      echo "OK.\n";
    }
  }

  echo("[ ] Cleaning up...\n");
  $files = scandir($dir);
  foreach ($files as $f) {
    if ($f[0] === '.') continue;
    unlink($dir.'/'.$f);
  } 
  rmdir($dir);
} else {
  $conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);
  $conn->set_charset('utf8mb4');
  
  $tables = [];
  $files = [];

  echo "[ ] Getting tables\n";

  $sql = "SHOW TABLES;";
  if ($conn->multi_query($sql) === FALSE)
    die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  $query_res = $conn->store_result();

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    $tables[] = $row[0];
  }

  $query_res->free_result();

  foreach ($tables as $t) {
    echo "[ ] Fetching `$t`...";
    $schema = [];

    $sql = "SHOW COLUMNS FROM $t;";
    if ($conn->multi_query($sql) === FALSE)
      die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

    $query_res = $conn->store_result();

    for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
      $schema[] = $row[0];
    }

    $query_res->free_result();

    // fetching data
    $fname = $t.'_'.time().'.csv';
    $files[$t.'.csv'] = $fname;

    $fp = fopen($fname, "w+");
    
    fwrite($fp, implode(',', $schema)."\n");

    $sql = "SELECT * FROM $t;";
    if ($conn->multi_query($sql) === FALSE)
      die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

    $query_res = $conn->store_result();

    for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
      $els = [];
      foreach ($row as $r) {
        if (strpos($r, ',') !== false)
          $els[] = '"'.str_replace('"', '""', $r).'"';
        else 
          $els[] = $r;
      }
      fwrite($fp, implode(',', $els)."\n");
    }

    $query_res->free_result();

    fclose($fp);

    echo "OK.\n";
    
    $conn->close();
    $conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);
  }

  unset($lines);
  unset($buffer);
  $conn->close();

  if ($make_report) {
    echo "[ ] Generating report...";
    exec("php rg_analyzer.php -l$lrg_league_tag");
    $files['report.json'] = "reports/report_$lrg_league_tag.json";
    echo "OK.\n";
  }

  echo "[ ] Generating matchlist...";
  exec("php tools/backport_matchlist.php -l$lrg_league_tag");
  $files['matchlist.list'] = "matchlists/$lrg_league_tag.list";
  echo "OK.\n";

  if ($remove) {
    $conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass);
    echo "[ ] Removing database...";
    $sql = "DROP DATABASE $lrg_sql_db;";
    if ($conn->multi_query($sql) === FALSE)
      die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
    echo "OK.\n";
    $conn->close();
  }

  $files['descriptor.json'] = "leagues/$lrg_league_tag.json";

  echo "[ ] Packing files...";
  $a = new PharData($output_path);
  foreach ($files as $n => $l) {
    $a->addFile($l, $n);
  }
  $a->compress(Phar::GZ);
  unlink($output_path);
  echo "OK\n";

  echo "[ ] Cleaning up...";
  foreach ($files as $n => $l) {
    if (strpos($l, '/') !== false && !$remove) continue;
    unlink($l);
  }
  echo "OK.\n";
  echo "[S] Result saved as `$output_path`\n";
}
