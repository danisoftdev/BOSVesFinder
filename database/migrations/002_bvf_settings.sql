-- Run if you already have a DB from before bvf_settings existed:
--   mysql -u root vesfinder < database/migrations/002_bvf_settings.sql

CREATE TABLE IF NOT EXISTS `bvf_settings` (
  `key` varchar(64) NOT NULL,
  `value` longtext DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
