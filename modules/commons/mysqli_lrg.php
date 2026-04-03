<?php

/**
 * mysqli using rg_settings ($lrg_sql_*). $lrg_sql_port 0 = MySQL default port (3306).
 *
 * @param string|null $database null or '' = no default database selected
 */
function lrg_mysqli_connect(?string $database = null): mysqli {
  global $lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_port;
  $port = isset($lrg_sql_port) ? (int)$lrg_sql_port : 0;
  $db = $database ?? '';
  return new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $db, $port);
}
