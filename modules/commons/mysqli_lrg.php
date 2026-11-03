<?php

/** Named connection resources from rg_settings, built by head.php. Default: first on the list. */

function lrg_resources(): array {
  global $lrg_resources;
  return is_array($lrg_resources ?? null) ? $lrg_resources : [];
}

function lrg_resource_default_name(): string {
  $r = lrg_resources();
  return $r ? (string)array_key_first($r) : '_legacy';
}

/** null/empty name = the default resource; null when the name is unknown */
function lrg_resource(?string $name = null): ?array {
  $r = lrg_resources();
  if ($name === null || $name === '') {
    $name = lrg_resource_default_name();
  }
  return $r[$name] ?? null;
}

/** "<prefix>_<tag>", or the fixed `database` when the resource has no prefix */
function lrg_resource_db_name(array $res, string $league_tag): string {
  $prefix = (string)($res['prefix'] ?? '');
  if ($prefix === '' && !empty($res['database'])) {
    return $res['database'];
  }
  return $prefix."_".$league_tag;
}

/** mysqli from a resource's credentials; null database = none selected */
function lrg_resource_connect(array $res, ?string $database = null): mysqli {
  $port = isset($res['port']) ? (int)$res['port'] : 0;
  $db = $database ?? '';
  return new mysqli($res['host'] ?? '', $res['user'] ?? '', $res['pass'] ?? '', $db, $port);
}

/** mysqli from the currently resolved resource ($lrg_sql_* globals set by head.php) */
function lrg_mysqli_connect(?string $database = null): mysqli {
  global $lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_port;
  $port = isset($lrg_sql_port) ? (int)$lrg_sql_port : 0;
  $db = $database ?? '';
  return new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $db, $port);
}
