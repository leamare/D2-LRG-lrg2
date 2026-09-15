CREATE TABLE `chat_report` (
  `matchid` bigint(20) UNSIGNED NOT NULL,
  `playerid` bigint(20) NOT NULL,
  `chat_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `chatwheel_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `spray_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `top_messages` json DEFAULT NULL,
  `top_chatwheel` json DEFAULT NULL,
  PRIMARY KEY (`matchid`,`playerid`),
  KEY `chat_report_playerid_IDX` (`playerid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
