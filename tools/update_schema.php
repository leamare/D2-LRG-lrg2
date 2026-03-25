<?php

declare(strict_types=1);

require_once __DIR__ . '/../head.php';

if (!isset($lrg_league_tag)) {
  die("[F] Pass league tag: php tools/update_schema.php -l<tag>\n");
}

$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);
if ($conn->connect_error) {
  die("[F] Connection to SQL server failed: ".$conn->connect_error."\n");
}
$conn->set_charset('utf8mb4');

require_once __DIR__ . '/../modules/commons/schema_sql.php';
require_once __DIR__ . '/../modules/commons/fantasy_mvp.php';

$teams_enabled = false;
$fantasy_enabled = false;
$league_file = __DIR__ . '/../leagues/' . $lrg_league_tag . '.json';
if (is_readable($league_file)) {
  $lg_settings = json_decode((string)file_get_contents($league_file), true);
  if (is_array($lg_settings)) {
    $teams_enabled = !empty($lg_settings['main']['teams']);
    $fantasy_enabled = !empty($lg_settings['main']['fantasy']);
  }
}

/** @return array<string, mixed> */
function lrg_load_report_schema(mysqli $conn): array {
  global $schema;
  $schema_quiet = true;
  include __DIR__ . '/../modules/commons/schema.php';
  return $schema;
}

$migration_order = [
  'matches_opener',
  'adv_matchlines_roles',
  'players_fixname',
  'draft_order',
  'starting_items',
  'skill_builds',
  'skill_build_attr',
  'starting_consumables',
  'wards',
  'variant_supported',
  'fantasy_mvp',
];

$tables = lrg_existing_tables($conn);

foreach (lrg_schema_core_table_creates() as [$file, $table]) {
  if (!isset($tables[strtolower($table)])) {
    echo "[ ] Init missing table `$table` ($file)\n";
    lrg_run_init_sql($conn, $file);
  }
}

if ($teams_enabled) {
  foreach (lrg_schema_teams_table_creates() as [$file, $table]) {
    if (!isset($tables[strtolower($table)])) {
      echo "[ ] Init missing table `$table` ($file)\n";
      lrg_run_init_sql($conn, $file);
    }
  }
}

if ($fantasy_enabled) {
  foreach (lrg_schema_fantasy_table_creates() as [$file, $table]) {
    if (!isset($tables[strtolower($table)])) {
      echo "[ ] Init missing table `$table` ($file)\n";
      lrg_run_init_sql($conn, $file);
    }
  }
}

foreach (lrg_schema_main_foreign_keys() as [$file, $cname]) {
  if (!lrg_fk_constraint_exists($conn, $cname)) {
    echo "[ ] Add foreign key `$cname` ($file)\n";
    try {
      lrg_run_init_sql($conn, $file);
    } catch (RuntimeException $e) {
      echo $e->getMessage();
    }
  }
}

if ($teams_enabled) {
  foreach (lrg_schema_teams_foreign_keys() as [$file, $cname]) {
    if (!lrg_fk_constraint_exists($conn, $cname)) {
      echo "[ ] Add foreign key `$cname` ($file)\n";
      try {
        lrg_run_init_sql($conn, $file);
      } catch (RuntimeException $e) {
        echo $e->getMessage();
      }
    }
  }
}

$schema = lrg_load_report_schema($conn);

foreach ($migration_order as $param) {
  if (!empty($schema[$param])) {
    continue;
  }
  if ($param === 'fantasy_mvp') {
    if (!$fantasy_enabled) {
      continue;
    }
    echo "[ ] Migration fantasy_mvp (init SQL)\n";
    create_fantasy_mvp_tables($conn);
  } else {
    $from_init = lrg_schema_migrations_from_init_tables();
    if (isset($from_init[$param])) {
      echo "[ ] Migration $param (init table {$from_init[$param]})\n";
      lrg_run_init_sql($conn, $from_init[$param]);
    } else {
      $rel = $param . '.sql';
      $path = lrg_schema_migrations_path($rel);
      if (!is_readable($path)) {
        die("[F] Missing migration: schema/migrations/$rel\n");
      }
      echo "[ ] Migration $param\n";
      lrg_run_migration_sql($conn, $rel);
    }
  }

  $posthook = lrg_schema_migrations_path($param . '_posthook.sql');
  if (is_readable($posthook) && trim((string)file_get_contents($posthook)) !== '') {
    echo "[ ] Posthook {$param}_posthook.sql\n";
    lrg_run_schema_sql_file($conn, $posthook);
  }

  $schema = lrg_load_report_schema($conn);
}

echo "OK\n";
