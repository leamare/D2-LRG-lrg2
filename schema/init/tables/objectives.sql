CREATE TABLE `objectives` (
  `matchid` bigint(20) UNSIGNED NOT NULL,
  `objective_id` int(10) UNSIGNED NOT NULL,
  `objective_key` varchar(64) NOT NULL,
  `timing` mediumint(9) NOT NULL,
  `killer_playerid` bigint(20) DEFAULT NULL,
  `target_is_radiant` tinyint(1) NOT NULL,
  KEY `objectives_matchid_IDX` (`matchid`,`objective_id`) USING BTREE,
  KEY `objectives_matchid_side_IDX` (`matchid`,`target_is_radiant`) USING BTREE,
  KEY `objectives_id_timing_IDX` (`objective_id`,`timing`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
