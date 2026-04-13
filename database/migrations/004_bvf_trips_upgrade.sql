-- bvf_trips: add missing columns/keys for older DBs.
--
-- Easiest: php bin/seed.php (checks each column before ALTER; safe to re-run)
--
-- Manual phpMyAdmin / mysql client:
--   Run ONE statement below. If MySQL says:
--     #1060 Duplicate column name  -> column already exists; skip it, run the next.
--     #1061 Duplicate key name       -> index already exists; skip it, run the next.
--
-- Check what you already have:
--   SHOW COLUMNS FROM bvf_trips;

ALTER TABLE `bvf_trips` ADD COLUMN `origin_label` text DEFAULT NULL;

ALTER TABLE `bvf_trips` ADD COLUMN `destination_label` text DEFAULT NULL;

ALTER TABLE `bvf_trips` ADD COLUMN `started_at` datetime DEFAULT NULL;

ALTER TABLE `bvf_trips` ADD COLUMN `ended_at` datetime DEFAULT NULL;

ALTER TABLE `bvf_trips` ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `bvf_trips` ADD COLUMN `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `bvf_trips` ADD KEY `vessel_status` (`vessel_id`, `status`);

ALTER TABLE `bvf_trips` ADD KEY `captain_status` (`captain_user_id`, `status`);

-- Legacy only: rename user_id -> captain_user_id (only if that column still exists; run once)
-- ALTER TABLE `bvf_trips` CHANGE COLUMN `user_id` `captain_user_id` bigint(20) unsigned NOT NULL;
