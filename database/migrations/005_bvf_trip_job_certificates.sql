-- Job request & certificate form (per trip). Safe to re-run if table exists.
CREATE TABLE IF NOT EXISTS `bvf_trip_job_certificates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trip_id` bigint(20) unsigned NOT NULL,
  `client_name` varchar(255) NOT NULL DEFAULT '',
  `client_address_phone` text,
  `client_vessel_name` varchar(255) NOT NULL DEFAULT '',
  `position_anchorage` varchar(255) NOT NULL DEFAULT '',
  `service_date` date DEFAULT NULL,
  `service_boat_name` varchar(255) NOT NULL DEFAULT '',
  `time_departed_port` varchar(64) NOT NULL DEFAULT '',
  `time_arrived_alongside` varchar(64) NOT NULL DEFAULT '',
  `time_departed_vessel` varchar(64) NOT NULL DEFAULT '',
  `time_arrived_port` varchar(64) NOT NULL DEFAULT '',
  `purpose_remarks` text,
  `master_signed_name` varchar(191) NOT NULL DEFAULT '',
  `coxswain_signed_name` varchar(191) NOT NULL DEFAULT '',
  `updated_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trip_id` (`trip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
