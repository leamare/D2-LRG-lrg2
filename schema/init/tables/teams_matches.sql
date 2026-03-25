CREATE TABLE `teams_matches` (
  `matchid` bigint(20) UNSIGNED NOT NULL,
  `teamid` bigint(20) UNSIGNED NOT NULL,
  `is_radiant` tinyint(1) NOT NULL,
  PRIMARY KEY (`matchid`,`is_radiant`),
  UNIQUE KEY `teams_matches_matchid_teamid_IDX` (`matchid`,`teamid`) USING BTREE,
  KEY `teams_matches_teamis_side_IDX` (`teamid`,`is_radiant`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
