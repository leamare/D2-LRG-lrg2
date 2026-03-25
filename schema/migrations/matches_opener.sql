ALTER TABLE matches ADD `radiant_opener` SMALLINT UNSIGNED DEFAULT null;
ALTER TABLE matches ADD `seriesid` bigint UNSIGNED DEFAULT null;
ALTER TABLE matches ADD `analysis_status` SMALLINT UNSIGNED DEFAULT 0 NOT NULL;
