<?php

declare(strict_types=1);

namespace Bvf\Services;

use Bvf\Db;
use PDO;

/**
 * Detect active trips with no position reports for a while; raise signal_loss alerts (deduped).
 */
final class SignalLossCron {
	private const STALE_MINUTES     = 45;
	private const DEDUPE_MINUTES   = 60;

	public static function run(): int {
		$pdo = Db::pdo();
		$sql = "SELECT t.id AS trip_id, t.vessel_id, t.captain_user_id,
(SELECT MAX(lo.created_at) FROM bvf_location_logs lo WHERE lo.trip_id = t.id) AS last_pos
			FROM bvf_trips t
			WHERE t.status = 'active'";
		$rows = $pdo->query( $sql )->fetchAll();
		$created = 0;
		$threshold = time() - self::STALE_MINUTES * 60;
		foreach ( $rows as $row ) {
			$last = $row['last_pos'] ?? null;
			$ts   = $last ? strtotime( (string) $last ) : false;
			if ( false !== $ts && $ts > $threshold ) {
				continue;
			}
			$tripId = (int) $row['trip_id'];
			if ( self::hasRecentSignalLoss( $pdo, $tripId ) ) {
				continue;
			}
			$now = date( 'Y-m-d H:i:s' );
			$payload = json_encode(
				array(
					'last_position_at' => $last,
					'threshold_minutes' => self::STALE_MINUTES,
				),
				JSON_UNESCAPED_UNICODE
			);
			$ins = $pdo->prepare(
				'INSERT INTO bvf_alerts (alert_type, severity, trip_id, vessel_id, user_id, payload, created_at)
				VALUES (?,?,?,?,?,?,?)'
			);
			$ins->execute(
				array(
					'signal_loss',
					'warning',
					$tripId,
					(int) $row['vessel_id'],
					(int) $row['captain_user_id'],
					$payload,
					$now,
				)
			);
			++$created;
		}
		return $created;
	}

	private static function hasRecentSignalLoss( PDO $pdo, int $tripId ): bool {
		$since = gmdate( 'Y-m-d H:i:s', time() - self::DEDUPE_MINUTES * 60 );
		$st    = $pdo->prepare(
			'SELECT COUNT(*) FROM bvf_alerts WHERE trip_id = ? AND alert_type = ? AND created_at >= ?'
		);
		$st->execute( array( $tripId, 'signal_loss', $since ) );
		return (int) $st->fetchColumn() > 0;
	}
}
