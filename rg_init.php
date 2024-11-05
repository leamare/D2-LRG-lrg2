<?php
$init = true;
include_once("head.php");

include_once("modules/commons/metadata.php");
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

echo "[ ] Opening matchlist file\n";

$f = fopen("matchlists/".$lg_settings['league_tag'].".list", "w") or die("[F] Couldn't open file to save results. Check working directory for `matchlists` folder.\n");
if($lg_settings['league_id'] == null) $out = "";
else {
    $request = "https://api.steampowered.com/IDOTA2Match_570/GetMatchHistory/v0001/?key=".$steamapikey."&league_id=".$lg_settings['league_id'];
    echo "[ ] Requested...";

    $matches = array();
    $response = json_decode(file_get_contents($request), true);

    do {
      echo "OK [".sizeof($response['result']['matches'])."] ";
      if(isset($last_matchid)) $last_tail = $last_matchid;
      else $last_tail = 0;

      foreach($response['result']['matches'] as $r_match) {
        $last_matchid = $r_match['match_id'];

        if($lg_settings['time_limit_after'] != null && $lg_settings['time_limit_after'] > $r_match['start_time'])
          continue;
        if($lg_settings['time_limit_after'] != null && $lg_settings['time_limit_before'] < $r_match['start_time'])
          continue;
        if($lg_settings['match_limit_after'] != null && $lg_settings['match_limit_after'] > $r_match['match_id'])
          continue;
        if($lg_settings['match_limit_before'] != null && $lg_settings['match_limit_before'] < $r_match['match_id'])
          continue;


        if(!in_array($r_match['match_id'], $matches)) {
          $matches[] = $r_match['match_id'];
        }
      }
      if (isset($last_matchid))
        $response = json_decode(file_get_contents($request."&start_at_match_id=".$last_matchid), true);
    } while (isset($last_matchid) && sizeof($response['result']['matches']) > 2 && $last_matchid != $last_tail);

    $out = implode("\n", $matches);
}
echo "\n";

fwrite($f, $out);
fclose($f);

echo "[ ] Creating database...";

$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass);
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
  echo "[ ] Creating table `matches`...";

  $conn->query("CREATE TABLE `matches` (
    `matchid` bigint(20) UNSIGNED NOT NULL,
    `radiantWin` tinyint(1) NOT NULL,
    `duration` int(11) NOT NULL,
    `modeID` tinyint(11) UNSIGNED NOT NULL,
    `leagueID` int(11),
    `start_date` int(11) NOT NULL,
    `analysis_status` tinyint(1) NOT NULL,
    `radiant_opener` tinyint(1),
    `seriesid` bigint(20) UNSIGNED,
    `stomp` int(11) NOT NULL,
    `comeback` int(11) NOT NULL,
    `cluster` int(10) UNSIGNED NOT NULL,
    `version` int(10) UNSIGNED NOT NULL,
    UNIQUE KEY `matches_matchid_radiantwin_IDX` (`matchid`,`radiantWin`) USING BTREE,
    UNIQUE KEY `matches_matchid_modeid_IDX` (`matchid`,`modeID`) USING BTREE,
    UNIQUE KEY `matches_matchid_cluster_IDX` (`matchid`,`cluster`) USING BTREE,
    UNIQUE KEY `matches_matchid_version_IDX` (`matchid`,`version`) USING BTREE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    if ($conn->connect_error) die("[F] Can't create table `matches`: ".$conn->connect_error."\n");
  echo "OK\n[ ] Creating table `matchlines`...";

  $conn->query("CREATE TABLE `matchlines` (
    `matchid` bigint(20) UNSIGNED NOT NULL,
    `playerid` bigint(20) NOT NULL,
    `heroid` smallint(6) NOT NULL,
    `variant` SMALLINT(6) UNSIGNED DEFAULT null NULL,
    `level` tinyint(3) UNSIGNED NOT NULL,
    `isRadiant` tinyint(1) NOT NULL,
    `kills` smallint(6) NOT NULL,
    `deaths` smallint(6) NOT NULL,
    `assists` smallint(6) NOT NULL,
    `networth` mediumint(9) NOT NULL,
    `gpm` mediumint(9) NOT NULL,
    `xpm` mediumint(9) NOT NULL,
    `heal` mediumint(9) NOT NULL,
    `heroDamage` mediumint(9) NOT NULL,
    `towerDamage` mediumint(9) NOT NULL,
    `lastHits` smallint(6) NOT NULL,
    `denies` smallint(6) NOT NULL,
    KEY `matchlines_matchid_heroid_IDX` (`matchid`,`heroid`) USING BTREE,
    UNIQUE KEY `matchlines_matchid_playerid_IDX` (`matchid`,`playerid`) USING BTREE,
    KEY `matchlines_heroid_isradiant_IDX` (`heroid`,`isRadiant`) USING BTREE,
    KEY `matchlines_playerid_isradiant_IDX` (`playerid`,`isRadiant`) USING BTREE,
    KEY `matchlines_playerid_heroid_IDX` (`playerid`,`heroid`) USING BTREE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    if ($conn->connect_error) die("[F] Can't create table `matchlines`: ".$conn->connect_error."\n");
  echo "OK\n[ ] Creating table `adv_matchlines`...";

  $conn->query("CREATE TABLE `adv_matchlines` (
    `matchid` bigint(20) UNSIGNED NOT NULL,
    `playerid` bigint(20) NOT NULL,
    `heroid` smallint(5) UNSIGNED NOT NULL,
    `lh_at10` mediumint(9) UNSIGNED NOT NULL,
    `isCore` tinyint(1) NOT NULL,
    `lane` tinyint(3) UNSIGNED NOT NULL,
    `role` tinyint(3) UNSIGNED NOT NULL,
    `lane_won` tinyint(3) UNSIGNED NOT NULL,
    `efficiency_at10` float NOT NULL,
    `wards` smallint(5) UNSIGNED NOT NULL,
    `sentries` smallint(5) UNSIGNED NOT NULL,
    `couriers_killed` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
    `roshans_killed` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
    `multi_kill` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
    `streak` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
    `stacks` tinyint(4) NOT NULL DEFAULT '0',
    `time_dead` int(10) UNSIGNED NOT NULL,
    `buybacks` smallint(5) NOT NULL DEFAULT '0',
    `wards_destroyed` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
    `pings` int(10) NOT NULL DEFAULT '0',
    `stuns` float NOT NULL,
    `teamfight_part` float NOT NULL,
    `damage_taken` int(10) UNSIGNED NOT NULL,
    KEY `advmatchlines_matchid_heroid_IDX` (`matchid`,`heroid`) USING BTREE,
    UNIQUE KEY `advmatchlines_matchid_playerid_IDX` (`matchid`,`playerid`) USING BTREE,
    KEY `advmatchlines_heroid_iscore_IDX` (`heroid`,`isCore`) USING BTREE,
    KEY `advmatchlines_heroid_lane_IDX` (`heroid`,`lane`) USING BTREE,
    KEY `advmatchlines_playerid_iscore_IDX` (`playerid`,`isCore`) USING BTREE,
    KEY `advmatchlines_playerid_heroid_IDX` (`playerid`,`heroid`) USING BTREE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    if ($conn->connect_error) die("[F] Can't create table `adv_matchlines`: ".$conn->connect_error."\n");

  echo "OK\n[ ] Creating table `draft`...";

  // - draft: matchid, heroid; matchid, playerid U, matchid, stage, matchid, is_pick, matchid, is_radiant
  $conn->query("CREATE TABLE `draft` (
    `matchid` bigint(20) UNSIGNED NOT NULL,
    `is_radiant` tinyint(1) NOT NULL,
    `is_pick` tinyint(1) NOT NULL,
    `hero_id` smallint(5) UNSIGNED NOT NULL,
    `stage` tinyint(3) UNSIGNED NOT NULL,
    `order` smallint(3) UNSIGNED NOT NULL,
    KEY `draft_matchid_heroid_IDX` (`matchid`,`hero_id`) USING BTREE,
    KEY `draft_matchid_stage_IDX` (`matchid`,`stage`) USING BTREE,
    KEY `draft_matchid_pick_IDX` (`matchid`,`is_pick`) USING BTREE,
    KEY `draft_matchid_side_IDX` (`matchid`,`is_radiant`) USING BTREE,
    KEY `draft_heroid_pick_IDX` (`hero_id`,`is_pick`) USING BTREE,
    KEY `draft_heroid_side_IDX` (`hero_id`,`is_radiant`) USING BTREE,
    KEY `draft_heroid_stage_IDX` (`hero_id`,`stage`) USING BTREE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    if ($conn->connect_error) die("[F] Can't create table `draft`: ".$conn->connect_error."\n");


    echo "OK\n[ ] Creating table `items`...";
    // `items` json NOT NULL,

  // KEY `items_matchid_heroid_IDX` (`matchid`,`hero_id`) USING BTREE,
  // KEY `items_matchid_player_IDX` (`matchid`,`playerid`) USING BTREE,
  // KEY `items_matchid_item_IDX` (`matchid`,`item_id`) USING BTREE,
  // KEY `items_matchid_category_IDX` (`matchid`,`category_id`) USING BTREE,
  // KEY `items_matchid_time_IDX` (`matchid`,`item_id`,`time`) USING BTREE
  $conn->query("CREATE TABLE `items` (
    `matchid` bigint(20) UNSIGNED NOT NULL,
    `hero_id` smallint(5) UNSIGNED NOT NULL,
    `playerid` bigint(20) NOT NULL,
    `item_id` smallint(5) UNSIGNED NOT NULL,
    `category_id` smallint(5) UNSIGNED NOT NULL,
    `time` int(11) NOT NULL,
    KEY `items_heroid_IDX` (`hero_id`) USING BTREE,
    KEY `items_matchid_IDX` (`matchid`) USING BTREE,
    KEY `items_item_IDX` (`item_id`) USING BTREE,
    KEY `items_playerid_IDX` (`playerid`) USING BTREE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    if ($conn->connect_error) die("[F] Can't create table `items`: ".$conn->connect_error."\n");

  echo "OK\n[ ] Creating table `starting_items`...";
  $conn->query("CREATE TABLE `starting_items` (
    `matchid` bigint(20) UNSIGNED NOT NULL,
    `playerid` bigint(20) NOT NULL,
    `hero_id` smallint(5) UNSIGNED NOT NULL,
    `starting_items` json,
    `consumables` json,
    KEY `starting_items_matchid_player_IDX` (`matchid`,`playerid`) USING BTREE,
    KEY `starting_items_matchid_hero_IDX` (`matchid`,`hero_id`) USING BTREE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    if ($conn->connect_error) die("[F] Can't create table `items`: ".$conn->connect_error."\n");

  echo "OK\n[ ] Creating table `skill_builds`...";
  $conn->query("CREATE TABLE `skill_builds` (
    `matchid` bigint(20) UNSIGNED NOT NULL,
    `playerid` bigint(20) NOT NULL,
    `hero_id` smallint(5) UNSIGNED NOT NULL,
    `skill_build` json,
    `first_point_at` json,
    `maxed_at` json,
    `priority` json,
    `talents` json,
    `attributes` json,
    `ultimate` bigint(10) UNSIGNED,
    KEY `skill_builds_matchid_player_IDX` (`matchid`,`playerid`) USING BTREE,
    KEY `skill_builds_matchid_hero_IDX` (`matchid`,`hero_id`) USING BTREE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  if ($conn->connect_error) die("[F] Can't create table `items`: ".$conn->connect_error."\n");

  echo "OK\n[ ] Creating table `wards`...";
  $conn->query("CREATE TABLE `wards` (
    `matchid` bigint(20) UNSIGNED NOT NULL,
    `playerid` bigint(20) NOT NULL,
    `hero_id` smallint(5) UNSIGNED NOT NULL,
    `wards_log` json,
    `sentries_log` json,
    `destroyed_log` json,
    UNIQUE KEY `wards_matchid_player_IDX` (`matchid`,`playerid`) USING BTREE,
    KEY `wards_matchid_IDX` (`matchid`) USING BTREE,
    KEY `wards_playerid_IDX` (`playerid`) USING BTREE,
    KEY `wards_heroid_IDX` (`hero_id`) USING BTREE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  if ($conn->connect_error) die("[F] Can't create table `wards`: ".$conn->connect_error."\n");


  echo "OK\n[ ] Creating table `players`...";

  $conn->query("CREATE TABLE `players` (
    `playerID` bigint(20) NOT NULL,
    `nickname` varchar(128),
    `name_fixed` tinyint(1) NOT NULL DEFAULT 0
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  if ($conn->connect_error) die("[F] Can't create table `players`: ".$conn->connect_error."\n");
  echo "OK\n";

  echo "[ ] Adding keys to main tables...";
  $conn->query("ALTER TABLE `matches` ADD PRIMARY KEY (`matchid`), ADD UNIQUE KEY `matchid` (`matchid`);");
    if ($conn->connect_error) die("[F] Can't add key to `matches`: ".$conn->connect_error."\n");
  $conn->query("ALTER TABLE `adv_matchlines` ADD PRIMARY KEY (`matchid`,`playerid`);");
    if ($conn->connect_error) die("[F] Can't add key to `adv_matchlines`: ".$conn->connect_error."\n");
  $conn->query("ALTER TABLE `matchlines` ADD PRIMARY KEY (`matchid`,`playerid`);");
    if ($conn->connect_error) die("[F] Can't add key to `matchlines`: ".$conn->connect_error."\n");
  $conn->query("ALTER TABLE `players` ADD PRIMARY KEY (`playerID`), ADD UNIQUE KEY `playerid` (`playerid`);");
    if ($conn->connect_error) die("[F] Can't add key to `players`: ".$conn->connect_error."\n");

  echo "OK\n[ ] Linking main tables...";
  $conn->query("ALTER TABLE `adv_matchlines` ADD CONSTRAINT `adv_matchlines` FOREIGN KEY (`matchid`) REFERENCES `matches` (`matchid`);");
    if ($conn->connect_error) die("[F] Can't link `adv_matchlines` to `matches`: ".$conn->connect_error."\n");
  $conn->query("ALTER TABLE `matchlines` ADD CONSTRAINT `matchlines_ibfk_1` FOREIGN KEY (`matchID`) REFERENCES `matches` (`matchid`);");
    if ($conn->connect_error) die("[F] Can't link `matchlines` to `matches`: ".$conn->connect_error."\n");
  $conn->query("ALTER TABLE `draft` ADD CONSTRAINT `draft` FOREIGN KEY (`matchid`) REFERENCES `matches` (`matchid`);");
    if ($conn->connect_error) die("[F] Can't link `draft` to `matches`: ".$conn->connect_error."\n");
  $conn->query("ALTER TABLE `items` ADD CONSTRAINT `items` FOREIGN KEY (`matchid`) REFERENCES `matches` (`matchid`);");
    if ($conn->connect_error) die("[F] Can't link `items` to `matches`: ".$conn->connect_error."\n");
  $conn->query("ALTER TABLE `adv_matchlines` ADD CONSTRAINT `adv_matchlines_pl` FOREIGN KEY (`playerID`) REFERENCES `players` (`playerID`);");
    if ($conn->connect_error) die("[F] Can't link `adv_matchlines` to `players`: ".$conn->connect_error."\n");
  $conn->query("ALTER TABLE `matchlines` ADD CONSTRAINT `matchlines_pl` FOREIGN KEY (`playerID`) REFERENCES `players` (`playerID`);");
    if ($conn->connect_error) die("[F] Can't link `matchlines` to `players`: ".$conn->connect_error."\n");
  echo "OK\n";

  if($lg_settings['main']['teams']) {
    echo "[ ] Creating table `teams`...";
    $conn->query("CREATE TABLE `teams` (
      `teamid` bigint(20) UNSIGNED NOT NULL,
      `name` varchar(50) NOT NULL,
      `tag` varchar(25) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
      if ($conn->connect_error) die("[F] Can't create table `teams`: ".$conn->connect_error."\n");
    echo "OK\n";

    echo "[ ] Creating table `teams_matches`...";
    $conn->query("CREATE TABLE `teams_matches` (
      `matchid` bigint(20) UNSIGNED NOT NULL,
      `teamid` bigint(20) UNSIGNED NOT NULL,
      `is_radiant` tinyint(1) NOT NULL,
      UNIQUE KEY `teams_matches_matchid_teamid_IDX` (`matchid`,`teamid`) USING BTREE,
      UNIQUE KEY `teams_matches_matchid_side_IDX` (`matchid`,`is_radiant`) USING BTREE,
      KEY `teams_matches_teamis_side_IDX` (`teamid`,`is_radiant`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
      if ($conn->connect_error) die("[F] Can't create table `teams_matches`: ".$conn->connect_error."\n");
    echo "OK\n";

    //- teams_rosters: teamid, playerid U, teamid, playerid
    echo "[ ] Creating table `teams_rosters`...";
    $conn->query("CREATE TABLE `teams_rosters` (
      `teamid` bigint(20) UNSIGNED NOT NULL,
      `playerid` bigint(20) NOT NULL,
      `position` tinyint(3) UNSIGNED NOT NULL,
      KEY `rosters_teamid_playerid_IDX` (`teamid`,`playerid`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
      if ($conn->connect_error) die("[F] Can't create table `teams_rosters`: ".$conn->connect_error."\n");
    echo "OK\n";

    echo "[ ] Adding keys to team tables...";
    $conn->query("ALTER TABLE `teams` ADD PRIMARY KEY (`teamid`), ADD UNIQUE KEY `teamid` (`teamid`);");
      if ($conn->connect_error) die("[F] Can't add key to `teams`: ".$conn->connect_error."\n");
    $conn->query("ALTER TABLE `teams_matches` ADD PRIMARY KEY (`matchid`,`is_radiant`);");
      if ($conn->connect_error) die("[F] Can't add key to `teams_matches`: ".$conn->connect_error."\n");
    $conn->query("ALTER TABLE `teams_rosters` ADD PRIMARY KEY (`teamid`,`playerid`);");
      if ($conn->connect_error) die("[F] Can't add key to `teams_rosters`: ".$conn->connect_error."\n");

    echo "OK\n[ ] Linking team tables...";
    $conn->query("ALTER TABLE `teams_matches` ADD CONSTRAINT `teams_matches` FOREIGN KEY (`matchid`) REFERENCES `matches` (`matchid`);");
      if ($conn->connect_error) die("[F] Can't link `teams_matches` to `matches`: ".$conn->connect_error."\n");
    $conn->query("ALTER TABLE `teams_matches` ADD CONSTRAINT `teams_matches_teams` FOREIGN KEY (`teamid`) REFERENCES `teams` (`teamid`);");
      if ($conn->connect_error) die("[F] Can't link `teams_matches` to `teams`: ".$conn->connect_error."\n");
    $conn->query("ALTER TABLE `teams_rosters` ADD CONSTRAINT `teams_rosters_teams` FOREIGN KEY (`teamid`) REFERENCES `teams` (`teamid`);");
      if ($conn->connect_error) die("[F] Can't link `teams_rosters` to `teams`: ".$conn->connect_error."\n");
    // $conn->query("ALTER TABLE `teams_rosters` ADD CONSTRAINT `teams_rosters_players` FOREIGN KEY (`playerid`) REFERENCES `players` (`playerID`);");
    //   if ($conn->connect_error) die("[F] Can't link `teams_rosters` to `players`: ".$conn->connect_error."\n");

    echo "OK\n";
  }
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