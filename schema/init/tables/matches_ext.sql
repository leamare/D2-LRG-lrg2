CREATE TABLE `matches_ext` (
  `matchid` bigint(20) UNSIGNED NOT NULL,
  `nw_t` json DEFAULT NULL,
  `xp_t` json DEFAULT NULL,
  `gold_t` json DEFAULT NULL,
  `teamfights` json DEFAULT NULL,
  PRIMARY KEY (`matchid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
