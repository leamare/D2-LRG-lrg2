CREATE TABLE `matches_failed` (
  `matchid` bigint(20) UNSIGNED NOT NULL,
  `retries_missing` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `retries_unparsed` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `last_attempt` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_draft_donor` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`matchid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
