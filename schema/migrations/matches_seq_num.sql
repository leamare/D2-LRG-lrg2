ALTER TABLE matches
  ADD `seq_num` bigint(20) UNSIGNED DEFAULT NULL,
  ADD `tower_status_radiant` int(10) UNSIGNED DEFAULT NULL,
  ADD `tower_status_dire` int(10) UNSIGNED DEFAULT NULL,
  ADD `barracks_status_radiant` int(10) UNSIGNED DEFAULT NULL,
  ADD `barracks_status_dire` int(10) UNSIGNED DEFAULT NULL,
  ADD `players_c` json DEFAULT NULL,
  ADD `heroes_c` json DEFAULT NULL,
  ADD `team_ids_c` json DEFAULT NULL;
