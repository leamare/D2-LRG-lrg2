<?php
$init = true;
include_once("head.php");

include_once("modules/commons/metadata.php");
require_once("modules/commons/schema_sql.php");

$meta = new lrg_metadata;

if (!file_exists("templates/default.json")) die("[F] No default league template found, exitting.");
$lg_settings = json_decode(file_get_contents("templates/default.json"), true);

if(isset($argv)) {
  $options = getopt("ST:l:N:D:I:t:dve", [
    "settings",
    "template",
    "league",
    "name",
    "desc",
    "id",
    "teams",
    "drop",
    "virtual",
    "existing",
  ]);

  $isVirtual = isset($options['virtual']) ? true : (isset($options['v']) ? true : false);
  $isForce = isset($options['drop']) ? true : (isset($options['d']) ? true : false);
  $isExisting = isset($options['existing']) ? true : (isset($options['e']) ? true : false);

  if(isset($options['T'])) {
    if (file_exists("templates/".$options['T'].".json")) {
      $tmp = json_decode(file_get_contents("templates/".$options['T'].".json"), true);
      $lg_settings = array_replace_recursive($lg_settings, $tmp);
      unset($tmp);
    }
  }

  if(isset($options['l'])) $lg_settings['league_tag'] = $options['l'];
  else $lg_settings['league_tag'] = readline_rg(" >  League tag: ");

  if ($isExisting) {
    if (file_exists("leagues/".$lg_settings['league_tag'].".json")) {
      $tmp = json_decode(file_get_contents("leagues/".$lg_settings['league_tag'].".json"), true);
      $lg_settings = array_replace_recursive($lg_settings, $tmp);
      unset($tmp);
    }
  }

  if (empty($lg_settings['league_name'])) {
    if(isset($options['N'])) $lg_settings['league_name'] = $options['N'];
    else $lg_settings['league_name'] = readline_rg(" >  League name: ");
  }

  if (empty($lg_settings['league_desc'])) {
    if(isset($options['D'])) $lg_settings['league_desc'] = $options['D'];
    else $lg_settings['league_desc'] = readline_rg(" >  League description: ");
  }

  if(isset($options['I'])) {
    $lg_settings['league_id'] = (int)$options['I'];
    if (!$lg_settings['league_id']) $lg_settings['league_id'] = null;
  }

  if(isset($options['t']) || isset($options['teams'])) {
    $teams = $options['t'] ?? $options['teams'];
    $lg_settings['teams'] = explode(",", $teams);
  }

  if(isset($options['S'])) {
    echo "[ ] Enter parameters below in format \"Parameter = value\".\n    Divide parameters subcategories by a \".\", empty line to exit.\n";
    while (!empty($st = readline_rg(" >  "))) {
      $st = explode("=", trim($st), 2);
      $st[0] = trim($st[0]);
      $st[1] = trim($st[1]);
      if ($st[1][0] === '[' && $st[1][strlen($st[1])-1] === ']') {
        $st[1] = explode(',', substr($st[1], 1, -1));
      }
      $val = &$lg_settings;
      $st[0] = explode(".", $st[0]);

      foreach ($st[0] as $level) {
        if(!isset($val[$level])) $val[$level] = array();
        $val = &$val[$level];
      }
      $val = $st[1];
    }
    unset($st);
    unset($val);
  }
}

$lg_settings['version'] = $lrg_version;

$meta['heroes'];
// if ($lg_settings['excluded_heroes'] ?? false) {
//   foreach($lg_settings['excluded_heroes'] as $hid) {
//     if (isset($meta['heroes'][$hid])) unset($meta['heroes'][$hid]);
//   }
// }
// $lg_settings['heroes_snapshot'] = array_keys($meta['heroes']);

$lg_settings['excluded_heroes'] = $lg_settings['excluded_heroes'] ?? [];
// Heroes snapshots are still supported
// but editing them is painful

$f = fopen("leagues/".$lg_settings['league_tag'].".json", "w") or die("[F] Couldn't open file to save results. Check working directory for `leagues` folder.\n");
fwrite($f, json_encode($lg_settings, JSON_PRETTY_PRINT));
fclose($f);

passthru("php tools/league_matches_steamapi.php -l".$lg_settings['league_tag']." -T".$lg_settings['league_id']);

echo "[ ] Creating database...";

$conn = lrg_mysqli_connect(null);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) die("[F] Connection to SQL server failed: ".$conn->connect_error."\n");

try {
  if ($conn->select_db($lrg_db_prefix."_".$lg_settings['league_tag'])) {
    echo "\n[E] Database already exists\n";
  
    if ($isForce) {
      echo "[ ] Dropping existing database...\n";
      $conn->query("DROP DATABASE ".$lrg_db_prefix."_".$lg_settings['league_tag'].";");
      if ($conn->connect_error || $conn->error) die("[F] Can't drop database: ".($conn->connect_error ?? $conn->error)."\n");
    } else {
      die();
    }
  }
} catch (\Throwable $e) {
  
}

$conn->query("CREATE DATABASE ".$lrg_db_prefix."_".$lg_settings['league_tag']." CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
if ($conn->connect_error) die("[F] Can't create database: ".$conn->connect_error."\n");
if ($conn->error) die("[F] Can't create database: ".$conn->error."\n");
$conn->select_db($lrg_db_prefix."_".$lg_settings['league_tag']);

echo "OK\n";

if (!$isVirtual) {
  $teams = !empty($lg_settings['main']['teams']);
  $fantasy = !empty($lg_settings['main']['fantasy']);
  echo "[ ] Applying schema/init ...\n";
  try {
    foreach (lrg_rg_init_script_list($teams, $fantasy) as $script) {
      echo "[ ] $script\n";
      lrg_run_init_sql($conn, $script);
    }
  } catch (RuntimeException $e) {
    die($e->getMessage());
  }
  echo "[ ] Schema init OK\n";
} else {
  // check lg_settings for a virtual data source
  if (!isset($lg_settings['virtual']) || !isset($lg_settings['virtual']['source']))
    die("[F] Need to specify information on virtual source first.\n");
  
  $self = $lrg_db_prefix."_".$lg_settings['league_tag'];
  $src = $lrg_db_prefix."_".$lg_settings['virtual']['source'];

  echo "[ ] Updating schema...\n";

  $res = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$src'");
  if ($conn->connect_error || $conn->error) die("[F] Can't drop database: ".($conn->connect_error ?? $conn->error)."\n");
  if (!$res->num_rows) die("[F] No such source.\n");

  // create matches and set filters

  $wheres = [];
  foreach (($lg_settings['virtual']['filters'] ?? []) as $type => $filter) {
    switch ($type) {
      case "players":
        $wheres[] = "`$src`.`matches`.`matchid` in (
          select matchid from `$src`.`matchlines` where playerid in (".implode(',', $filter).")
        )";
        break;
      case "time_after":
        $wheres[] = "`$src`.`matches`.`start_date` >= $filter";
        break;
      case "time_before":
        $wheres[] = "`$src`.`matches`.`start_date` <= $filter";
        break;
      case "teams":
        $wheres[] = "`$src`.`matches`.`matchid` in (
          select matchid from `$src`.`teams_matches` where teamid in (".implode(',', $filter).")
        )";
        break;
      case "league":
        $wheres[] = "`$src`.`matches`.`leagueID` in (".implode(',', $filter)."";
        break;
      case "version":
        $wheres[] = "`$src`.`matches`.`version` in (".implode(',', $filter)."";
        break;
      case "version_start":
        $wheres[] = "`$src`.`matches`.`version` >= $filter";
        break;
      case "version_end":
        $wheres[] = "`$src`.`matches`.`version` <= $filter";
        break;
      case "cluster":
        $wheres[] = "`$src`.`matches`.`cluster` in (".implode(',', $filter)."";
        break;
      case "modes":
        $wheres[] = "`$src`.`matches`.`modeID` in (".implode(',', $filter)."";
        break;
    }
  }

  echo "[ ] Creating `matches`...\n";

  $conn->query("CREATE OR REPLACE VIEW `$self`.`matches` AS
    select * from `$src`.`matches`".
    (empty($wheres) ? "" : " where ".implode(" AND ", $wheres))
  );
  if ($conn->connect_error) die("[F] Error creating view `$self`.`matches`: ".$conn->connect_error."\n");

  // specify what tables to clone
  $existing = [];
  $res = $conn->query("SHOW TABLES FROM $src");
  if ($conn->connect_error) die("[F] Error fetching tables: ".$conn->connect_error."\n");

  for ($row = $res->fetch_row(); $row != null; $row = $res->fetch_row()) {
    $existing[] = $row[0];
  }

  $tables = [
    'matchlines',
    'adv_matchlines',
    'draft',
    'items',
    'teams_matches',
    'skill_builds',
    'starting_items',
    'wards',
  ];

  $tables_clone = [
    'teams',
    'players',
    'teams_rosters',
  ];

  // create all other tables

  foreach ($tables as $t) {
    if (!in_array($t, $existing)) continue;

    echo "[ ] Creating `$t`...\n";

    $conn->query("CREATE OR REPLACE VIEW `$self`.`$t`
    AS SELECT * FROM `$src`.`$t` WHERE matchid in (
      select matchid from `$self`.`matches`
    );");

    if ($conn->connect_error) die("[F] Error creating view `$self`.`$t`: ".$conn->connect_error."\n");
  }

  foreach ($tables_clone as $t) {
    if (!in_array($t, $existing)) continue;

    echo "[ ] Creating `$t`...\n";

    $conn->query("CREATE OR REPLACE VIEW `$self`.`$t` AS SELECT * FROM `$src`.`$t`;");

    if ($conn->connect_error) die("[F] Error creating view `$self`.`$t`: ".$conn->connect_error."\n");
  }
}