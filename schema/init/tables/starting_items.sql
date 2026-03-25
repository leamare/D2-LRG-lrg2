CREATE TABLE `starting_items` (
  `matchid` bigint(20) UNSIGNED NOT NULL,
  `playerid` bigint(20) NOT NULL,
  `hero_id` smallint(5) UNSIGNED NOT NULL,
  `starting_items` json,
  `consumables` json,
  KEY `starting_items_matchid_player_IDX` (`matchid`,`playerid`) USING BTREE,
  KEY `starting_items_matchid_hero_IDX` (`matchid`,`hero_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
