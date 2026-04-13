-- Upgrade older bvf_crew tables (before app_user_id). Safe to run once.
-- If you see "Duplicate column", the column already exists — skip.

ALTER TABLE `bvf_crew`
	ADD COLUMN `app_user_id` bigint(20) unsigned DEFAULT NULL AFTER `id`;

ALTER TABLE `bvf_crew`
	ADD KEY `app_user` (`app_user_id`);
