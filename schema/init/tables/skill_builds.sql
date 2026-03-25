CREATE TABLE `skill_builds` (
  `matchid` bigint(20) UNSIGNED NOT NULL,
  `playerid` bigint(20) NOT NULL,
  `hero_id` smallint(5) UNSIGNED NOT NULL,
  `skill_build` json,
  `first_point_at` json,
  `maxed_at` json,
  `priority` json,
  `talents` json,
  `attributes` json,
  `ultimate` bigint(10) UNSIGNED,
  KEY `skill_builds_matchid_player_IDX` (`matchid`,`playerid`) USING BTREE,
  KEY `skill_builds_matchid_hero_IDX` (`matchid`,`hero_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
