<?php 

include_once("head.php");
ini_set('memory_limit', '16192M');
const DISK_CACHE_COUNTER = 25000;
const QUERY_COUNTER = 10000;

$options = getopt("l:RrFf:o:S:");

$restore = isset($options['R']);

$make_report = isset($options['r']);

$remove = isset($options['F']);

$input = $options['f'] ?? '';

$skipTables = explode(",", $options['S'] ?? '');

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
    'items',
    'draft',
    'starting_items',
    'runes',
    'skill_builds',
    'teams_matches',
    'teams',
    'teams_rosters',
  ];

  foreach ($tables as $t) {
    if (file_exists($dir.'/'.$t.'.csv')) {
      if (in_array($t, $skipTables)) continue;

      echo("[ ] Adding data to `$t`...");
      $table = $t;

      // counting lines
      $_lines = 0;
      $handle = fopen($dir.'/'.$t.'.csv', "r");
      if ($handle) {
        while (($line = fgets($handle)) !== false) {
          $_lines++;
        }
      
        fclose($handle);
      } else {
        die("Error reading the file `$t`\n");
      }
      
      $handle = fopen($dir.'/'.$t.'.csv', "r");
      $schema = trim(fgets($handle));
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

        foreach ($vals as $v) {
          if (strpos($v, ',') !== false) {
            $v = substr($v, 1, strlen($v)-2);
            $v = str_replace('""', '"', $v);
          }
          if (!is_numeric($v) && !mb_check_encoding($v, 'UTF-8')) {
            $v = mb_convert_encoding($v, 'UTF-8');
          }
          $v = trim($v);
          $qline .= "\"".addcslashes($v, '"\\')."\",";
        }
        $qline[strlen($qline)-1] = ")";

        $qlines[] = '('.$qline;
        $qcnt += $hsz;
        $_lines--;

        if ($qcnt >= QUERY_COUNTER || $_lines <= 1) {
          $sql = "INSERT INTO $t ($schema) VALUES \n".implode(",\n", $qlines).';';
          if ($conn->multi_query($sql) === TRUE);
          else {
            $fname_base = "tmp/query_{$table}_".time();
            $i = 0;
            while(file_exists(($fname = $fname_base))) {
              $fname = $fname_base.".$i";
            }
            $fname .= ".sql";

            echo "[E] ERROR: ".$conn->error."\n    Details: `$fname`\n";
            file_put_contents($fname, $sql);
          }

          $qcnt = 0;
          $qlines = [];
        }
      }
      fclose($handle);
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
  $lrg_sql_db   = $lrg_db_prefix."_".$lrg_league_tag;
  $conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);
  $conn->set_charset('utf8mb4');
  $conn->query("set names utf8mb4");
  
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
    if (in_array($t, $skipTables)) continue;
    
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
    $fname = 'tmp/'.$t.'_'.$lrg_league_tag.'_'.time().'.csv';
    $files[$t.'.csv'] = $fname;

    $fp = fopen($fname, "w+");
    
    // fwrite($fp, pack("CCC",0xef,0xbb,0xbf)); 
    fwrite($fp, implode(',', $schema)."\n");

    
    for ($off = 0; ; $off++) {
      $sql = "SELECT * FROM $t LIMIT ".(DISK_CACHE_COUNTER*$off).", ".DISK_CACHE_COUNTER.";";

      if ($conn->multi_query($sql) === FALSE)
        die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

      $query_res = $conn->store_result();

      if (!$query_res->num_rows) {
        break;
      }

      for ($i = 1, $row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row(), $i++) {
        $els = [];

        foreach ($row as $r) {
          if (strpos($r, ',') !== false || (!empty($r) && $r[0] == '"'))
            $els[] = '"'.str_replace('"', '""', $r).'"';
          else 
            $els[] = $r;
        }
        fwrite($fp, implode(',', $els)."\n");

        unset($row);
        unset($els);

        if ($i == DISK_CACHE_COUNTER) {
          fflush($fp);
          echo '~';
          $i = 0;
          // $fp = fopen($fname, "w+");
        }
      }

      $query_res->free_result();

      if ($i > 1) {
        break;
      }
    }

    if ($i != DISK_CACHE_COUNTER) {
      fclose($fp);
    }

    echo "OK.\n";
    
    $conn->close();
    $conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);
    $conn->set_charset('utf8mb4');
  }

  unset($lines);
  unset($buffer);
  $conn->close();

  if (!file_exists("leagues/$lrg_league_tag.json")) {
    $descriptor = json_decode(file_get_contents("templates/default.json"), true);
    $descriptor['league_tag'] = $lrg_league_tag;
    $descriptor['league_desc'] = '';
    $descriptor['league_name'] = $lrg_league_tag;
    $descriptor['version'] = $lrg_version;
    file_put_contents("leagues/$lrg_league_tag.json", json_encode($descriptor));
  }

  $descriptor = json_decode(file_get_contents("leagues/$lrg_league_tag.json"), true);
  $out_tag = $descriptor['league_tag'];

  if ($make_report) {
    echo "[ ] Generating report...";
    exec("php rg_analyzer.php -l$lrg_league_tag");
    $files['report.json'] = "reports/report_$out_tag.json";
    echo "OK.\n";
  }

  if (file_exists("reports/report_$out_tag.json")) {
    echo "[ ] Adding report file...";
    $files['report.json'] = "reports/report_$out_tag.json";
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
    try {
      $a->addFile($l, $n);
    } catch (\Throwable $e) {
      echo "\n[E] Couldn't pack file `$n`: ".$e->getMessage()."\n";
      echo "\t...";
    }
  }
  
  $a->compress(Phar::GZ);
  unlink($output_path);
  echo "OK\n";

  echo "[ ] Cleaning up...";
  foreach ($files as $n => $l) {
    if (strpos($l, '.csv') === false && !$remove) continue;
    unlink($l);
  }
  echo "OK.\n";
  echo "[S] Result saved as `$output_path`\n";
}
