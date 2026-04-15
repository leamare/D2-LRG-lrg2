<?php
require_once("head.php");
$conn = lrg_mysqli_connect($lrg_sql_db);


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
  $raw = str_replace("\r\n", "\n", (string)file_get_contents($file));
  $mids = explode("\n", $raw);
}

if( !is_array($mids) ) $mids = [$mids];

$mids = array_values(array_filter(array_map('trim', $mids), 'strlen'));
$mids = array_values(array_filter($mids, function ($mid) {
  $s = (string)$mid;
  return $s !== '' && $s[0] !== '#' && ctype_digit($s);
}));
$mids = array_map('intval', $mids);
$mids = array_values(array_filter($mids, function ($id) {
  return $id > 0;
}));

$chunkSize = 200;

foreach (array_chunk($mids, $chunkSize) as $chunk) {
  if (empty($chunk)) {
    continue;
  }
  $in = implode(',', $chunk);

  $sql = "DELETE FROM matchlines WHERE matchid IN ($in); DELETE FROM adv_matchlines WHERE matchid IN ($in); ";
  if ($schema['itemslines']) {
    $sql .= "DELETE FROM itemslines WHERE matchid IN ($in); ";
  } elseif ($schema['items']) {
    $sql .= "DELETE FROM items WHERE matchid IN ($in); ";
  }
  $sql .= "DELETE FROM draft WHERE matchid IN ($in); ";
  if ($schema['teams']) {
    $sql .= "DELETE FROM teams_matches WHERE matchid IN ($in); ";
  }
  if ($schema['skill_builds']) {
    $sql .= "DELETE FROM skill_builds WHERE matchid IN ($in); ";
  }
  if ($schema['starting_items']) {
    $sql .= "DELETE FROM starting_items WHERE matchid IN ($in); ";
  }
  if ($schema['wards']) {
    $sql .= "DELETE FROM wards WHERE matchid IN ($in); ";
  }
  if ($schema['fantasy_mvp']) {
    $sql .= "DELETE FROM fantasy_mvp_points WHERE matchid IN ($in); DELETE FROM fantasy_mvp_awards WHERE matchid IN ($in); ";
  }
  $sql .= "DELETE FROM matches WHERE matchid IN ($in);";

  if ($conn->multi_query($sql) === TRUE) {
    foreach ($chunk as $mid) {
      echo "$mid\n";
    }
  } else {
    echo("# [F] Unexpected problems when quering database.\n".$conn->error."\n");
  }

  do {
    $conn->store_result();
  } while($conn->next_result());
}

echo "# OK \n";
