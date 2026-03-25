CREATE TABLE `teams` (
  `teamid` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `tag` varchar(25) NOT NULL,
  PRIMARY KEY (`teamid`),
  UNIQUE KEY `teamid` (`teamid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
