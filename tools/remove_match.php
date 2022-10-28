<?php
require_once("head.php");

$options = getopt("m:", [ "match" ]);

if(isset($options['m']))
  $mids = $options['m'];
else die("[F] No match ID specified\n");



$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);

if( !is_array($mids) ) $mids = [$mids];

include_once("modules/commons/schema.php");

foreach ($mids as $mid) {
  $sql = "DELETE from matchlines where matchid = $mid; DELETE from adv_matchlines where matchid = $mid; ".
      ( $schema['items'] ? "delete from items where matchid = $mid;" : "").
      "DELETE from draft where matchid = $mid; ".
      ( $schema['teams'] ? "delete from teams_matches where matchid = $mid;" : "").
      ( $schema['skill_builds'] ? "delete from skill_builds where matchid = $mid;" : "").
      ( $schema['starting_items'] ? "delete from starting_items where matchid = $mid;" : "").
      "delete from matches where matchid = $mid;";

  if ($conn->multi_query($sql) === TRUE) echo "[S] Removed match $mid.\n";
  else echo("[F] Unexpected problems when quering database.\n".$conn->error."\n");

  do {
    $conn->store_result();
  } while($conn->next_result());
}

//$query_res = $conn->store_result();
//$query_res->free_result();

echo "OK \n";

?>
