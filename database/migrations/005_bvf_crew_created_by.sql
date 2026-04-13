-- Records which app user registered each crew row (e.g. captain vs operations).
-- Safe to run once; SchemaEnsure also adds this column on web bootstrap when allowed.

ALTER TABLE `bvf_crew`
	ADD COLUMN `created_by_user_id` bigint(20) unsigned DEFAULT NULL AFTER `app_user_id`;

ALTER TABLE `bvf_crew`
	ADD KEY `created_by_user` (`created_by_user_id`);
