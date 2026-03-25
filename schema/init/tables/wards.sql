CREATE TABLE `wards` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
