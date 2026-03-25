CREATE TABLE `fantasy_mvp_awards` (
  `matchid` bigint(20) UNSIGNED NOT NULL,
  `playerid` bigint(20) NOT NULL,
  `heroid` smallint(6) NOT NULL,
  `total_points` FLOAT NOT NULL,
  `mvp` tinyint(1) NOT NULL,
  `mvp_losing` tinyint(1) NOT NULL,
  `core` tinyint(1) NOT NULL,
  `support` tinyint(1) NOT NULL,
  `lvp` tinyint(1) NOT NULL,
  PRIMARY KEY (`matchid`,`playerid`),
  KEY `fantasy_awards_matchid_heroid_IDX` (`matchid`,`heroid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
