CREATE TABLE `teams_rosters` (
  `teamid` bigint(20) UNSIGNED NOT NULL,
  `playerid` bigint(20) NOT NULL,
  `position` tinyint(3) UNSIGNED NOT NULL,
  PRIMARY KEY (`teamid`,`playerid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
