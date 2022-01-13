<?php
require_once('head.php');
$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);

if ($conn->connect_error) die("[F] Connection to SQL server failed: ".$conn->connect_error."\n");

$wheres = [];

$_file = !empty($options['o']) ? $options['o'] : "matchlists/$lrg_league_tag.list";

if(isset($options['T'])) {
  $endt = isset($options['e']) ? $options['e'] : 0;
  $tp = strtotime($options['T'], 0);

  if (!$endt) {
    $sql = "select max(start_date) from matches;";

    if ($conn->multi_query($sql) !== TRUE) die("[F] Unexpected problems when recording to database.\n".$conn->error."\n");

    $query_res = $conn->store_result();
    $row = $query_res->fetch_row();
    if (!$row) $endt = time();
    else $endt = (int)$row[0];
    $query_res->free_result();
  }

  //$sql = "SELECT matchid FROM matches WHERE start_date >= ".($endt-$tp)." AND start_date <= $endt".";";
  $wheres[] = "start_date >= ".($endt-$tp);
  $wheres[] = "start_date <= $endt";
}
if (isset($options['P'])) {
  $ver = (int)($options['P']);

  $wheres[] = "version = $ver";
} 
if (isset($options['r'])) {
  $wheres[] = "matchid not in (select distinct matchid from adv_matchlines)";
} else if (isset($options['R'])) {
  $wheres[] = "matchid in (select distinct matchid from adv_matchlines)";
}

$sql = "SELECT matchid FROM matches".(!empty($wheres) ? " WHERE ".(isset($options['Z']) ? 'NOT ' : '').'('.implode(' AND ', $wheres).')' : "").";";

if ($conn->multi_query($sql) === TRUE) echo "[S] Requested MatchIDs.\n";
else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n".$sql."\n");

$query_res = $conn->store_result();

for ($matches = [], $row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  $matches[] = $row[0];
}

$query_res->free_result();

$matches = implode("\n", $matches);

file_put_contents($_file, $matches);

?>
