CREATE TABLE `matches_draft_donors` (
  `matchid` bigint(20) UNSIGNED NOT NULL,
  `draft` json NOT NULL,
  `heroes` json NOT NULL,
  `players` json DEFAULT NULL,
  `modeID` tinyint(3) UNSIGNED DEFAULT NULL,
  `donated_to` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`matchid`),
  KEY `matches_draft_donors_donated_IDX` (`donated_to`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
