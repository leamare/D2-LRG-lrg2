<?php

set_error_handler(
  function ($severity, $message, $file, $line) {
    if (strpos($message, 'file_get_contents') !== false) {
      $dt = strrpos($message, '):');
      $message = substr($message, $dt+3);
    }
    throw new ErrorException($message, $severity, $severity, $file, $line);
  }
);

require_once("head.php");
$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);

require_once("modules/commons/schema.php");

$options = getopt("l:f:d:");

$cooldown = isset($options['d']) ? (int)$options['d'] : 0;

// $lrg_league_tag;
// update:
//   items
//   matchlines
//   adv_matchlines

// items support detection
$sql = "SELECT COUNT(*) z
FROM information_schema.tables WHERE table_schema = '$lrg_sql_db' 
AND table_name = 'items' HAVING z > 0;";

$query = $conn->query($sql);
if (!isset($query->num_rows) || !$query->num_rows) {
  $lg_settings['main']['items'] = false;
  echo "# Set &settings.items to false.\n";
}
//
//

// players
$t_players = [];
$sql = "SELECT playerid, nickname FROM players;";
if ($conn->multi_query($sql) === TRUE) {
  $res = $conn->store_result();

  for ($row = $res->fetch_row(); $row != null; $row = $res->fetch_row()) {
    $t_players[(int)$row[0]] = $row[1];
  }
  $res->free_result();
} else die("Something went wrong: ".$conn->error."\n");

// 

$sql = "SELECT matchid FROM matchlines WHERE playerid < 0;";

$query_res = $conn->query($sql);

if ($query_res !== FALSE);
else die("[F] Unexpected problems when requesting database.\n".$conn->error."\n".$q);

for ($matches = [], $row = $query_res->fetch_row(); $row != null; $row = $query_res->fetch_row()) {
  $matches[] = $row[0];
}

$query_res->close();

$matches = array_unique($matches);

echo "# Total: ".sizeof($matches)."\n";

foreach ($matches as $match) {
  $data = [
    'query' => "query MatchPlayerIDs {
      match(id: $match) { 
        parsedDateTime, players { steamAccountId, heroId, steamAccount { name, proSteamAccount { name } } }
      } 
    }",
  ];
  if (!empty($stratztoken)) $data['key'] = $stratztoken;
  
  $stratz_request = "https://api.stratz.com/graphql";

  $q = http_build_query($data);
  $error = null;

  try {
    $json = file_get_contents($stratz_request.'?'.$q);
  } catch (Exception $e) {
    $json = null;
    $error = $e->getMessage();
  }

  if ($cooldown) sleep($cooldown);

  if ($json) {
    $stratz = json_decode($json, true);

    if (empty($stratz['data']) || empty($stratz['data']['match'])) {
      $error = " empty ";
    }// else if (!$stratz['data']['match']['parsedDateTime']) {
    //  $error = " unparsed ";
    //}

    if (!empty($stratz['errors'])) {
      $error .= implode(',', $stratz['errors']);
    }
  }
  
  if ($error) {
    $error = str_replace("\n", " ", $error);
    echo "# $match - ERROR ".$error."\n";
    continue;
  }

  $update_events = [];

  foreach ($stratz['data']['match']['players'] as $pl) {
    if (empty($pl['steamAccountId'])) continue;
    if (!isset($t_players[$pl['steamAccountId']])) {
      $name = $pl['steamAccount']['proSteamAccount']['name'] ?? $pl['steamAccount']['name'] ?? "Player ".$pl['steamAccountId'];
      $update_events[] = "INSERT INTO players (playerID, nickname) VALUES (".$pl['steamAccountId'].",\"".addslashes($name)."\");";
      $t_players[$pl['steamAccountId']] = $name;
    }
    $update_events[] = "UPDATE matchlines SET playerid = ".$pl['steamAccountId']." WHERE heroid = ".$pl['heroId']." AND matchid = $match;";
    $update_events[] = "UPDATE adv_matchlines SET playerid = ".$pl['steamAccountId']." WHERE heroid = ".$pl['heroId']." AND matchid = $match;";
    if ($lg_settings['main']['items'] && $schema['items']) {
      $update_events[] = "UPDATE items SET playerid = ".$pl['steamAccountId']." WHERE hero_id = ".$pl['heroId']." AND matchid = $match;";
    }
    if ($schema['skill_builds']) {
      $update_events[] = "UPDATE skill_builds SET playerid = ".$pl['steamAccountId']." WHERE hero_id = ".$pl['heroId']." AND matchid = $match;";
    }
    if ($schema['starting_items']) {
      $update_events[] = "UPDATE starting_items SET playerid = ".$pl['steamAccountId']." WHERE hero_id = ".$pl['heroId']." AND matchid = $match;";
    }
    if ($schema['wards']) {
      $update_events[] = "UPDATE wards SET playerid = ".$pl['steamAccountId']." WHERE hero_id = ".$pl['heroId']." AND matchid = $match;";
    }
  }

  if ($conn->multi_query(implode("\n", $update_events)) !== TRUE) {
    echo "# $match ".$conn->error."\n";
    continue;
  }

  do {
    $query_res = $conn->store_result();
  } while($conn->more_results() && $conn->next_result());
  //echo "# $match ".$conn->error."\n";

  echo "$match\n";
}

echo "# OK \n";
