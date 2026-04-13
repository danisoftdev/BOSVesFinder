<?php

declare(strict_types=1);

namespace Bvf\Services;

use Bvf\Db;
use PDO;

final class GeofenceEvaluator {
	private const DEDUPE_SECONDS = 900;

	public static function distanceMeters( float $lat1, float $lng1, float $lat2, float $lng2 ): float {
		$earth = 6371000.0;
		$phi1  = deg2rad( $lat1 );
		$phi2  = deg2rad( $lat2 );
		$dphi  = deg2rad( $lat2 - $lat1 );
		$dl    = deg2rad( $lng2 - $lng1 );
		$a     = sin( $dphi / 2 ) ** 2 + cos( $phi1 ) * cos( $phi2 ) * sin( $dl / 2 ) ** 2;
		$c     = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
		return $earth * $c;
	}

	public static function evaluatePosition( int $tripId, int $vesselId, int $userId, float $lat, float $lng ): void {
		$pdo = Db::pdo();
		$st  = $pdo->query(
			'SELECT id, name, zone_type, center_lat, center_lng, radius_meters FROM bvf_geofences
			WHERE active = 1 AND center_lat IS NOT NULL AND center_lng IS NOT NULL
				AND radius_meters IS NOT NULL AND radius_meters > 0'
		);
		$zones = $st->fetchAll();
		if ( ! $zones ) {
			return;
		}
		$nowMysql = date( 'Y-m-d H:i:s' );
		foreach ( $zones as $z ) {
			$gid = (int) $z['id'];
			$clat = (float) $z['center_lat'];
			$clng = (float) $z['center_lng'];
			$rad  = (int) $z['radius_meters'];
			$type = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $z['zone_type'] ) ) ?: 'operational';
			$dist = self::distanceMeters( $lat, $lng, $clat, $clng );

			$violate = false;
			if ( 'restricted' === $type && $dist <= $rad ) {
				$violate = true;
			} elseif ( 'operational' === $type && $dist > $rad ) {
				$violate = true;
			}
			if ( ! $violate ) {
				continue;
			}
			if ( self::hasRecentGeofenceAlert( $pdo, $tripId, $gid ) ) {
				continue;
			}
			$reason = 'restricted' === $type ? 'inside_restricted_zone' : 'outside_operational_zone';
			$payload = json_encode(
				array(
					'geofence_id'   => $gid,
					'geofence_name' => $z['name'],
					'zone_type'     => $type,
					'distance_m'    => round( $dist, 1 ),
					'radius_m'      => $rad,
					'reason'        => $reason,
					'latitude'      => $lat,
					'longitude'     => $lng,
				),
				JSON_UNESCAPED_UNICODE
			);
			$ins = $pdo->prepare(
				'INSERT INTO bvf_alerts (alert_type, severity, trip_id, vessel_id, user_id, payload, created_at)
				VALUES (?,?,?,?,?,?,?)'
			);
			$ins->execute(
				array(
					'geofence_violation',
					'restricted' === $type ? 'high' : 'warning',
					$tripId,
					$vesselId,
					$userId,
					$payload,
					$nowMysql,
				)
			);
		}
	}

	private static function hasRecentGeofenceAlert( PDO $pdo, int $tripId, int $gfId ): bool {
		$since = gmdate( 'Y-m-d H:i:s', time() - self::DEDUPE_SECONDS );
		$like  = '%"geofence_id":' . $gfId . '%';
		$st    = $pdo->prepare(
			'SELECT COUNT(*) FROM bvf_alerts WHERE trip_id = ? AND alert_type = ? AND created_at >= ? AND payload LIKE ?'
		);
		$st->execute( array( $tripId, 'geofence_violation', $since, $like ) );
		return (int) $st->fetchColumn() > 0;
	}
}
