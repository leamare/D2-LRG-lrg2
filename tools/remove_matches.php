<?php
require_once("head.php");
$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);


$options = getopt("l:f:T:e:");

if(isset($options['f']))
  $file = $options['f'];
else
  $file = "matchlists/$lrg_league_tag.list";

$mids = [];

include_once("modules/commons/schema.php");

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

  $sql = "SELECT matchid FROM matches WHERE start_date < ".($endt-$tp)." OR start_date > $endt".";";
  //die($sql);

  if ($conn->multi_query($sql) === TRUE) echo "# Requested MatchIDs.\n";
  else die("[F] Unexpected problems when recording to database.\n".$conn->error."\n");

  $query_res = $conn->store_result();

  for ($row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
    $mids[] = $row[0];
  }

  $query_res->free_result();
} else {
  $mids = explode("\n", file_get_contents($file));
}

if( !is_array($mids) ) $mids = [$mids];

foreach ($mids as $mid) {
  if (empty($mid) || $mid[0] == '#') continue;

  $sql = "DELETE from matchlines where matchid = $mid; DELETE from adv_matchlines where matchid = $mid; ".
      ( $schema['items'] ? "delete from items where matchid = $mid;" : "").
      "DELETE from draft where matchid = $mid; ".
      ( $schema['teams'] ? "delete from teams_matches where matchid = $mid;" : "").
      ( $schema['skill_builds'] ? "delete from skill_builds where matchid = $mid;" : "").
      ( $schema['starting_items'] ? "delete from starting_items where matchid = $mid;" : "").
      ( $schema['wards'] ? "delete from wards where matchid = $mid;" : "").
      "delete from matches where matchid = $mid;";

  if ($conn->multi_query($sql) === TRUE) echo "$mid\n";
  else echo("# [F] Unexpected problems when quering database.\n".$conn->error."\n");
  
  do {
      $conn->store_result();
  } while($conn->next_result());
}

echo "# OK \n";
