<?php

require_once('head.php');

$conn = new mysqli($lrg_sql_host, $lrg_sql_user, $lrg_sql_pass, $lrg_sql_db);

if ($conn->connect_error) die("[F] Connection to SQL server failed: ".$conn->connect_error."\n");

include_once("modules/commons/schema.php");

function runquery($conn, $q) {
  $conn->query($q);
  if ($conn->connect_error) die("[F] Can't create table `matchlines`: ".$conn->connect_error."\n");
}

if (!$schema['matches_opener']) {
  $sql = "ALTER TABLE matches ADD `radiant_opener` SMALLINT UNSIGNED DEFAULT null;";
  runquery($conn, $sql);
  $sql = "ALTER TABLE matches ADD `seriesid` bigint UNSIGNED DEFAULT null;";
  runquery($conn, $sql);
  $sql = "ALTER TABLE matches ADD `analysis_status` SMALLINT UNSIGNED DEFAULT 0 NOT NULL;";
  runquery($conn, $sql);
}

if (!$schema['adv_matchlines_roles']) {
  $sql = "ALTER TABLE adv_matchlines ADD `role` SMALLINT UNSIGNED DEFAULT 0 NOT NULL;";
  runquery($conn, $sql);
  $sql = "ALTER TABLE adv_matchlines ADD `lane_won` SMALLINT UNSIGNED DEFAULT 0 NOT NULL;";
  runquery($conn, $sql);
}

if (!$schema['players_fixname']) {
  $sql = "ALTER TABLE players ADD `name_fixed` tinyint(1) DEFAULT 0 NOT NULL;";

  runquery($conn, $sql);
}

if (!$schema['draft_order']) {
  $sql = "ALTER TABLE draft ADD `order` SMALLINT UNSIGNED DEFAULT 0 NOT NULL;";

  runquery($conn, $sql);
}


if (!$schema['starting_items']) {
  $sql = "CREATE TABLE `starting_items` (
    `matchid` bigint(20) UNSIGNED NOT NULL,
    `playerid` bigint(20) NOT NULL,
    `hero_id` smallint(5) UNSIGNED NOT NULL,
    `starting_items` json,
    `consumables` json,
    KEY `starting_items_matchid_player_IDX` (`matchid`,`playerid`) USING BTREE,
    KEY `starting_items_matchid_hero_IDX` (`matchid`,`hero_id`) USING BTREE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

  $schema['starting_consumables'] = true;

  runquery($conn, $sql);
}

if (!$schema['skill_builds']) {
  $sql = "CREATE TABLE `skill_builds` (
    `matchid` bigint(20) UNSIGNED NOT NULL,
    `playerid` bigint(20) NOT NULL,
    `hero_id` smallint(5) UNSIGNED NOT NULL,
    `skill_build` json,
    `first_point_at` json,
    `maxed_at` json,
    `priority` json,
    `talents` json,
    `attributes` json,
    `ultimate` bigint UNSIGNED,
    KEY `skill_builds_matchid_player_IDX` (`matchid`,`playerid`) USING BTREE,
    KEY `skill_builds_matchid_hero_IDX` (`matchid`,`hero_id`) USING BTREE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

  $schema['skill_build_attr'] = true;

  runquery($conn, $sql);
}

if (!$schema['skill_build_attr']) {
  $sql = "ALTER TABLE skill_builds ADD `attributes` json;";
  runquery($conn, $sql);
  $sql = "ALTER TABLE skill_builds ADD `ultimate` BIGINT UNSIGNED;";
  runquery($conn, $sql);
}

if (!$schema['starting_consumables']) {
  $sql = "ALTER TABLE starting_items ADD `consumables` json;";

  runquery($conn, $sql);
}

if (!$schema['wards']) {
  $sql = "CREATE TABLE `wards` (
    `matchid` bigint(20) UNSIGNED NOT NULL,
    `playerid` bigint(20) NOT NULL,
    `hero_id` smallint(5) UNSIGNED NOT NULL,
    `wards_log` json,
    `sentries_log` json,
    `destroyed_log` json,
    UNIQUE KEY `wards_matchid_player_IDX` (`matchid`,`playerid`) USING BTREE,
    KEY `wards_matchid_IDX` (`matchid`) USING BTREE,
    KEY `wards_playerid_IDX` (`playerid`) USING BTREE,
    KEY `wards_heroid_IDX` (`hero_id`) USING BTREE,
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

  $schema['skill_build_attr'] = true;

  runquery($conn, $sql);
}