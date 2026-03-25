CREATE TABLE `players` (
  `playerID` bigint(20) NOT NULL,
  `nickname` varchar(128),
  `name_fixed` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`playerID`),
  UNIQUE KEY `playerid` (`playerid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
