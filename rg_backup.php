<?php 

include_once("head.php");
include_once("modules/commons/streaming_archive.php");

// Streaming dump keeps a bounded working set; 2G is enough even for wide JSON rows.
ini_set('memory_limit', '2048M');
const DISK_CACHE_COUNTER = 25000;
const QUERY_COUNTER = 10000;
const BACKUP_WRITE_BUFFER = 262144;
const BACKUP_PROGRESS_EVERY = 2000;
const BACKUP_MATCHID_BATCH = 250;
const BACKUP_SOCKET_TIMEOUT = 28800;
const BACKUP_LOG_PCT_STEP = 5;
const BACKUP_LOG_PCT_EVERY_CHUNKS = 10;

if (function_exists('ob_implicit_flush')) {
  ob_implicit_flush(true);
}
while (ob_get_level() > 0) {
  ob_end_flush();
}

function backup_connect($database) {
  $conn = lrg_mysqli_connect($database);
  if ($conn->connect_error) {
    die("[F] DB connect failed: ".$conn->connect_error."\n");
  }
  $conn->set_charset('utf8mb4');
  $conn->query("SET NAMES utf8mb4");
  $timeout = (int)BACKUP_SOCKET_TIMEOUT;
  $conn->query("SET SESSION net_read_timeout=$timeout");
  $conn->query("SET SESSION net_write_timeout=$timeout");
  $conn->query("SET SESSION wait_timeout=$timeout");
  @ini_set('default_socket_timeout', (string)$timeout);
  @ini_set('mysqlnd.net_read_timeout', (string)$timeout);
  return $conn;
}

function backup_stdout_is_tty() {
  static $tty = null;
  if ($tty !== null) {
    return $tty;
  }
  if (!defined('STDOUT') || !is_resource(STDOUT)) {
    $tty = false;
    return $tty;
  }
  if (function_exists('stream_isatty')) {
    $tty = @stream_isatty(STDOUT);
    return $tty;
  }
  if (function_exists('posix_isatty')) {
    $tty = @posix_isatty(STDOUT);
    return $tty;
  }
  $tty = true;
  return $tty;
}

function backup_fflush() {
  if (defined('STDOUT') && is_resource(STDOUT)) {
    fflush(STDOUT);
  }
}

function backup_progress($prefix, $done, $total = 0, $final = false, $approx = true, $chunk = false) {
  static $last_len = 0;
  static $last_t = 0.0;
  static $last_prefix = '';
  static $last_printed_pct = 0.0;
  static $tildes_since_pct = 0;
  static $opened = false;
  static $last_tilde_done = -1;

  $now = microtime(true);
  $tty = backup_stdout_is_tty();
  $done = (int)$done;
  $total = (int)$total;
  $pct = ($total > 0) ? ($final ? 100.0 : min(99.9, 100.0 * $done / $total)) : null;

  if ($prefix !== $last_prefix) {
    $last_printed_pct = 0.0;
    $tildes_since_pct = 0;
    $last_len = 0;
    $opened = false;
    $last_tilde_done = -1;
  }

  if ($tty) {
    if (!$final && $prefix === $last_prefix && ($now - $last_t) < 0.2) {
      return;
    }

    $done_s = number_format($done);
    if ($total > 0) {
      $count = $final ? $done_s : $done_s.($approx ? ' / ~' : ' / ').number_format($total);
      $line = sprintf("%s %5.1f%% (%s)", $prefix, $pct, $count);
    } else {
      $line = sprintf("%s %s", $prefix, $done_s);
    }
    if ($final) {
      $line .= " OK.";
    }

    $pad = $last_len > strlen($line) ? str_repeat(' ', $last_len - strlen($line)) : '';
    echo "\r".$line.$pad;
    $last_len = strlen($line);
    $last_t = $now;
    $last_prefix = $prefix;
    if ($final) {
      echo "\n";
      $last_len = 0;
      $last_prefix = '';
      $opened = false;
      $last_printed_pct = 0.0;
      $tildes_since_pct = 0;
      $last_tilde_done = -1;
    }
    backup_fflush();
    return;
  }

  // Redirected / nohup: one growing line per table — ~ per chunk,
  // a % every 5%, or every N chunks if 1% would be a long run of tildes.
  if (!$opened) {
    echo $prefix;
    $opened = true;
    $last_prefix = $prefix;
  }

  $emitted_tilde = false;
  if ($chunk && !$final && $done > 0 && $done !== $last_tilde_done) {
    echo '~';
    $last_tilde_done = $done;
    $tildes_since_pct++;
    $emitted_tilde = true;
  }

  if (!$final && $pct !== null) {
    $step = BACKUP_LOG_PCT_STEP;
    $target5 = (int)(floor($pct / $step) * $step);
    if ($target5 >= $step && $target5 < 100) {
      $n = (int)(floor($last_printed_pct / $step) * $step) + $step;
      while ($n <= $target5 && $n < 100) {
        echo $n.'%';
        $last_printed_pct = $n;
        $n += $step;
        $tildes_since_pct = 0;
      }
    }

    if (
      $emitted_tilde
      && $tildes_since_pct >= BACKUP_LOG_PCT_EVERY_CHUNKS
      && $pct < 100
    ) {
      $int = (int)floor($pct);
      if ($int > (int)$last_printed_pct && $int < 100) {
        echo $int.'%';
        $last_printed_pct = $int;
        $tildes_since_pct = 0;
      } elseif ($pct > $last_printed_pct + 0.09) {
        echo sprintf('%.1f%%', $pct);
        $last_printed_pct = $pct;
        $tildes_since_pct = 0;
      }
    }
  }

  if ($final) {
    echo " OK.\n";
    $last_len = 0;
    $last_prefix = '';
    $opened = false;
    $last_printed_pct = 0.0;
    $tildes_since_pct = 0;
    $last_tilde_done = -1;
  }

  $last_t = $now;
  backup_fflush();
}

function backup_table_row_estimate(mysqli $conn, $db, $table) {
  $db_e = $conn->real_escape_string($db);
  $t_e = $conn->real_escape_string($table);
  $res = $conn->query(
    "SELECT TABLE_ROWS FROM information_schema.TABLES ".
    "WHERE TABLE_SCHEMA='{$db_e}' AND TABLE_NAME='{$t_e}'"
  );
  if (!$res) {
    return 0;
  }
  $row = $res->fetch_row();
  $res->free();
  return (int)($row[0] ?? 0);
}

function backup_csv_line(array $row) {
  $els = [];
  foreach ($row as $r) {
    if ($r === null) {
      $els[] = '';
      continue;
    }
    if (strpos($r, ',') !== false || (isset($r[0]) && $r[0] == '"')) {
      $els[] = '"'.str_replace('"', '""', $r).'"';
    } else {
      $els[] = $r;
    }
  }
  return implode(',', $els)."\n";
}

function backup_ident($name) {
  return '`'.str_replace('`', '``', $name).'`';
}

function backup_sql_value(mysqli $conn, $v) {
  if ($v === null) {
    return 'NULL';
  }
  if (is_int($v) || is_float($v) || (is_string($v) && preg_match('/^-?\d+(\.\d+)?$/', $v))) {
    return $v;
  }
  return "'".$conn->real_escape_string((string)$v)."'";
}

function backup_col_index(array $schema, $col) {
  foreach ($schema as $i => $name) {
    if (strcasecmp($name, $col) === 0) {
      return $i;
    }
  }
  return false;
}

/**
 * How to page a table without OFFSET.
 * unique / unique_tuple: keyset on PRIMARY/UNIQUE (index range, not a full skip).
 * group: DISTINCT matchid windows — used when there is no unique key (items, draft, …).
 * scan: last resort, one unbuffered SELECT * (small leftover tables).
 */
function backup_table_chunk_spec(mysqli $conn, $table, array $schema) {
  $res = $conn->query("SHOW INDEX FROM ".backup_ident($table));
  if ($res === FALSE) {
    return ['mode' => 'scan'];
  }

  $indexes = [];
  while ($row = $res->fetch_assoc()) {
    $name = $row['Key_name'];
    if (!isset($indexes[$name])) {
      $indexes[$name] = [
        'unique' => ((int)$row['Non_unique'] === 0),
        'cols' => [],
      ];
    }
    $indexes[$name]['cols'][(int)$row['Seq_in_index']] = $row['Column_name'];
  }
  $res->free();

  foreach ($indexes as &$idx) {
    ksort($idx['cols']);
    $idx['cols'] = array_values($idx['cols']);
  }
  unset($idx);

  if (isset($indexes['PRIMARY']) && count($indexes['PRIMARY']['cols']) === 1) {
    return ['mode' => 'unique', 'cols' => $indexes['PRIMARY']['cols']];
  }
  foreach ($indexes as $idx) {
    if ($idx['unique'] && count($idx['cols']) === 1) {
      return ['mode' => 'unique', 'cols' => $idx['cols']];
    }
  }
  if (isset($indexes['PRIMARY'])) {
    return ['mode' => 'unique_tuple', 'cols' => $indexes['PRIMARY']['cols']];
  }
  foreach ($indexes as $idx) {
    if ($idx['unique']) {
      return ['mode' => 'unique_tuple', 'cols' => $idx['cols']];
    }
  }

  foreach ($schema as $col) {
    if (strcasecmp($col, 'matchid') === 0) {
      return ['mode' => 'group', 'cols' => [$col]];
    }
  }

  return ['mode' => 'scan'];
}

function backup_row_key(array $row, array $schema, array $spec) {
  $vals = [];
  foreach ($spec['cols'] as $col) {
    $i = backup_col_index($schema, $col);
    $vals[] = ($i === false) ? null : $row[$i];
  }
  return $vals;
}

function backup_reconnect($database, ?mysqli $conn = null) {
  if ($conn) {
    @$conn->close();
  }
  if (function_exists('gc_collect_cycles')) {
    gc_collect_cycles();
  }
  return backup_connect($database);
}

function backup_is_gone_away($error) {
  return (bool)preg_match('/gone away|lost connection|timeout|reset by peer|server has gone/i', $error);
}

/**
 * Fetch one unbuffered result into the CSV, updating $buf / $i / $last_key.
 * Returns [rows_in_chunk, last_key, ok].
 */
function backup_fetch_chunk(mysqli $conn, $sql, array $schema, array $spec, $fp, &$buf, &$i, $last_key, $prefix = '', $estimate = 0) {
  $query_res = $conn->query($sql, MYSQLI_USE_RESULT);
  if ($query_res === FALSE) {
    return [0, $last_key, false, $conn->error];
  }

  $chunk_rows = 0;
  $new_last = $last_key;
  while ($row = $query_res->fetch_row()) {
    $buf .= backup_csv_line($row);
    $i++;
    $chunk_rows++;
    if ($spec['mode'] !== 'scan') {
      $new_last = backup_row_key($row, $schema, $spec);
    }
    if (strlen($buf) >= BACKUP_WRITE_BUFFER) {
      fwrite($fp, $buf);
      $buf = '';
    }
    if ($prefix !== '') {
      $chunk_tick = ($i % DISK_CACHE_COUNTER === 0);
      if ($chunk_tick || $i % BACKUP_PROGRESS_EVERY === 0) {
        backup_progress($prefix, $i, $estimate, false, true, $chunk_tick);
      }
    }
  }
  $err = $conn->error;
  $query_res->free();
  if ($err) {
    return [$chunk_rows, $new_last, false, $err];
  }
  return [$chunk_rows, $new_last, true, ''];
}

function backup_reassemble_split_files($dir) {
  $entries = scandir($dir);
  if ($entries === false) return;

  $parts_by_base = [];
  foreach ($entries as $f) {
    if (preg_match('/^(.+)\.(\d{4,})$/', $f, $m)) {
      $parts_by_base[$m[1]][(int)$m[2]] = $f;
    }
  }

  foreach ($parts_by_base as $base => $parts) {
    ksort($parts);
    if (array_keys($parts) !== range(0, count($parts) - 1)) continue;
    if (file_exists($dir.'/'.$base)) continue;

    $out = fopen($dir.'/'.$base, 'wb');
    if ($out === false) continue;

    foreach ($parts as $part_name) {
      $in = fopen($dir.'/'.$part_name, 'rb');
      stream_copy_to_stream($in, $out);
      fclose($in);
    }
    fclose($out);

    foreach ($parts as $part_name) {
      unlink($dir.'/'.$part_name);
    }
  }
}

function backup_run_chunk(mysqli &$conn, $database, $sql, array $schema, array $spec, $fp, &$buf, &$i, $last_key, $table, $prefix = '', $estimate = 0) {
  if ($buf !== '') {
    fwrite($fp, $buf);
    $buf = '';
  }
  $mark_i = $i;
  $mark_pos = ftell($fp);

  for ($attempt = 1; $attempt <= 3; $attempt++) {
    [$chunk_rows, $new_last, $ok, $err] = backup_fetch_chunk(
      $conn, $sql, $schema, $spec, $fp, $buf, $i, $last_key, $prefix, $estimate
    );
    if ($ok) {
      return [$chunk_rows, $new_last];
    }

    $buf = '';
    $i = $mark_i;
    if ($mark_pos !== false) {
      ftruncate($fp, $mark_pos);
      fseek($fp, $mark_pos);
    }
    echo "\n[W] Dump of `$table` interrupted".($err ? " ($err)" : "").", retry $attempt/3\n";
    $conn = backup_reconnect($database, $conn);
    if ($attempt === 3 || ($err && !backup_is_gone_away($err) && stripos($err, 'interrupted') === false)) {
      die("[F] Unexpected problems when requesting database.\n$err\n");
    }
    sleep($attempt);
  }

  die("[F] Unexpected problems when requesting database.\n");
}

$options = getopt("l:RrFf:o:S:");

$restore = isset($options['R']);

$make_report = isset($options['r']);

$remove = isset($options['F']);

$input = $options['f'] ?? '';

$skipTables = array_values(array_filter(explode(",", $options['S'] ?? ''), 'strlen'));

$output_path = $options['o'] ?? 'backups/'.$lrg_league_tag.'_'.time().'.tar.gz';
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

  if ($remove || is_dir($input)) {
    $dir = $input;
    backup_reassemble_split_files($dir);
  } else {
    echo("[ ] Unpacking...\n");
    $a = new PharData($input);
    $dir = '_restore_'.$lrg_league_tag;
    mkdir($dir);
    $a->extractTo($dir);
    backup_reassemble_split_files($dir);
  }

  echo("[ ] Initializing db...\n");
  exec("php rg_init.php -l$lrg_league_tag -Nq -Dq");
  $lrg_sql_db   = $lrg_db_prefix."_".$lrg_league_tag;

  echo("[ ] Copying descriptor and matchlist...\n");
  copy($dir.'/descriptor.json', 'leagues/'.$lrg_league_tag.'.json');
  copy($dir.'/matchlist.list', 'matchlists/'.$lrg_league_tag.'.list');

  $conn = backup_connect($lrg_sql_db);

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
    'teams',
    'teams_matches',
    'teams_rosters',
    'fantasy_mvp_points',
    'fantasy_mvp_awards',
  ];

  foreach ($tables as $t) {
    if (file_exists($dir.'/'.$t.'.csv')) {
      if (in_array($t, $skipTables)) continue;

      $table = $t;
      $restore_prefix = "[ ] Adding data to `$t`...";

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
      $restore_total = $_lines;
      $restore_done = 0;
      backup_progress($restore_prefix, 0, $restore_total, false, false);

      $qlines = [];
      $qcnt = 0;
      $hsz = count(explode(',', $schema));

      $schema = '`'.implode('`,`', explode(',', $schema)).'`';

      while (($line = fgets($handle)) !== false) {
        if (empty($line)) continue;

        $qline = "";
        $_vals = explode(',', trim($line));

        $vals = []; $jstr = false;
        foreach ($_vals as $v) {
          if ($jstr) {
            $vals[ count($vals)-1 ] .= ','.$v;
            if (!empty($v) && $v[strlen($v)-1] == '"') {
              $jstr = false;
            }
          } else {
            if (!empty($v) && $v[0] == '"' && ((strlen($v) == 1) || ($v[strlen($v)-1] != '"'))) {
              $jstr = true;
            }
            $vals[] = $v;
          }
        }

        foreach ($vals as $v) {
          if (empty($v)) {
            $qline .= "'0',";
            continue;
          }
          if (strpos($v, ',') !== false) {
            // $v = substr($v, 1, strlen($v)-2);
            $v = str_replace('""', '"', $v);
            $v = substr($v, 1, strlen($v)-2);
            // $v = trim($v, '"');
          }
          if (!is_numeric($v) && !mb_check_encoding($v, 'UTF-8')) {
            $v = mb_convert_encoding($v, 'UTF-8');
          }
          $v = trim($v);
          $qline .= "'".addcslashes($v, "'\\")."',";
        }
        $qline[strlen($qline)-1] = ")";

        $qlines[] = '('.$qline;
        $qcnt += $hsz;
        $_lines--;
        $restore_done++;

        if ($qcnt >= QUERY_COUNTER || $_lines <= 1) {
          $sql = "INSERT INTO $t ($schema) VALUES \n".implode(",\n", $qlines).';';
          try {
            if ($conn->multi_query($sql) === TRUE);
            else {
              throw new Exception($conn->error);
            }
          } catch (Exception $e) {
            $fname_base = "tmp/query_{$table}_".time();
            $i = 0;
            while(file_exists(($fname = $fname_base))) {
              $fname = $fname_base.".$i";
            }
            $fname .= ".sql";

            echo "\n[E] ERROR: ".$conn->error."\n    Details: `$fname`\n";
            file_put_contents($fname, $sql);

            die();
          }

          $qcnt = 0;
          $qlines = [];
          backup_progress($restore_prefix, $restore_done, $restore_total, false, false, true);
        }
      }
      fclose($handle);
      backup_progress($restore_prefix, $restore_done, $restore_total, true, false);
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
  $conn = backup_connect($lrg_sql_db);

  $tables = [];
  $files = [];

  echo "[ ] Getting tables\n";

  if (!empty($skipTables)) {
    echo "[ ] Skip tables: ".implode(', ', $skipTables)."\n";
  }

  $query_res = $conn->query("SHOW TABLES");
  if ($query_res === FALSE)
    die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

  while ($row = $query_res->fetch_row()) {
    $tables[] = $row[0];
  }
  $query_res->free();

  foreach ($tables as $t) {
    if (in_array($t, $skipTables)) continue;

    $schema = [];
    $query_res = $conn->query("SHOW COLUMNS FROM `$t`");
    if ($query_res === FALSE)
      die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");

    while ($row = $query_res->fetch_row()) {
      $schema[] = $row[0];
    }
    $query_res->free();

    $estimate = backup_table_row_estimate($conn, $lrg_sql_db, $t);
    $spec = backup_table_chunk_spec($conn, $t, $schema);
    $prefix = "[ ] Fetching `$t`...";
    backup_progress($prefix, 0, $estimate);

    $fname = 'tmp/'.$t.'_'.$lrg_league_tag.'_'.time().'.csv';
    $files[$t.'.csv'] = $fname;

    $fp = fopen($fname, "w");
    if ($fp === FALSE) {
      die("[F] Can't write `$fname`\n");
    }
    fwrite($fp, implode(',', $schema)."\n");

    $i = 0;
    $buf = '';
    $last_key = null;
    $tq = backup_ident($t);

    while (true) {
      if ($spec['mode'] === 'scan') {
        $sql = "SELECT * FROM $tq";
      } elseif ($spec['mode'] === 'group') {
        $colq = backup_ident($spec['cols'][0]);
        $id_sql = $last_key === null
          ? "SELECT DISTINCT $colq FROM $tq ORDER BY $colq ASC LIMIT ".BACKUP_MATCHID_BATCH
          : "SELECT DISTINCT $colq FROM $tq WHERE $colq > ".backup_sql_value($conn, $last_key[0]).
            " ORDER BY $colq ASC LIMIT ".BACKUP_MATCHID_BATCH;
        $id_res = $conn->query($id_sql);
        if ($id_res === FALSE) {
          echo "\n[W] Could not page `$t` ($conn->error), retrying\n";
          $conn = backup_reconnect($lrg_sql_db, $conn);
          $id_res = $conn->query($id_sql);
        }
        if ($id_res === FALSE) {
          die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
        }
        $ids = [];
        while ($id_row = $id_res->fetch_row()) {
          $ids[] = $id_row[0];
        }
        $id_res->free();
        if (!$ids) {
          break;
        }
        $sql = "SELECT * FROM $tq WHERE $colq >= ".backup_sql_value($conn, $ids[0]).
          " AND $colq <= ".backup_sql_value($conn, $ids[count($ids)-1]);
        $last_key = [end($ids)];
      } else {
        $col_sql = implode(', ', array_map('backup_ident', $spec['cols']));
        $limit = DISK_CACHE_COUNTER;
        if ($last_key === null) {
          $sql = "SELECT * FROM $tq ORDER BY $col_sql LIMIT $limit";
        } else {
          $vals = [];
          foreach ($last_key as $v) {
            $vals[] = backup_sql_value($conn, $v);
          }
          $sql = "SELECT * FROM $tq WHERE ($col_sql) > (".implode(', ', $vals).")".
            " ORDER BY $col_sql LIMIT $limit";
        }
      }

      [$chunk_rows, $new_last] = backup_run_chunk(
        $conn, $lrg_sql_db, $sql, $schema, $spec, $fp, $buf, $i, $last_key, $t, $prefix, $estimate
      );

      if ($spec['mode'] !== 'group') {
        $last_key = $new_last;
      }

      if ($buf !== '') {
        fwrite($fp, $buf);
        $buf = '';
      }
      fflush($fp);
      backup_progress($prefix, $i, $estimate, false, true, true);

      $conn = backup_reconnect($lrg_sql_db, $conn);

      if ($spec['mode'] === 'scan') {
        break;
      }
      if ($spec['mode'] === 'group') {
        if (count($ids) < BACKUP_MATCHID_BATCH) {
          break;
        }
        continue;
      }
      if ($chunk_rows < DISK_CACHE_COUNTER) {
        break;
      }
    }

    if ($buf !== '') {
      fwrite($fp, $buf);
    }
    fflush($fp);
    fclose($fp);

    backup_progress($prefix, $i, $estimate > 0 ? $estimate : $i, true);
  }

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
    $conn = lrg_mysqli_connect(null);
    echo "[ ] Removing database...";
    $sql = "DROP DATABASE $lrg_sql_db;";
    if ($conn->multi_query($sql) === FALSE)
      die("[F] Unexpected problems when requesting database.\n".$conn->error."\n");
    echo "OK.\n";
    $conn->close();
  }

  $files['descriptor.json'] = "leagues/$lrg_league_tag.json";

  echo "[ ] Packing files...\n";
  require_once("modules/commons/streaming_archive.php");
  $archive = new StreamingArchive($output_path, 6);

  foreach ($files as $n => $l) {
    $pack_prefix = "[ ] Packing `$n`...";
    $size = @filesize($l);
    if ($size === false) {
      echo "[E] Couldn't pack file `$n`: file missing\n";
      continue;
    }
    try {
      $archive->addFile($n, $l, function($pos, $total) use ($pack_prefix) {
        backup_progress($pack_prefix, $pos, $total, false, false);
      });
      backup_progress($pack_prefix, $size, $size, true, false);
    } catch (\Throwable $e) {
      echo "\n[E] Couldn't pack file `$n`: ".$e->getMessage()."\n";
    }
  }

  $archive->close();

  echo "[ ] Cleaning up...";
  foreach ($files as $n => $l) {
    if (strpos($l, '.csv') === false && !$remove) continue;
    unlink($l);
  }
  echo "OK.\n";
  echo "[S] Result saved as `$output_path`\n";
}
