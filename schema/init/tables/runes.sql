CREATE TABLE `runes` (
  `matchid` bigint(20) UNSIGNED NOT NULL,
  `playerid` bigint(20) NOT NULL,
  `rune_code` tinyint(3) UNSIGNED NOT NULL,
  `timing` mediumint(9) NOT NULL,
  KEY `runes_matchid_IDX` (`matchid`,`playerid`) USING BTREE,
  KEY `runes_code_match_timing_IDX` (`rune_code`,`matchid`,`timing`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
