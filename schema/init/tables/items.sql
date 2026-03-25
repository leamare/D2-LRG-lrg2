CREATE TABLE `items` (
  `matchid` bigint(20) UNSIGNED NOT NULL,
  `hero_id` smallint(5) UNSIGNED NOT NULL,
  `playerid` bigint(20) NOT NULL,
  `item_id` smallint(5) UNSIGNED NOT NULL,
  `category_id` smallint(5) UNSIGNED NOT NULL,
  `time` int(11) NOT NULL,
  KEY `items_heroid_IDX` (`hero_id`) USING BTREE,
  KEY `items_matchid_IDX` (`matchid`) USING BTREE,
  KEY `items_item_IDX` (`item_id`) USING BTREE,
  KEY `items_playerid_IDX` (`playerid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
