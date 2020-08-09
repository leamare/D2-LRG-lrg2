<?php
require_once("head.php");

$options = getopt("m:", [ "match" ]);

if(isset($options['m']))
  $mids = $options['m'];
else die("[F] No match ID specified\n");



$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);

if( !is_array($mids) ) $mids = [$mids];

if (!$lg_settings['main']['teams']) {
  $sql = "SELECT COUNT(*) z
  FROM information_schema.tables WHERE table_schema = '$lrg_sql_db' 
  AND table_name = 'teams_matches' HAVING z > 0;";

  $query = $conn->query($sql);
  if (isset($query->num_rows) && $query->num_rows) {
    $lg_settings['main']['teams'] = true;
  }
  echo "[N] Set &settings.teams to true.\n";
}

foreach ($mids as $mid) {
  $sql = "DELETE from matchlines where matchid = $mid; DELETE from adv_matchlines where matchid = $mid; ".
      "DELETE from draft where matchid = $mid; ".
      ( $lg_settings['main']['teams'] ? "delete from teams_matches where matchid = $mid;" : "").
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
