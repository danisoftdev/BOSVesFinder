<?php

declare(strict_types=1);

namespace Bvf;

use PDO;

/**
 * Add missing columns/indexes on older DBs. Safe when already up to date.
 * Used by bin/seed.php (verbose) and web bootstrap (silent).
 */
final class SchemaEnsure {
	public static function ensureLegacyColumns( PDO $pdo, bool $verbose = false ): void {
		self::ensureBvfTrips( $pdo, $verbose );
		self::ensureBvfCrew( $pdo, $verbose );
		self::ensureTripJobCertificatesTable( $pdo, $verbose );
	}

	/** @return list<string> */
	private static function tableColumns( PDO $pdo, string $table ): array {
		try {
			$st = $pdo->query( "SHOW COLUMNS FROM `{$table}`" );
		} catch ( \PDOException $e ) {
			unset( $e );
			return array();
		}
		if ( ! $st ) {
			return array();
		}
		$out = array();
		while ( $row = $st->fetch( PDO::FETCH_ASSOC ) ) {
			if ( isset( $row['Field'] ) ) {
				$out[] = (string) $row['Field'];
			}
		}
		return $out;
	}

	private static function log( bool $verbose, string $msg ): void {
		if ( $verbose ) {
			echo $msg . "\n";
		}
	}

	private static function ensureBvfTrips( PDO $pdo, bool $verbose ): void {
		$cols = self::tableColumns( $pdo, 'bvf_trips' );
		if ( $cols === array() ) {
			return;
		}
		if ( ! in_array( 'captain_user_id', $cols, true ) && in_array( 'user_id', $cols, true ) ) {
			$pdo->exec(
				'ALTER TABLE `bvf_trips` CHANGE COLUMN `user_id` `captain_user_id` bigint(20) unsigned NOT NULL'
			);
			self::log( $verbose, 'Renamed bvf_trips.user_id → captain_user_id.' );
			$cols = self::tableColumns( $pdo, 'bvf_trips' );
		}
		$fragments = array(
			'origin_label'      => 'ADD COLUMN `origin_label` text DEFAULT NULL',
			'destination_label' => 'ADD COLUMN `destination_label` text DEFAULT NULL',
			'started_at'        => 'ADD COLUMN `started_at` datetime DEFAULT NULL',
			'ended_at'          => 'ADD COLUMN `ended_at` datetime DEFAULT NULL',
			'created_at'        => 'ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
			'updated_at'        => 'ADD COLUMN `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
		);
		foreach ( $fragments as $name => $sql ) {
			if ( ! in_array( $name, $cols, true ) ) {
				$pdo->exec( "ALTER TABLE `bvf_trips` $sql" );
				self::log( $verbose, "Added bvf_trips.$name (schema upgrade)." );
				$cols[] = $name;
			}
		}
		$keys = array(
			'vessel_status'  => 'ADD KEY `vessel_status` (`vessel_id`,`status`)',
			'captain_status' => 'ADD KEY `captain_status` (`captain_user_id`,`status`)',
		);
		foreach ( $keys as $keyName => $sql ) {
			$idx = $pdo->query( "SHOW INDEX FROM `bvf_trips` WHERE Key_name = " . $pdo->quote( $keyName ) );
			if ( $idx && ! $idx->fetch() ) {
				try {
					$pdo->exec( "ALTER TABLE `bvf_trips` $sql" );
					self::log( $verbose, "Added bvf_trips index $keyName." );
				} catch ( \PDOException $e ) {
					unset( $e );
				}
			}
		}
	}

	private static function ensureBvfCrew( PDO $pdo, bool $verbose ): void {
		$cols = self::tableColumns( $pdo, 'bvf_crew' );
		if ( $cols === array() ) {
			return;
		}
		if ( ! in_array( 'app_user_id', $cols, true ) ) {
			$pdo->exec(
				'ALTER TABLE `bvf_crew` ADD COLUMN `app_user_id` bigint(20) unsigned DEFAULT NULL AFTER `id`'
			);
			self::log( $verbose, 'Added bvf_crew.app_user_id (schema upgrade).' );
			$cols = self::tableColumns( $pdo, 'bvf_crew' );
		}
		if ( ! in_array( 'created_by_user_id', $cols, true ) ) {
			try {
				$pdo->exec(
					'ALTER TABLE `bvf_crew` ADD COLUMN `created_by_user_id` bigint(20) unsigned DEFAULT NULL AFTER `app_user_id`'
				);
				self::log( $verbose, 'Added bvf_crew.created_by_user_id (schema upgrade).' );
			} catch ( \PDOException $e ) {
				unset( $e );
			}
		}
		$idx = $pdo->query( "SHOW INDEX FROM `bvf_crew` WHERE Key_name = 'created_by_user'" );
		if ( $idx && ! $idx->fetch() ) {
			try {
				$pdo->exec( 'ALTER TABLE `bvf_crew` ADD KEY `created_by_user` (`created_by_user_id`)' );
				self::log( $verbose, 'Added bvf_crew index created_by_user.' );
			} catch ( \PDOException $e ) {
				unset( $e );
			}
		}
		$idx = $pdo->query( "SHOW INDEX FROM `bvf_crew` WHERE Key_name = 'app_user'" );
		if ( $idx && ! $idx->fetch() ) {
			try {
				$pdo->exec( 'ALTER TABLE `bvf_crew` ADD KEY `app_user` (`app_user_id`)' );
				self::log( $verbose, 'Added bvf_crew index app_user.' );
			} catch ( \PDOException $e ) {
				unset( $e );
			}
		}
	}

	private static function ensureTripJobCertificatesTable( PDO $pdo, bool $verbose ): void {
		try {
			$pdo->exec(
				'CREATE TABLE IF NOT EXISTS `bvf_trip_job_certificates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trip_id` bigint(20) unsigned NOT NULL,
  `client_name` varchar(255) NOT NULL DEFAULT \'\',
  `client_address_phone` text,
  `client_vessel_name` varchar(255) NOT NULL DEFAULT \'\',
  `position_anchorage` varchar(255) NOT NULL DEFAULT \'\',
  `service_date` date DEFAULT NULL,
  `service_boat_name` varchar(255) NOT NULL DEFAULT \'\',
  `time_departed_port` varchar(64) NOT NULL DEFAULT \'\',
  `time_arrived_alongside` varchar(64) NOT NULL DEFAULT \'\',
  `time_departed_vessel` varchar(64) NOT NULL DEFAULT \'\',
  `time_arrived_port` varchar(64) NOT NULL DEFAULT \'\',
  `purpose_remarks` text,
  `master_signed_name` varchar(191) NOT NULL DEFAULT \'\',
  `coxswain_signed_name` varchar(191) NOT NULL DEFAULT \'\',
  `updated_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trip_id` (`trip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
			);
			self::log( $verbose, 'Ensured bvf_trip_job_certificates table exists.' );
		} catch ( \PDOException $e ) {
			unset( $e );
		}
	}
}
