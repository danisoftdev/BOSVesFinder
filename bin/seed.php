<?php

declare(strict_types=1);

/**
 * Apply schema (if present), ensure default users, and idempotent demo vessels / trips / crew.
 * Usage: php bin/seed.php
 */

$root = dirname( __DIR__ );
require $root . '/vendor/autoload.php';

if ( is_readable( $root . '/.env' ) ) {
	\Dotenv\Dotenv::createImmutable( $root )->load();
}

$pdo = Bvf\Db::pdo();

$sqlFile = $root . '/database/schema.sql';
if ( is_readable( $sqlFile ) ) {
	$sql = file_get_contents( $sqlFile );
	if ( is_string( $sql ) && $sql !== '' ) {
		foreach ( array_filter( array_map( 'trim', explode( ';', $sql ) ) ) as $chunk ) {
			if ( $chunk === '' || str_starts_with( $chunk, '--' ) ) {
				continue;
			}
			$pdo->exec( $chunk );
		}
		echo "Schema applied from database/schema.sql\n";
	}
}

$adminEmail = 'admin@example.local';
$adminPass  = 'admin';
$adminId    = ensure_user(
	$pdo,
	$adminEmail,
	$adminPass,
	'Administrator',
	Bvf\Auth\User::ROLE_SYSTEM_ADMIN
);
echo "User {$adminEmail} / {$adminPass} (id {$adminId})\n";

$captainEmail = 'captain@example.local';
$captainPass  = 'captain';
$captainId    = ensure_user(
	$pdo,
	$captainEmail,
	$captainPass,
	'Captain Demo',
	Bvf\Auth\User::ROLE_CAPTAIN
);
echo "User {$captainEmail} / {$captainPass} (id {$captainId}) — use for captain / GPS page\n";

Bvf\SchemaEnsure::ensureLegacyColumns( $pdo, true );
seed_demo_operational_data( $pdo, $captainId );
Bvf\Services\FleetCache::bust();
echo "Demo vessel, trips, crew, and sample position (if missing) are ready.\n";

/**
 * @return int user id
 */
function ensure_user( PDO $pdo, string $email, string $plain, string $displayName, string $role ): int {
	$st = $pdo->prepare( 'SELECT id FROM bvf_app_users WHERE email = ?' );
	$st->execute( array( $email ) );
	$existing = $st->fetchColumn();
	if ( $existing ) {
		return (int) $existing;
	}
	$hash = password_hash( $plain, PASSWORD_DEFAULT );
	$ins  = $pdo->prepare(
		'INSERT INTO bvf_app_users (email, password_hash, display_name, role) VALUES (?,?,?,?)'
	);
	$ins->execute( array( $email, $hash, $displayName, $role ) );
	return (int) $pdo->lastInsertId();
}

function seed_demo_operational_data( PDO $pdo, int $captainUserId ): void {
	$now = date( 'Y-m-d H:i:s' );

	$st = $pdo->prepare( 'SELECT id FROM bvf_vessels WHERE external_id = ?' );
	$st->execute( array( 'bvf_seed_demo' ) );
	$vesselId = $st->fetchColumn();
	if ( ! $vesselId ) {
		$ins = $pdo->prepare(
			'INSERT INTO bvf_vessels (name, external_id, status, assigned_captain_user_id, created_at, updated_at) VALUES (?,?,?,?,?,?)'
		);
		$ins->execute(
			array(
				'Benmarine One (demo)',
				'bvf_seed_demo',
				'active',
				$captainUserId,
				$now,
				$now,
			)
		);
		$vesselId = $pdo->lastInsertId();
	} else {
		$up = $pdo->prepare(
			'UPDATE bvf_vessels SET assigned_captain_user_id = ?, updated_at = ? WHERE id = ? AND (assigned_captain_user_id IS NULL OR assigned_captain_user_id = 0)'
		);
		$up->execute( array( $captainUserId, $now, $vesselId ) );
	}
	$vesselId = (int) $vesselId;

	$st = $pdo->prepare(
		"SELECT id FROM bvf_trips WHERE vessel_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1"
	);
	$st->execute( array( $vesselId ) );
	$tripId = $st->fetchColumn();
	if ( ! $tripId ) {
		$ins = $pdo->prepare(
			"INSERT INTO bvf_trips (vessel_id, captain_user_id, status, origin_label, destination_label, started_at, created_at, updated_at)
			VALUES (?,?,?,?,?,?,?,?)"
		);
		$ins->execute(
			array(
				$vesselId,
				$captainUserId,
				'active',
				'Demo port',
				'Offshore field',
				$now,
				$now,
				$now,
			)
		);
		$tripId = $pdo->lastInsertId();
	}
	$tripId = (int) $tripId;

	$st = $pdo->prepare( "SELECT COUNT(*) FROM bvf_trips WHERE vessel_id = ? AND status = 'completed'" );
	$st->execute( array( $vesselId ) );
	if ( (int) $st->fetchColumn() === 0 ) {
		$ended   = date( 'Y-m-d H:i:s', strtotime( '-3 days' ) );
		$started = date( 'Y-m-d H:i:s', strtotime( '-4 days' ) );
		$ins     = $pdo->prepare(
			"INSERT INTO bvf_trips (vessel_id, captain_user_id, status, origin_label, destination_label, started_at, ended_at, created_at, updated_at)
			VALUES (?,?,?,?,?,?,?,?,?)"
		);
		$ins->execute(
			array(
				$vesselId,
				$captainUserId,
				'completed',
				'Previous port',
				'Demo port',
				$started,
				$ended,
				$started,
				$ended,
			)
		);
	}

	$crewDefs = array(
		array( 'Alex Demo', 'engineer' ),
		array( 'Jordan Demo', 'deckhand' ),
	);
	$crewIds = array();
	foreach ( $crewDefs as $pair ) {
		$st = $pdo->prepare( 'SELECT id FROM bvf_crew WHERE display_name = ? AND role_slug = ?' );
		$st->execute( array( $pair[0], $pair[1] ) );
		$cid = $st->fetchColumn();
		if ( ! $cid ) {
			$ins = $pdo->prepare(
				'INSERT INTO bvf_crew (app_user_id, created_by_user_id, display_name, role_slug, vessel_id, created_at, updated_at) VALUES (NULL,NULL,?,?,?,?,?)'
			);
			$ins->execute( array( $pair[0], $pair[1], $vesselId, $now, $now ) );
			$cid = $pdo->lastInsertId();
		}
		$crewIds[] = (int) $cid;
	}

	foreach ( $crewIds as $cid ) {
		$st = $pdo->prepare( 'SELECT id FROM bvf_trip_crew WHERE trip_id = ? AND crew_id = ?' );
		$st->execute( array( $tripId, $cid ) );
		if ( ! $st->fetchColumn() ) {
			$ins = $pdo->prepare(
				'INSERT INTO bvf_trip_crew (trip_id, crew_id, onboard_confirmed, created_at) VALUES (?,?,?,?)'
			);
			$ins->execute( array( $tripId, $cid, 1, $now ) );
		}
	}

	$st = $pdo->prepare( 'SELECT COUNT(*) FROM bvf_location_logs WHERE trip_id = ?' );
	$st->execute( array( $tripId ) );
	if ( (int) $st->fetchColumn() === 0 ) {
		$ins = $pdo->prepare(
			'INSERT INTO bvf_location_logs (trip_id, user_id, latitude, longitude, accuracy_m, speed_knots, heading_degrees, source, created_at)
			VALUES (?,?,?,?,?,?,?,?,?)'
		);
		$ins->execute(
			array(
				$tripId,
				$captainUserId,
				4.1234567,
				5.9876543,
				12.5,
				8.2,
				180.0,
				'seed',
				$now,
			)
		);
	}
}
