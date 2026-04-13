<?php

declare(strict_types=1);

namespace Bvf\Services;

use Bvf\Auth\User;
use Bvf\Db;
use PDO;

final class RestApiService {
	private const OFFLINE_AFTER_SECONDS = 600;
	private const MOVING_SPEED_KNOTS    = 0.5;

	private PDO $pdo;

	public function __construct() {
		$this->pdo = Db::pdo();
	}

	/** @return array{error:array{status:int,body:array<string,mixed>}}|array{data:mixed} */
	public function getFleetLive( User $user ): array {
		if ( ! $user->can( User::CAP_VIEW_FLEET ) ) {
			return $this->err( 'bvf_forbidden', 'Fleet map access required.', 403 );
		}
		$cached = FleetCache::get();
		if ( is_array( $cached ) ) {
			return array( 'data' => $cached );
		}
		$payload = $this->queryFleetLivePayload();
		if ( isset( $payload['error'] ) ) {
			return $payload;
		}
		FleetCache::set( $payload['data'] );
		return array( 'data' => $payload['data'] );
	}

	/** @return array{error:array{status:int,body:array<string,mixed>}}|array{data:mixed} */
	public function getFleetTracks( User $user, int $hours ): array {
		if ( ! $user->can( User::CAP_VIEW_FLEET ) ) {
			return $this->err( 'bvf_forbidden', 'Fleet map access required.', 403 );
		}
		if ( $hours < 1 ) {
			$hours = 48;
		}
		$hours   = min( 168, $hours );
		$maxPts = 160;
		$st      = $this->pdo->prepare(
			"SELECT t.id AS trip_id, v.name AS vessel_name FROM bvf_trips t
			INNER JOIN bvf_vessels v ON v.id = t.vessel_id
			WHERE t.status = 'active'"
		);
		$st->execute();
		$tripRows = $st->fetchAll();
		$out      = array();
		foreach ( $tripRows as $tr ) {
			$tid = (int) $tr['trip_id'];
			$ps  = $this->pdo->prepare(
				'SELECT latitude, longitude FROM bvf_location_logs
				WHERE trip_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
				ORDER BY id ASC'
			);
			$ps->execute( array( $tid, $hours ) );
			$pts = $ps->fetchAll();
			if ( ! $pts ) {
				continue;
			}
			$path = array();
			foreach ( $pts as $p ) {
				$path[] = array( round( (float) $p['latitude'], 6 ), round( (float) $p['longitude'], 6 ) );
			}
			$path = $this->decimateTrackPoints( $path, $maxPts );
			$out[] = array(
				'trip_id'     => $tid,
				'vessel_name' => $tr['vessel_name'],
				'points'      => $path,
			);
		}
		return array( 'data' => $out );
	}

	/** @return array{error:array{status:int,body:array<string,mixed>}}|array{data:mixed} */
	public function listVessels( User $user ): array {
		if ( ! $user->can( User::CAP_VIEW_FLEET ) ) {
			return $this->err( 'bvf_forbidden', 'Fleet map access required.', 403 );
		}
		$rows = $this->pdo->query(
			'SELECT id, name, external_id, status, assigned_captain_user_id, created_at FROM bvf_vessels ORDER BY name ASC'
		)->fetchAll();
		return array( 'data' => $rows );
	}

	/**
	 * Vessels the user may start a trip for: full list for fleet managers; assigned active vessels for captains.
	 *
	 * @return array{error:array{status:int,body:array<string,mixed>}}|array{data:list<array<string,mixed>>}
	 */
	public function listVesselsForCaptainTrip( User $user ): array {
		if ( ! $user->can( User::CAP_SUBMIT_TRACKING ) ) {
			return $this->err( 'bvf_forbidden', 'Tracking permission required.', 403 );
		}
		if ( $user->can( User::CAP_MANAGE_FLEET ) ) {
			return $this->listVessels( $user );
		}
		$st = $this->pdo->prepare(
			"SELECT id, name, external_id, status, assigned_captain_user_id, created_at FROM bvf_vessels
			WHERE assigned_captain_user_id = ? AND status = 'active' ORDER BY name ASC"
		);
		$st->execute( array( $user->id ) );
		return array( 'data' => $st->fetchAll() ?: array() );
	}

	/** @param array<string,mixed> $body */
	public function createVessel( User $user, array $body ): array {
		if ( ! $user->can( User::CAP_MANAGE_FLEET ) ) {
			return $this->err( 'bvf_forbidden', 'Fleet management permission required.', 403 );
		}
		$name = trim( (string) ( $body['name'] ?? '' ) );
		if ( $name === '' ) {
			return $this->err( 'bvf_invalid', 'Vessel name is required.', 400 );
		}
		$status = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) ( $body['status'] ?? 'active' ) ) );
		if ( ! in_array( $status, array( 'active', 'inactive', 'maintenance' ), true ) ) {
			$status = 'active';
		}
		$captainParam = $body['assigned_captain_user_id'] ?? null;
		$captainId    = ( null !== $captainParam && '' !== (string) $captainParam && (int) $captainParam > 0 )
			? (int) $captainParam : null;

		$now = date( 'Y-m-d H:i:s' );
		if ( null !== $captainId ) {
			$st = $this->pdo->prepare(
				'INSERT INTO bvf_vessels (name, external_id, status, assigned_captain_user_id, created_at, updated_at) VALUES (?,?,?,?,?,?)'
			);
			$st->execute(
				array(
					$name,
					trim( (string) ( $body['external_id'] ?? '' ) ),
					$status,
					$captainId,
					$now,
					$now,
				)
			);
		} else {
			$st = $this->pdo->prepare(
				'INSERT INTO bvf_vessels (name, external_id, status, created_at, updated_at) VALUES (?,?,?,?,?)'
			);
			$st->execute( array( $name, trim( (string) ( $body['external_id'] ?? '' ) ), $status, $now, $now ) );
		}
		return array( 'data' => array( 'id' => (int) $this->pdo->lastInsertId() ) );
	}

	/** @param array<string,mixed> $body */
	public function createTrip( User $user, array $body ): array {
		$canFleet = $user->can( User::CAP_MANAGE_FLEET );
		$canTrack = $user->can( User::CAP_SUBMIT_TRACKING );
		if ( ! $canFleet && ! $canTrack ) {
			return $this->err( 'bvf_forbidden', 'You cannot create trips.', 403 );
		}
		$vesselId = (int) ( $body['vessel_id'] ?? 0 );
		if ( $vesselId <= 0 ) {
			return $this->err( 'bvf_invalid', 'vessel_id is required.', 400 );
		}
		if ( $canFleet ) {
			$captainId = (int) ( $body['captain_user_id'] ?? 0 );
			if ( $captainId <= 0 ) {
				return $this->err( 'bvf_invalid', 'captain_user_id is required.', 400 );
			}
			$st = $this->pdo->prepare( 'SELECT id FROM bvf_vessels WHERE id = ?' );
			$st->execute( array( $vesselId ) );
			if ( ! $st->fetchColumn() ) {
				return $this->err( 'bvf_invalid', 'Vessel not found.', 404 );
			}
		} else {
			$captainId = $user->id;
			$st = $this->pdo->prepare(
				"SELECT id FROM bvf_vessels WHERE id = ? AND assigned_captain_user_id = ? AND status = 'active'"
			);
			$st->execute( array( $vesselId, $user->id ) );
			if ( ! $st->fetchColumn() ) {
				return $this->err(
					'bvf_forbidden',
					'That vessel is not assigned to you, or it is not active. Ask operations to assign you on Vessels.',
					403
				);
			}
		}
		$status = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) ( $body['status'] ?? 'active' ) ) );
		if ( ! in_array( $status, array( 'pending', 'active', 'completed', 'cancelled' ), true ) ) {
			$status = 'active';
		}
		if ( ! $canFleet && 'active' !== $status && 'pending' !== $status ) {
			return $this->err( 'bvf_invalid', 'Captains may only start trips as active or pending.', 400 );
		}
		$now          = date( 'Y-m-d H:i:s' );
		$originLabel  = trim( (string) ( $body['origin_label'] ?? '' ) );
		$destLabel    = trim( (string) ( $body['destination_label'] ?? '' ) );

		if ( 'active' === $status ) {
			$ins = $this->pdo->prepare(
				'INSERT INTO bvf_trips (vessel_id, captain_user_id, status, origin_label, destination_label, started_at, created_at, updated_at)
				VALUES (?,?,?,?,?,?,?,?)'
			);
			$ins->execute(
				array( $vesselId, $captainId, $status, $originLabel, $destLabel, $now, $now, $now )
			);
		} else {
			$ins = $this->pdo->prepare(
				'INSERT INTO bvf_trips (vessel_id, captain_user_id, status, origin_label, destination_label, created_at, updated_at)
				VALUES (?,?,?,?,?,?,?)'
			);
			$ins->execute( array( $vesselId, $captainId, $status, $originLabel, $destLabel, $now, $now ) );
		}
		if ( 'active' === $status ) {
			FleetCache::bust();
		}
		return array( 'data' => array( 'id' => (int) $this->pdo->lastInsertId() ) );
	}

	public function getMyActiveTrip( User $user ): array {
		if ( ! $user->can( User::CAP_SUBMIT_TRACKING ) ) {
			return $this->err( 'bvf_forbidden', 'Tracking permission required.', 403 );
		}
		$st = $this->pdo->prepare(
			"SELECT id, vessel_id, captain_user_id, status, origin_label, destination_label, started_at
			FROM bvf_trips WHERE captain_user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1"
		);
		$st->execute( array( $user->id ) );
		$row = $st->fetch();
		return array( 'data' => $row ?: null );
	}

	/**
	 * Captain's current workable trip: latest active, or if none the latest pending (ops-created trips).
	 *
	 * @return array{error:array{status:int,body:array<string,mixed>}}|array{data:array<string,mixed>|null}
	 */
	public function getMyCaptainCurrentTrip( User $user ): array {
		if ( ! $user->can( User::CAP_SUBMIT_TRACKING ) ) {
			return $this->err( 'bvf_forbidden', 'Tracking permission required.', 403 );
		}
		$st = $this->pdo->prepare(
			"SELECT t.id, t.vessel_id, t.captain_user_id, t.status, t.origin_label, t.destination_label, t.started_at, v.name AS vessel_name
			FROM bvf_trips t
			INNER JOIN bvf_vessels v ON v.id = t.vessel_id
			WHERE t.captain_user_id = ? AND t.status IN ('pending','active')
			ORDER BY (t.status = 'active') DESC, t.id DESC
			LIMIT 1"
		);
		$st->execute( array( $user->id ) );
		$row = $st->fetch();
		return array( 'data' => $row ?: null );
	}

	public function activateCaptainTrip( User $user, int $tripId ): array {
		if ( ! $user->can( User::CAP_SUBMIT_TRACKING ) ) {
			return $this->err( 'bvf_forbidden', 'Tracking permission required.', 403 );
		}
		if ( ! $this->userIsTripCaptain( $tripId, $user->id ) ) {
			return $this->err( 'bvf_forbidden', 'You are not the captain for this trip.', 403 );
		}
		$st = $this->pdo->prepare( 'SELECT id, status FROM bvf_trips WHERE id = ?' );
		$st->execute( array( $tripId ) );
		$row = $st->fetch();
		if ( ! $row ) {
			return $this->err( 'bvf_not_found', 'Trip not found.', 404 );
		}
		if ( 'pending' !== $row['status'] ) {
			return $this->err( 'bvf_invalid', 'Only pending trips can be started this way.', 400 );
		}
		$now = date( 'Y-m-d H:i:s' );
		$up  = $this->pdo->prepare(
			"UPDATE bvf_trips SET status = 'active', started_at = COALESCE(started_at, ?), updated_at = ? WHERE id = ? AND status = 'pending'"
		);
		$up->execute( array( $now, $now, $tripId ) );
		if ( $up->rowCount() === 0 ) {
			return $this->err( 'bvf_invalid', 'Trip could not be activated.', 400 );
		}
		FleetCache::bust();
		$st = $this->pdo->prepare(
			"SELECT t.id, t.vessel_id, t.captain_user_id, t.status, t.origin_label, t.destination_label, t.started_at, v.name AS vessel_name
			FROM bvf_trips t
			INNER JOIN bvf_vessels v ON v.id = t.vessel_id
			WHERE t.id = ?"
		);
		$st->execute( array( $tripId ) );
		$out = $st->fetch();
		return array( 'data' => $out ?: array( 'id' => $tripId, 'status' => 'active' ) );
	}

	public function listTrips( User $user, int $page, int $perPage, ?string $status ): array {
		if ( ! $user->can( User::CAP_VIEW_FLEET ) ) {
			return $this->err( 'bvf_forbidden', 'Fleet map access required.', 403 );
		}
		$page     = max( 1, $page );
		$perPage  = min( 100, max( 1, $perPage ) );
		$offset   = ( $page - 1 ) * $perPage;
		$validSt = array( 'pending', 'active', 'completed', 'cancelled' );
		$useStatus = $status && in_array( $status, $validSt, true );

		if ( $useStatus ) {
			$c = $this->pdo->prepare( 'SELECT COUNT(*) FROM bvf_trips WHERE status = ?' );
			$c->execute( array( $status ) );
			$total = (int) $c->fetchColumn();
			$st    = $this->pdo->prepare(
				"SELECT t.*, v.name AS vessel_name FROM bvf_trips t
				INNER JOIN bvf_vessels v ON v.id = t.vessel_id
				WHERE t.status = ? ORDER BY t.id DESC LIMIT ? OFFSET ?"
			);
			$st->execute( array( $status, $perPage, $offset ) );
		} else {
			$total = (int) $this->pdo->query( 'SELECT COUNT(*) FROM bvf_trips' )->fetchColumn();
			$st    = $this->pdo->prepare(
				"SELECT t.*, v.name AS vessel_name FROM bvf_trips t
				INNER JOIN bvf_vessels v ON v.id = t.vessel_id
				ORDER BY t.id DESC LIMIT ? OFFSET ?"
			);
			$st->execute( array( $perPage, $offset ) );
		}
		$rows      = $st->fetchAll();
		$maxPages  = max( 1, (int) ceil( $total / $perPage ) );
		return array(
			'data'    => $rows,
			'headers' => array(
				'X-BVF-Total'      => (string) $total,
				'X-BVF-TotalPages' => (string) $maxPages,
			),
		);
	}

	public function getTrip( User $user, int $id ): array {
		if ( ! $user->can( User::CAP_VIEW_FLEET ) ) {
			return $this->err( 'bvf_forbidden', 'Fleet map access required.', 403 );
		}
		$st = $this->pdo->prepare(
			"SELECT t.*, v.name AS vessel_name FROM bvf_trips t
			INNER JOIN bvf_vessels v ON v.id = t.vessel_id WHERE t.id = ?"
		);
		$st->execute( array( $id ) );
		$row = $st->fetch();
		if ( ! $row ) {
			return $this->err( 'bvf_not_found', 'Trip not found.', 404 );
		}
		return array( 'data' => $row );
	}

	/** @param array<string,mixed> $body */
	public function postTripPosition( User $user, int $tripId, array $body ): array {
		if ( ! $user->can( User::CAP_SUBMIT_TRACKING ) ) {
			return $this->err( 'bvf_forbidden', 'Tracking permission required.', 403 );
		}
		if ( ! $this->userIsTripCaptain( $tripId, $user->id ) ) {
			return $this->err( 'bvf_forbidden', 'You are not the captain for this trip.', 403 );
		}
		$rl = RateLimiter::allow( 'position', $user->id, 300, 60 );
		if ( ! $rl['ok'] ) {
			return $this->err( 'bvf_rate_limit', (string) $rl['message'], (int) $rl['status'] );
		}

		$st = $this->pdo->prepare( 'SELECT id, status, vessel_id FROM bvf_trips WHERE id = ?' );
		$st->execute( array( $tripId ) );
		$trip = $st->fetch();
		if ( ! $trip ) {
			return $this->err( 'bvf_not_found', 'Trip not found.', 404 );
		}
		if ( 'active' !== $trip['status'] ) {
			return $this->err( 'bvf_invalid', 'Trip is not active.', 400 );
		}
		$lat = (float) ( $body['latitude'] ?? 0 );
		$lng = (float) ( $body['longitude'] ?? 0 );
		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
			return $this->err( 'bvf_invalid', 'Invalid coordinates.', 400 );
		}
		$now = date( 'Y-m-d H:i:s' );
		$src  = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) ( $body['source'] ?? 'mobile' ) ) ) ?: 'mobile';
		$ins = $this->pdo->prepare(
			'INSERT INTO bvf_location_logs (trip_id, user_id, latitude, longitude, accuracy_m, speed_knots, heading_degrees, altitude_m, battery_percent, source, created_at)
			VALUES (?,?,?,?,?,?,?,?,?,?,?)'
		);
		$ins->execute(
			array(
				$tripId,
				$user->id,
				round( $lat, 7 ),
				round( $lng, 7 ),
				$this->nullableFloat( $body['accuracy_m'] ?? null ),
				$this->nullableFloat( $body['speed_knots'] ?? null ),
				$this->nullableFloat( $body['heading_degrees'] ?? null ),
				$this->nullableFloat( $body['altitude_m'] ?? null ),
				$this->nullableInt( $body['battery_percent'] ?? null ),
				$src,
				$now,
			)
		);
		FleetCache::bust();
		GeofenceEvaluator::evaluatePosition( $tripId, (int) $trip['vessel_id'], $user->id, $lat, $lng );
		$this->maybeBatteryLowAlert( $tripId, (int) $trip['vessel_id'], $user->id, $this->nullableInt( $body['battery_percent'] ?? null ) );
		return array( 'data' => array( 'id' => (int) $this->pdo->lastInsertId() ) );
	}

	public function completeTrip( User $user, int $tripId ): array {
		$canManage = $user->can( User::CAP_MANAGE_FLEET );
		$isCaptain = $user->can( User::CAP_SUBMIT_TRACKING ) && $this->userIsTripCaptain( $tripId, $user->id );
		if ( ! $canManage && ! $isCaptain ) {
			return $this->err( 'bvf_forbidden', 'You cannot complete this trip.', 403 );
		}
		$st = $this->pdo->prepare( 'SELECT status FROM bvf_trips WHERE id = ?' );
		$st->execute( array( $tripId ) );
		$row = $st->fetch();
		if ( ! $row ) {
			return $this->err( 'bvf_not_found', 'Trip not found.', 404 );
		}
		if ( ! in_array( (string) $row['status'], array( 'pending', 'active' ), true ) ) {
			return $this->err( 'bvf_invalid', 'This trip is already finished.', 400 );
		}
		$now = date( 'Y-m-d H:i:s' );
		$up  = $this->pdo->prepare(
			"UPDATE bvf_trips SET status = 'completed', ended_at = ?, updated_at = ? WHERE id = ? AND status IN ('pending','active')"
		);
		$up->execute( array( $now, $now, $tripId ) );
		if ( $up->rowCount() === 0 ) {
			return $this->err( 'bvf_invalid', 'This trip is already finished.', 400 );
		}
		FleetCache::bust();
		return array( 'data' => array( 'ok' => true ) );
	}

	/** @param array<string,mixed> $body */
	public function postSos( User $user, array $body ): array {
		if ( ! $user->can( User::CAP_SUBMIT_TRACKING ) ) {
			return $this->err( 'bvf_forbidden', 'Tracking permission required.', 403 );
		}
		$tripId = (int) ( $body['trip_id'] ?? 0 );
		if ( ! $this->userIsTripCaptain( $tripId, $user->id ) ) {
			return $this->err( 'bvf_forbidden', 'You are not the captain for this trip.', 403 );
		}
		$rl = RateLimiter::allow( 'sos', $user->id, 5, 600 );
		if ( ! $rl['ok'] ) {
			return $this->err( 'bvf_rate_limit', (string) $rl['message'], (int) $rl['status'] );
		}
		$st = $this->pdo->prepare( 'SELECT id, vessel_id, status FROM bvf_trips WHERE id = ?' );
		$st->execute( array( $tripId ) );
		$trip = $st->fetch();
		if ( ! $trip ) {
			return $this->err( 'bvf_not_found', 'Trip not found.', 404 );
		}
		if ( ! in_array( (string) $trip['status'], array( 'pending', 'active' ), true ) ) {
			return $this->err( 'bvf_invalid', 'SOS is only available for trips that are pending or active.', 400 );
		}
		$note = trim( (string) ( $body['note'] ?? $body['message'] ?? '' ) );
		if ( strlen( $note ) < 10 ) {
			return $this->err( 'bvf_invalid', 'Describe the emergency in at least 10 characters.', 400 );
		}
		if ( strlen( $note ) > 2000 ) {
			return $this->err( 'bvf_invalid', 'Emergency description is too long (max 2000 characters).', 400 );
		}
		$payload = json_encode(
			array(
				'note'       => $note,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
			),
			JSON_UNESCAPED_UNICODE
		);
		$now = date( 'Y-m-d H:i:s' );
		$ins = $this->pdo->prepare(
			'INSERT INTO bvf_alerts (alert_type, severity, trip_id, vessel_id, user_id, payload, created_at) VALUES (?,?,?,?,?,?,?)'
		);
		$ins->execute(
			array( 'sos', 'critical', $tripId, (int) $trip['vessel_id'], $user->id, $payload, $now )
		);
		$alertId = (int) $this->pdo->lastInsertId();
		Notifier::notifySos(
			$alertId,
			$tripId,
			(int) $trip['vessel_id'],
			$note,
			$user->displayName,
			$user->email
		);
		return array( 'data' => array( 'id' => $alertId ) );
	}

	/** @return array{error:array{status:int,body:array<string,mixed>}}|array{data:array<int,array<string,mixed>>} */
	private function queryFleetLivePayload(): array {
		$sql = "SELECT t.id AS trip_id, t.vessel_id, t.captain_user_id, v.name AS vessel_name,
				l.latitude, l.longitude, l.speed_knots, l.created_at AS last_report_at
			FROM bvf_trips t
			INNER JOIN bvf_vessels v ON v.id = t.vessel_id
			LEFT JOIN bvf_location_logs l ON l.id = (
				SELECT MAX(lo.id) FROM bvf_location_logs lo WHERE lo.trip_id = t.id
			)
			WHERE t.status = 'active'";
		$rows = $this->pdo->query( $sql )->fetchAll();
		$out  = array();
		foreach ( $rows as $row ) {
			$last  = $row['last_report_at'] ?? null;
			$speed = isset( $row['speed_knots'] ) ? (float) $row['speed_knots'] : null;
			$out[] = array(
				'trip_id'         => (int) $row['trip_id'],
				'vessel_id'       => (int) $row['vessel_id'],
				'vessel_name'     => $row['vessel_name'],
				'captain_user_id' => (int) $row['captain_user_id'],
				'latitude'        => isset( $row['latitude'] ) ? (float) $row['latitude'] : null,
				'longitude'       => isset( $row['longitude'] ) ? (float) $row['longitude'] : null,
				'speed_knots'     => $speed,
				'last_report_at'  => $last ? gmdate( 'c', strtotime( (string) $last ) ) : null,
				'computed_status' => $this->computeVesselStatus( $last, $speed ),
			);
		}
		return array( 'data' => $out );
	}

	private function computeVesselStatus( mixed $lastMysql, ?float $speedKnots ): string {
		if ( empty( $lastMysql ) ) {
			return 'offline';
		}
		$ts = strtotime( (string) $lastMysql );
		if ( false === $ts || ( time() - $ts ) > self::OFFLINE_AFTER_SECONDS ) {
			return 'offline';
		}
		if ( null !== $speedKnots && $speedKnots > self::MOVING_SPEED_KNOTS ) {
			return 'moving';
		}
		return 'idle';
	}

	private function userIsTripCaptain( int $tripId, int $userId ): bool {
		$st = $this->pdo->prepare( 'SELECT captain_user_id FROM bvf_trips WHERE id = ?' );
		$st->execute( array( $tripId ) );
		$c = $st->fetchColumn();
		return (int) $c === $userId;
	}

	/** Fleet ops may assign any trip; captains may assign only trips they command. */
	private function userCanAssignTripCrew( User $user, int $tripId ): bool {
		if ( $user->can( User::CAP_MANAGE_FLEET ) ) {
			return true;
		}
		return $user->can( User::CAP_MANAGE_CREW ) && $this->userIsTripCaptain( $tripId, $user->id );
	}

	/** @param array<int,array{0:float,1:float}> $path */
	private function decimateTrackPoints( array $path, int $max ): array {
		$n = count( $path );
		if ( $n <= $max ) {
			return $path;
		}
		$step = (int) ceil( $n / $max );
		$out  = array();
		for ( $i = 0; $i < $n; $i += $step ) {
			$out[] = $path[ $i ];
		}
		$last = $path[ $n - 1 ];
		$tail = end( $out );
		if ( ! $out ) {
			return array( $last );
		}
		if ( ! is_array( $tail ) || abs( (float) $tail[0] - (float) $last[0] ) > 1e-5 || abs( (float) $tail[1] - (float) $last[1] ) > 1e-5 ) {
			$out[] = $last;
		}
		return $out;
	}

	private function nullableFloat( mixed $v ): ?float {
		if ( null === $v || '' === $v ) {
			return null;
		}
		return is_numeric( $v ) ? (float) $v : null;
	}

	private function nullableInt( mixed $v ): ?int {
		if ( null === $v || '' === $v ) {
			return null;
		}
		return is_numeric( $v ) ? (int) $v : null;
	}

	private function maybeBatteryLowAlert( int $tripId, int $vesselId, int $userId, ?int $batteryPercent ): void {
		if ( null === $batteryPercent || $batteryPercent > 15 || $batteryPercent < 0 || $batteryPercent > 100 ) {
			return;
		}
		$since = gmdate( 'Y-m-d H:i:s', time() - 3600 );
		$st    = $this->pdo->prepare(
			"SELECT COUNT(*) FROM bvf_alerts WHERE trip_id = ? AND alert_type = 'battery_low' AND created_at >= ?"
		);
		$st->execute( array( $tripId, $since ) );
		if ( (int) $st->fetchColumn() > 0 ) {
			return;
		}
		$now     = date( 'Y-m-d H:i:s' );
		$payload = json_encode( array( 'battery_percent' => $batteryPercent ), JSON_UNESCAPED_UNICODE );
		$ins     = $this->pdo->prepare(
			'INSERT INTO bvf_alerts (alert_type, severity, trip_id, vessel_id, user_id, payload, created_at) VALUES (?,?,?,?,?,?,?)'
		);
		$ins->execute( array( 'battery_low', 'warning', $tripId, $vesselId, $userId, $payload, $now ) );
	}

	public function listCrew( User $user ): array {
		if ( ! $user->can( User::CAP_VIEW_CREW ) ) {
			return $this->err( 'bvf_forbidden', 'Crew registry access required.', 403 );
		}
		$rows = $this->pdo->query(
			'SELECT c.id, c.app_user_id, c.created_by_user_id, c.display_name, c.role_slug, c.vessel_id, c.created_at, c.updated_at,
				v.name AS vessel_name,
				au.display_name AS linked_user_display_name, au.email AS linked_user_email,
				cu.display_name AS created_by_display_name, cu.email AS created_by_email, cu.role AS created_by_role
			FROM bvf_crew c
			LEFT JOIN bvf_vessels v ON v.id = c.vessel_id
			LEFT JOIN bvf_app_users au ON au.id = c.app_user_id
			LEFT JOIN bvf_app_users cu ON cu.id = c.created_by_user_id
			ORDER BY c.display_name ASC, c.id ASC'
		)->fetchAll();
		return array( 'data' => $rows );
	}

	/** @param array<string,mixed> $body */
	public function createCrew( User $user, array $body ): array {
		if ( ! $user->can( User::CAP_MANAGE_CREW ) ) {
			return $this->err( 'bvf_forbidden', 'Crew management permission required.', 403 );
		}
		$name = trim( (string) ( $body['display_name'] ?? '' ) );
		if ( $name === '' ) {
			return $this->err( 'bvf_invalid', 'display_name is required.', 400 );
		}
		$roleSlug = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) ( $body['role_slug'] ?? 'crew' ) ) ) ?: 'crew';
		$vesselId = $this->nullableId( $body['vessel_id'] ?? null );
		$appUid   = $this->nullableId( $body['app_user_id'] ?? null );
		if ( null !== $appUid && ! $this->appUserExists( $appUid ) ) {
			return $this->err( 'bvf_invalid', 'app_user_id not found.', 400 );
		}
		if ( null !== $vesselId && ! $this->vesselExists( $vesselId ) ) {
			return $this->err( 'bvf_invalid', 'vessel_id not found.', 400 );
		}
		$now = date( 'Y-m-d H:i:s' );
		$ins = $this->pdo->prepare(
			'INSERT INTO bvf_crew (app_user_id, created_by_user_id, display_name, role_slug, vessel_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?)'
		);
		$ins->execute( array( $appUid, $user->id, $name, $roleSlug, $vesselId, $now, $now ) );
		return array( 'data' => array( 'id' => (int) $this->pdo->lastInsertId() ) );
	}

	/** @param array<string,mixed> $body */
	public function updateCrew( User $user, int $id, array $body ): array {
		if ( ! $user->can( User::CAP_MANAGE_CREW ) ) {
			return $this->err( 'bvf_forbidden', 'Crew management permission required.', 403 );
		}
		if ( ! $this->crewExists( $id ) ) {
			return $this->err( 'bvf_not_found', 'Crew record not found.', 404 );
		}
		$name = array_key_exists( 'display_name', $body ) ? trim( (string) $body['display_name'] ) : null;
		if ( is_string( $name ) && $name === '' ) {
			return $this->err( 'bvf_invalid', 'display_name cannot be empty.', 400 );
		}
		$roleSlug = null;
		if ( array_key_exists( 'role_slug', $body ) ) {
			$roleSlug = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $body['role_slug'] ) ) ?: 'crew';
		}
		$vesselId = array_key_exists( 'vessel_id', $body ) ? $this->nullableId( $body['vessel_id'] ) : false;
		$appUid   = array_key_exists( 'app_user_id', $body ) ? $this->nullableId( $body['app_user_id'] ) : false;
		if ( false !== $appUid && null !== $appUid && ! $this->appUserExists( $appUid ) ) {
			return $this->err( 'bvf_invalid', 'app_user_id not found.', 400 );
		}
		if ( false !== $vesselId && null !== $vesselId && ! $this->vesselExists( $vesselId ) ) {
			return $this->err( 'bvf_invalid', 'vessel_id not found.', 400 );
		}
		$st = $this->pdo->prepare( 'SELECT display_name, role_slug, vessel_id, app_user_id FROM bvf_crew WHERE id = ?' );
		$st->execute( array( $id ) );
		$row = $st->fetch();
		if ( ! $row ) {
			return $this->err( 'bvf_not_found', 'Crew record not found.', 404 );
		}
		$dn = null !== $name ? $name : (string) $row['display_name'];
		$rs  = null !== $roleSlug ? $roleSlug : (string) $row['role_slug'];
		$vid = false !== $vesselId ? $vesselId : ( $row['vessel_id'] !== null ? (int) $row['vessel_id'] : null );
		$au  = false !== $appUid ? $appUid : ( $row['app_user_id'] !== null ? (int) $row['app_user_id'] : null );
		$now = date( 'Y-m-d H:i:s' );
		$up  = $this->pdo->prepare(
			'UPDATE bvf_crew SET display_name = ?, role_slug = ?, vessel_id = ?, app_user_id = ?, updated_at = ? WHERE id = ?'
		);
		$up->execute( array( $dn, $rs, $vid, $au, $now, $id ) );
		return array( 'data' => array( 'ok' => true ) );
	}

	public function deleteCrew( User $user, int $id ): array {
		if ( ! $user->can( User::CAP_MANAGE_CREW ) ) {
			return $this->err( 'bvf_forbidden', 'Crew management permission required.', 403 );
		}
		if ( ! $this->crewExists( $id ) ) {
			return $this->err( 'bvf_not_found', 'Crew record not found.', 404 );
		}
		$delLinks = $this->pdo->prepare( 'DELETE FROM bvf_trip_crew WHERE crew_id = ?' );
		$delLinks->execute( array( $id ) );
		$del = $this->pdo->prepare( 'DELETE FROM bvf_crew WHERE id = ?' );
		$del->execute( array( $id ) );
		return array( 'data' => array( 'ok' => true ) );
	}

	public function listTripCrew( User $user, int $tripId ): array {
		$e = $this->assertTripViewable( $user, $tripId );
		if ( null !== $e ) {
			return $e;
		}
		$st = $this->pdo->prepare(
			'SELECT tc.id AS trip_crew_id, tc.trip_id, tc.crew_id, tc.onboard_confirmed, tc.created_at,
				c.display_name, c.role_slug, c.vessel_id
			FROM bvf_trip_crew tc
			INNER JOIN bvf_crew c ON c.id = tc.crew_id
			WHERE tc.trip_id = ?
			ORDER BY c.display_name ASC'
		);
		$st->execute( array( $tripId ) );
		return array( 'data' => $st->fetchAll() );
	}

	/** @param array<string,mixed> $body */
	public function addTripCrew( User $user, int $tripId, array $body ): array {
		if ( ! $this->userCanAssignTripCrew( $user, $tripId ) ) {
			return $this->err( 'bvf_forbidden', 'You cannot assign crew to this trip.', 403 );
		}
		$e = $this->assertTripViewable( $user, $tripId );
		if ( null !== $e ) {
			return $e;
		}
		$crewId = (int) ( $body['crew_id'] ?? 0 );
		if ( $crewId <= 0 || ! $this->crewExists( $crewId ) ) {
			return $this->err( 'bvf_invalid', 'crew_id is required.', 400 );
		}
		$now = date( 'Y-m-d H:i:s' );
		try {
			$ins = $this->pdo->prepare(
				'INSERT INTO bvf_trip_crew (trip_id, crew_id, onboard_confirmed, created_at) VALUES (?,?,0,?)'
			);
			$ins->execute( array( $tripId, $crewId, $now ) );
		} catch ( \PDOException $ex ) {
			if ( (int) ( $ex->errorInfo[1] ?? 0 ) === 1062 ) {
				return $this->err( 'bvf_invalid', 'Crew member already assigned to this trip.', 409 );
			}
			throw $ex;
		}
		return array( 'data' => array( 'id' => (int) $this->pdo->lastInsertId() ) );
	}

	public function removeTripCrew( User $user, int $tripId, int $crewId ): array {
		if ( ! $this->userCanAssignTripCrew( $user, $tripId ) ) {
			return $this->err( 'bvf_forbidden', 'You cannot change the crew roster for this trip.', 403 );
		}
		$e = $this->assertTripViewable( $user, $tripId );
		if ( null !== $e ) {
			return $e;
		}
		$del = $this->pdo->prepare( 'DELETE FROM bvf_trip_crew WHERE trip_id = ? AND crew_id = ?' );
		$del->execute( array( $tripId, $crewId ) );
		return array( 'data' => array( 'ok' => true, 'removed' => $del->rowCount() > 0 ) );
	}

	/** @param array<string,mixed> $body */
	public function setTripCrewOnboard( User $user, int $tripId, int $crewId, array $body ): array {
		$canManage = $user->can( User::CAP_MANAGE_FLEET );
		$isCaptain = $user->can( User::CAP_SUBMIT_TRACKING ) && $this->userIsTripCaptain( $tripId, $user->id );
		if ( ! $canManage && ! $isCaptain ) {
			return $this->err( 'bvf_forbidden', 'You cannot update crew roster for this trip.', 403 );
		}
		$e = $this->assertTripViewable( $user, $tripId );
		if ( null !== $e ) {
			return $e;
		}
		$on = isset( $body['onboard_confirmed'] ) ? (int) (bool) $body['onboard_confirmed'] : null;
		if ( null === $on ) {
			return $this->err( 'bvf_invalid', 'onboard_confirmed is required.', 400 );
		}
		$up = $this->pdo->prepare(
			'UPDATE bvf_trip_crew SET onboard_confirmed = ? WHERE trip_id = ? AND crew_id = ?'
		);
		$up->execute( array( $on, $tripId, $crewId ) );
		if ( $up->rowCount() === 0 ) {
			return $this->err( 'bvf_not_found', 'Trip crew link not found.', 404 );
		}
		return array( 'data' => array( 'ok' => true ) );
	}

	public function listFuelLogs( User $user, int $tripId ): array {
		$e = $this->assertTripViewable( $user, $tripId );
		if ( null !== $e ) {
			return $e;
		}
		$st = $this->pdo->prepare(
			'SELECT id, trip_id, user_id, fuel_loaded_litres, fuel_consumed_litres, notes, created_at
			FROM bvf_fuel_logs WHERE trip_id = ? ORDER BY id DESC'
		);
		$st->execute( array( $tripId ) );
		return array( 'data' => $st->fetchAll() );
	}

	/** @param array<string,mixed> $body */
	public function postFuelLog( User $user, int $tripId, array $body ): array {
		if ( ! $this->userCanPostFuel( $user, $tripId ) ) {
			return $this->err( 'bvf_forbidden', 'You cannot add fuel entries for this trip.', 403 );
		}
		$st = $this->pdo->prepare( 'SELECT id, status FROM bvf_trips WHERE id = ?' );
		$st->execute( array( $tripId ) );
		$trip = $st->fetch();
		if ( ! $trip ) {
			return $this->err( 'bvf_not_found', 'Trip not found.', 404 );
		}
		if ( 'active' !== $trip['status'] && ! $user->can( User::CAP_MANAGE_FLEET ) ) {
			return $this->err( 'bvf_invalid', 'Trip is not active.', 400 );
		}
		$loaded   = $this->nullableDecimal( $body['fuel_loaded_litres'] ?? null );
		$consumed = $this->nullableDecimal( $body['fuel_consumed_litres'] ?? null );
		if ( null === $loaded || null === $consumed ) {
			return $this->err( 'bvf_invalid', 'fuel_loaded_litres and fuel_consumed_litres are required (use 0 if none).', 400 );
		}
		$lf = (float) $loaded;
		$cf = (float) $consumed;
		if ( $lf < 0 || $cf < 0 ) {
			return $this->err( 'bvf_invalid', 'Fuel litres cannot be negative.', 400 );
		}
		if ( $lf > 999999999.999 || $cf > 999999999.999 ) {
			return $this->err( 'bvf_invalid', 'Fuel values exceed allowed range.', 400 );
		}
		$rl = RateLimiter::allow( 'fuel_log', $user->id, 40, 3600 );
		if ( ! $rl['ok'] ) {
			return $this->err( 'bvf_rate_limit', (string) $rl['message'], (int) $rl['status'] );
		}
		$notes = trim( (string) ( $body['notes'] ?? '' ) );
		$notes = $notes !== '' ? $notes : null;
		$now   = date( 'Y-m-d H:i:s' );
		$ins = $this->pdo->prepare(
			'INSERT INTO bvf_fuel_logs (trip_id, user_id, fuel_loaded_litres, fuel_consumed_litres, notes, created_at) VALUES (?,?,?,?,?,?)'
		);
		$ins->execute( array( $tripId, $user->id, $loaded, $consumed, $notes, $now ) );
		return array( 'data' => array( 'id' => (int) $this->pdo->lastInsertId() ) );
	}

	/** @return array{error:array{status:int,body:array<string,mixed>}}|null */
	private function assertTripViewable( User $user, int $tripId ): ?array {
		if ( ! $user->can( User::CAP_VIEW_FLEET ) && ! $this->userIsTripCaptain( $tripId, $user->id ) ) {
			return $this->err( 'bvf_forbidden', 'Fleet access required.', 403 );
		}
		$st = $this->pdo->prepare( 'SELECT id FROM bvf_trips WHERE id = ?' );
		$st->execute( array( $tripId ) );
		if ( ! $st->fetch() ) {
			return $this->err( 'bvf_not_found', 'Trip not found.', 404 );
		}
		return null;
	}

	private function userCanPostFuel( User $user, int $tripId ): bool {
		if ( $user->can( User::CAP_MANAGE_FLEET ) ) {
			return true;
		}
		if ( ! $user->can( User::CAP_SUBMIT_TRACKING ) ) {
			return false;
		}
		if ( ! $this->userIsTripCaptain( $tripId, $user->id ) ) {
			return false;
		}
		$st = $this->pdo->prepare( 'SELECT status FROM bvf_trips WHERE id = ?' );
		$st->execute( array( $tripId ) );
		$s = $st->fetchColumn();
		return 'active' === $s;
	}

	private function crewExists( int $id ): bool {
		$st = $this->pdo->prepare( 'SELECT id FROM bvf_crew WHERE id = ?' );
		$st->execute( array( $id ) );
		return (bool) $st->fetch();
	}

	private function vesselExists( int $id ): bool {
		$st = $this->pdo->prepare( 'SELECT id FROM bvf_vessels WHERE id = ?' );
		$st->execute( array( $id ) );
		return (bool) $st->fetch();
	}

	private function appUserExists( int $id ): bool {
		$st = $this->pdo->prepare( 'SELECT id FROM bvf_app_users WHERE id = ?' );
		$st->execute( array( $id ) );
		return (bool) $st->fetch();
	}

	/** @param mixed $v */
	private function nullableId( mixed $v ): ?int {
		if ( null === $v || '' === $v ) {
			return null;
		}
		$i = (int) $v;
		return $i > 0 ? $i : null;
	}

	/** @param mixed $v */
	private function nullableDecimal( mixed $v ): ?string {
		if ( null === $v || '' === $v ) {
			return null;
		}
		if ( ! is_numeric( $v ) ) {
			return null;
		}
		return number_format( (float) $v, 3, '.', '' );
	}

	private function userCanAccessTripJobCertificate( User $user, int $tripId ): bool {
		if ( $user->can( User::CAP_MANAGE_FLEET ) ) {
			return true;
		}
		return $user->can( User::CAP_SUBMIT_TRACKING ) && $this->userIsTripCaptain( $tripId, $user->id );
	}

	/**
	 * @return array{error:array{status:int,body:array<string,mixed>}}|array{data:array<string,mixed>}
	 */
	public function getTripJobCertificate( User $user, int $tripId ): array {
		if ( ! $user->can( User::CAP_VIEW_FLEET ) ) {
			return $this->err( 'bvf_forbidden', 'Fleet access required.', 403 );
		}
		if ( ! $this->userCanAccessTripJobCertificate( $user, $tripId ) ) {
			return $this->err( 'bvf_forbidden', 'You cannot access this certificate.', 403 );
		}
		$st = $this->pdo->prepare(
			"SELECT t.id AS trip_id, t.vessel_id, t.status AS trip_status, t.origin_label, t.destination_label,
			t.started_at, t.ended_at, v.name AS vessel_name
			FROM bvf_trips t
			INNER JOIN bvf_vessels v ON v.id = t.vessel_id
			WHERE t.id = ?"
		);
		$st->execute( array( $tripId ) );
		$trip = $st->fetch();
		if ( ! $trip ) {
			return $this->err( 'bvf_not_found', 'Trip not found.', 404 );
		}
		$cst = $this->pdo->prepare( 'SELECT * FROM bvf_trip_job_certificates WHERE trip_id = ?' );
		$cst->execute( array( $tripId ) );
		$cert = $cst->fetch();
		$ref  = null;
		if ( is_array( $cert ) && isset( $cert['id'] ) ) {
			$ref = sprintf( 'BM-%06d', (int) $cert['id'] );
		}
		$data = array(
			'trip_id'                => (int) $trip['trip_id'],
			'certificate_id'         => is_array( $cert ) && isset( $cert['id'] ) ? (int) $cert['id'] : null,
			'form_reference'         => $ref,
			'client_name'            => is_array( $cert ) ? (string) ( $cert['client_name'] ?? '' ) : '',
			'client_address_phone'   => is_array( $cert ) ? (string) ( $cert['client_address_phone'] ?? '' ) : '',
			'client_vessel_name'     => is_array( $cert ) ? (string) ( $cert['client_vessel_name'] ?? '' ) : (string) ( $trip['vessel_name'] ?? '' ),
			'position_anchorage'     => is_array( $cert ) ? (string) ( $cert['position_anchorage'] ?? '' ) : '',
			'service_date'           => is_array( $cert ) && ! empty( $cert['service_date'] ) ? (string) $cert['service_date'] : null,
			'service_boat_name'      => is_array( $cert ) ? (string) ( $cert['service_boat_name'] ?? '' ) : (string) ( $trip['vessel_name'] ?? '' ),
			'time_departed_port'     => is_array( $cert ) ? (string) ( $cert['time_departed_port'] ?? '' ) : '',
			'time_arrived_alongside' => is_array( $cert ) ? (string) ( $cert['time_arrived_alongside'] ?? '' ) : '',
			'time_departed_vessel'   => is_array( $cert ) ? (string) ( $cert['time_departed_vessel'] ?? '' ) : '',
			'time_arrived_port'      => is_array( $cert ) ? (string) ( $cert['time_arrived_port'] ?? '' ) : '',
			'purpose_remarks'        => is_array( $cert ) ? (string) ( $cert['purpose_remarks'] ?? '' ) : '',
			'master_signed_name'     => is_array( $cert ) ? (string) ( $cert['master_signed_name'] ?? '' ) : '',
			'coxswain_signed_name'   => is_array( $cert ) ? (string) ( $cert['coxswain_signed_name'] ?? '' ) : '',
			'trip_vessel_name'       => (string) ( $trip['vessel_name'] ?? '' ),
			'trip_origin_label'      => (string) ( $trip['origin_label'] ?? '' ),
			'trip_destination_label' => (string) ( $trip['destination_label'] ?? '' ),
		);
		if ( null === $data['service_date'] && ! empty( $trip['started_at'] ) ) {
			$data['service_date'] = gmdate( 'Y-m-d', strtotime( (string) $trip['started_at'] ) );
		}
		return array( 'data' => $data );
	}

	/** @param array<string,mixed> $body */
	public function saveTripJobCertificate( User $user, int $tripId, array $body ): array {
		if ( ! $user->can( User::CAP_VIEW_FLEET ) ) {
			return $this->err( 'bvf_forbidden', 'Fleet access required.', 403 );
		}
		if ( ! $this->userCanAccessTripJobCertificate( $user, $tripId ) ) {
			return $this->err( 'bvf_forbidden', 'You cannot edit this certificate.', 403 );
		}
		$st = $this->pdo->prepare( 'SELECT id FROM bvf_trips WHERE id = ?' );
		$st->execute( array( $tripId ) );
		if ( ! $st->fetchColumn() ) {
			return $this->err( 'bvf_not_found', 'Trip not found.', 404 );
		}
		$clientName          = trim( (string) ( $body['client_name'] ?? '' ) );
		$clientAddr            = trim( (string) ( $body['client_address_phone'] ?? '' ) );
		$clientVessel        = trim( (string) ( $body['client_vessel_name'] ?? '' ) );
		$positionAnch        = trim( (string) ( $body['position_anchorage'] ?? '' ) );
		$serviceDate         = trim( (string) ( $body['service_date'] ?? '' ) );
		$serviceDateSql      = ( $serviceDate !== '' && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $serviceDate ) ) ? $serviceDate : null;
		$serviceBoat         = trim( (string) ( $body['service_boat_name'] ?? '' ) );
		$tDepPort            = trim( (string) ( $body['time_departed_port'] ?? '' ) );
		$tArrAlong             = trim( (string) ( $body['time_arrived_alongside'] ?? '' ) );
		$tDepVes               = trim( (string) ( $body['time_departed_vessel'] ?? '' ) );
		$tArrPort            = trim( (string) ( $body['time_arrived_port'] ?? '' ) );
		$purpose             = trim( (string) ( $body['purpose_remarks'] ?? '' ) );
		$master              = trim( (string) ( $body['master_signed_name'] ?? '' ) );
		$coxswain            = trim( (string) ( $body['coxswain_signed_name'] ?? '' ) );
		$now = date( 'Y-m-d H:i:s' );
		$ins = $this->pdo->prepare(
			'INSERT INTO bvf_trip_job_certificates (
				trip_id, client_name, client_address_phone, client_vessel_name, position_anchorage, service_date,
				service_boat_name, time_departed_port, time_arrived_alongside, time_departed_vessel, time_arrived_port,
				purpose_remarks, master_signed_name, coxswain_signed_name, updated_by_user_id, created_at, updated_at
			) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
			ON DUPLICATE KEY UPDATE
				client_name = VALUES(client_name),
				client_address_phone = VALUES(client_address_phone),
				client_vessel_name = VALUES(client_vessel_name),
				position_anchorage = VALUES(position_anchorage),
				service_date = VALUES(service_date),
				service_boat_name = VALUES(service_boat_name),
				time_departed_port = VALUES(time_departed_port),
				time_arrived_alongside = VALUES(time_arrived_alongside),
				time_departed_vessel = VALUES(time_departed_vessel),
				time_arrived_port = VALUES(time_arrived_port),
				purpose_remarks = VALUES(purpose_remarks),
				master_signed_name = VALUES(master_signed_name),
				coxswain_signed_name = VALUES(coxswain_signed_name),
				updated_by_user_id = VALUES(updated_by_user_id),
				updated_at = VALUES(updated_at)'
		);
		$ins->execute(
			array(
				$tripId,
				$clientName,
				$clientAddr !== '' ? $clientAddr : null,
				$clientVessel,
				$positionAnch,
				$serviceDateSql,
				$serviceBoat,
				$tDepPort,
				$tArrAlong,
				$tDepVes,
				$tArrPort,
				$purpose !== '' ? $purpose : null,
				$master,
				$coxswain,
				$user->id,
				$now,
				$now,
			)
		);
		return $this->getTripJobCertificate( $user, $tripId );
	}

	/** @return array{error:array{status:int,body:array<string,mixed>}} */
	private function err( string $code, string $message, int $status ): array {
		return array(
			'error' => array(
				'status' => $status,
				'body'   => array(
					'code'    => $code,
					'message' => $message,
					'data'    => array( 'status' => $status ),
				),
			),
		);
	}
}
