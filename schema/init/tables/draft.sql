CREATE TABLE `draft` (
  `matchid` bigint(20) UNSIGNED NOT NULL,
  `is_radiant` tinyint(1) NOT NULL,
  `is_pick` tinyint(1) NOT NULL,
  `hero_id` smallint(5) UNSIGNED NOT NULL,
  `stage` tinyint(3) UNSIGNED NOT NULL,
  `order` smallint(3) UNSIGNED NOT NULL,
  KEY `draft_matchid_heroid_IDX` (`matchid`,`hero_id`) USING BTREE,
  KEY `draft_matchid_stage_IDX` (`matchid`,`stage`) USING BTREE,
  KEY `draft_matchid_pick_IDX` (`matchid`,`is_pick`) USING BTREE,
  KEY `draft_matchid_side_IDX` (`matchid`,`is_radiant`) USING BTREE,
  KEY `draft_heroid_pick_IDX` (`hero_id`,`is_pick`) USING BTREE,
  KEY `draft_heroid_side_IDX` (`hero_id`,`is_radiant`) USING BTREE,
  KEY `draft_heroid_stage_IDX` (`hero_id`,`stage`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
