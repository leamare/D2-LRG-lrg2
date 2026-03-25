<?php

declare(strict_types=1);

function lrg_schema_dir(): string {
  return dirname(__DIR__, 2) . '/schema';
}

function lrg_schema_init_path(string $relative): string {
  return lrg_schema_dir() . '/init/' . $relative;
}

function lrg_schema_migrations_path(string $filename): string {
  return lrg_schema_dir() . '/migrations/' . $filename;
}

function lrg_run_schema_sql_file(mysqli $conn, string $fullPath): void {
  if (!is_readable($fullPath)) {
    throw new RuntimeException("[F] Missing schema SQL file: $fullPath\n");
  }
  $sql = file_get_contents($fullPath);
  if ($sql === false) {
    throw new RuntimeException("[F] Can't read $fullPath\n");
  }
  $sql = trim($sql);
  if ($sql === '') {
    return;
  }
  if (!$conn->multi_query($sql)) {
    throw new RuntimeException("[F] SQL error in $fullPath: ".$conn->error."\n");
  }
  do {
    if ($res = $conn->store_result()) {
      $res->free();
    }
  } while ($conn->next_result());
  if ($conn->errno) {
    throw new RuntimeException("[F] SQL error in $fullPath: ".$conn->error."\n");
  }
}

/** @param string $relative e.g. tables/matches.sql or fk/draft.sql */
function lrg_run_init_sql(mysqli $conn, string $relative): void {
  lrg_run_schema_sql_file($conn, lrg_schema_init_path($relative));
}

function lrg_run_migration_sql(mysqli $conn, string $filename): void {
  lrg_run_schema_sql_file($conn, lrg_schema_migrations_path($filename));
}

/** @return array<string, true> lowercase table names */
function lrg_existing_tables(mysqli $conn): array {
  $out = [];
  $r = $conn->query("SHOW TABLES");
  if (!$r) {
    die("[F] SHOW TABLES: ".$conn->error."\n");
  }
  while ($row = $r->fetch_row()) {
    $out[strtolower($row[0])] = true;
  }
  return $out;
}

function lrg_fk_constraint_exists(mysqli $conn, string $constraint): bool {
  $dbres = $conn->query("SELECT DATABASE()");
  if (!$dbres) {
    return false;
  }
  $dbrow = $dbres->fetch_row();
  $db = $conn->real_escape_string($dbrow[0] ?? '');
  $c = $conn->real_escape_string($constraint);
  $r = $conn->query(
    "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS ".
    "WHERE CONSTRAINT_SCHEMA='$db' AND CONSTRAINT_NAME='$c' AND CONSTRAINT_TYPE='FOREIGN KEY' LIMIT 1"
  );
  return (bool)($r && $r->num_rows > 0);
}

/**
 * Ordered init scripts for a fresh physical DB (rg_init non-virtual).
 *
 * @return list<string>
 */
function lrg_rg_init_script_list(bool $teams, bool $fantasy): array {
  $base = array_merge(
    [
      'tables/matches.sql',
      'tables/matchlines.sql',
      'tables/adv_matchlines.sql',
      'tables/draft.sql',
      'tables/items.sql',
      'tables/starting_items.sql',
      'tables/skill_builds.sql',
      'tables/wards.sql',
      'tables/players.sql',
      'tables/leagues.sql',
    ],
    [
      'fk/adv_matchlines.sql',
      'fk/matchlines.sql',
      'fk/draft.sql',
      'fk/items.sql',
      'fk/adv_matchlines_players.sql',
      'fk/matchlines_players.sql',
    ]
  );
  if ($fantasy) {
    $base = array_merge($base, [
      'tables/fantasy_mvp_points.sql',
      'tables/fantasy_mvp_awards.sql',
    ]);
  }
  if ($teams) {
    $base = array_merge($base, [
      'tables/teams.sql',
      'tables/teams_matches.sql',
      'tables/teams_rosters.sql',
      'fk/teams_matches_matches.sql',
      'fk/teams_matches_teams.sql',
      'fk/teams_rosters_teams.sql',
    ]);
  }
  return $base;
}

/** @return list<array{0: string, 1: string}> [relative path under init/, information_schema constraint name] */
function lrg_schema_main_foreign_keys(): array {
  return [
    ['fk/adv_matchlines.sql', 'adv_matchlines'],
    ['fk/matchlines.sql', 'matchlines_ibfk_1'],
    ['fk/draft.sql', 'draft'],
    ['fk/items.sql', 'items'],
    ['fk/adv_matchlines_players.sql', 'adv_matchlines_pl'],
    ['fk/matchlines_players.sql', 'matchlines_pl'],
  ];
}

/** @return list<array{0: string, 1: string}> */
function lrg_schema_teams_foreign_keys(): array {
  return [
    ['fk/teams_matches_matches.sql', 'teams_matches'],
    ['fk/teams_matches_teams.sql', 'teams_matches_teams'],
    ['fk/teams_rosters_teams.sql', 'teams_rosters_teams'],
  ];
}

/** @return list<array{0: string, 1: string}> [init sql path, table name] */
function lrg_schema_core_table_creates(): array {
  return [
    ['tables/matches.sql', 'matches'],
    ['tables/matchlines.sql', 'matchlines'],
    ['tables/adv_matchlines.sql', 'adv_matchlines'],
    ['tables/draft.sql', 'draft'],
    ['tables/items.sql', 'items'],
    ['tables/starting_items.sql', 'starting_items'],
    ['tables/skill_builds.sql', 'skill_builds'],
    ['tables/wards.sql', 'wards'],
    ['tables/players.sql', 'players'],
    ['tables/leagues.sql', 'leagues'],
  ];
}

/** @return list<array{0: string, 1: string}> */
function lrg_schema_teams_table_creates(): array {
  return [
    ['tables/teams.sql', 'teams'],
    ['tables/teams_matches.sql', 'teams_matches'],
    ['tables/teams_rosters.sql', 'teams_rosters'],
  ];
}

/** @return list<array{0: string, 1: string}> */
function lrg_schema_fantasy_table_creates(): array {
  return [
    ['tables/fantasy_mvp_points.sql', 'fantasy_mvp_points'],
    ['tables/fantasy_mvp_awards.sql', 'fantasy_mvp_awards'],
  ];
}

/**
 * Legacy $schema keys whose fix is “create the table as in init” — no separate migrations/*.sql.
 *
 * @return array<string, string> flag => init path relative to schema/init/
 */
function lrg_schema_migrations_from_init_tables(): array {
  return [
    'starting_items' => 'tables/starting_items.sql',
    'skill_builds' => 'tables/skill_builds.sql',
    'wards' => 'tables/wards.sql',
  ];
}
