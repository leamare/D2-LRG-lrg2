ALTER TABLE adv_matchlines
  ADD `nw_t` json DEFAULT NULL,
  ADD `gold_t` json DEFAULT NULL,
  ADD `xp_t` json DEFAULT NULL,
  ADD `lh_t` json DEFAULT NULL,
  ADD `damage_breakdown` json DEFAULT NULL;
