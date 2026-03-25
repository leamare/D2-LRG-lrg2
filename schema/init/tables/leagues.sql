CREATE TABLE `leagues` (
  `ticket_id` bigint(20) NOT NULL,
  `name` varchar(512) NOT NULL,
  `url` varchar(512),
  `description` text,
  PRIMARY KEY (`ticket_id`),
  UNIQUE KEY `leagues_ticket_id_IDX` (`ticket_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
