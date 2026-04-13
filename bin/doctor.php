<?php

declare(strict_types=1);

/**
 * Quick DB + schema check. Usage: php bin/doctor.php
 */

$root = dirname( __DIR__ );
require $root . '/vendor/autoload.php';

if ( is_readable( $root . '/.env' ) ) {
	\Dotenv\Dotenv::createImmutable( $root )->load();
}

$pdo = Bvf\Db::pdo();
echo "DB: connected OK\n";

foreach ( array( 'bvf_app_users', 'bvf_vessels', 'bvf_trips', 'bvf_trip_job_certificates', 'bvf_crew', 'bvf_trip_crew', 'bvf_settings' ) as $t ) {
	try {
		$n = (int) $pdo->query( "SELECT COUNT(*) FROM `{$t}`" )->fetchColumn();
		echo "  {$t}: {$n} rows\n";
	} catch ( Throwable $e ) {
		echo "  {$t}: MISSING or error — " . $e->getMessage() . "\n";
	}
}

echo "\nColumns:\n";
foreach ( array( 'bvf_trips', 'bvf_crew' ) as $t ) {
	try {
		$st = $pdo->query( "SHOW COLUMNS FROM `{$t}`" );
		$fields = array();
		while ( $row = $st->fetch( PDO::FETCH_ASSOC ) ) {
			$fields[] = $row['Field'];
		}
		echo "  {$t}: " . implode( ', ', $fields ) . "\n";
	} catch ( Throwable $e ) {
		echo "  {$t}: " . $e->getMessage() . "\n";
	}
}

$need = array(
	'bvf_trips' => array( 'captain_user_id', 'origin_label', 'destination_label', 'started_at', 'ended_at', 'created_at', 'updated_at' ),
	'bvf_crew'  => array( 'app_user_id', 'created_by_user_id', 'display_name', 'role_slug', 'vessel_id' ),
);
echo "\nRequired columns:\n";
foreach ( $need as $t => $cols ) {
	$st = $pdo->query( "SHOW COLUMNS FROM `{$t}`" );
	$have = array();
	while ( $row = $st->fetch( PDO::FETCH_ASSOC ) ) {
		$have[] = $row['Field'];
	}
	foreach ( $cols as $c ) {
		$ok = in_array( $c, $have, true );
		echo '  ' . $t . '.' . $c . ': ' . ( $ok ? 'OK' : 'MISSING — open any page once or run php bin/seed.php' ) . "\n";
	}
}
