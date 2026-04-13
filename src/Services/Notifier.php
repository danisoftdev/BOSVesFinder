<?php

declare(strict_types=1);

namespace Bvf\Services;

use Bvf\Auth\UserRepository;
use Bvf\Db;
use Bvf\EnvConfig;

final class Notifier {
	/** @return string[] */
	public static function opsEmails(): array {
		$raw = '';
		try {
			$raw = ( new SettingsRepository() )->get( 'ops_notification_emails', '' );
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		if ( trim( $raw ) === '' ) {
			$raw = EnvConfig::string( 'BVF_OPS_EMAILS' );
		}
		if ( trim( $raw ) === '' ) {
			return array();
		}
		$parts = preg_split( '/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
		$out   = array();
		foreach ( $parts as $p ) {
			$e = filter_var( trim( $p ), FILTER_VALIDATE_EMAIL );
			if ( is_string( $e ) ) {
				$out[] = $e;
			}
		}
		return array_values( array_unique( $out ) );
	}

	public static function notifySos( int $alertId, int $tripId, int $vesselId, string $note, string $captainDisplayName, string $captainEmail ): void {
		$repo = new UserRepository();
		$to   = array_values(
			array_unique(
				array_merge( $repo->emailsForSosNotifications(), self::opsEmails() )
			)
		);
		if ( ! $to ) {
			return;
		}
		$st = Db::pdo()->prepare( 'SELECT name FROM bvf_vessels WHERE id = ?' );
		$st->execute( array( $vesselId ) );
		$name = (string) ( $st->fetchColumn() ?: '' );

		$subject = '[BOSVesFinder] SOS emergency';
		$url = rtrim( EnvConfig::string( 'APP_URL' ), '/' );
		if ( $url === '' && PHP_SAPI !== 'cli' ) {
			$url = EnvConfig::requestAppBaseUrl();
		}
		$captainLine = trim( $captainDisplayName ) !== ''
			? trim( $captainDisplayName ) . ' <' . trim( $captainEmail ) . '>'
			: trim( $captainEmail );
		$lines = array(
			'This SOS was submitted by the captain from the BOSVesFinder app.',
			'Captain: ' . $captainLine,
			'',
			'Alert ID: ' . $alertId,
			'Trip ID: ' . $tripId,
			'Vessel: ' . ( $name !== '' ? $name : '#' . $vesselId ),
			'Time: ' . date( 'Y-m-d H:i:s' ),
		);
		if ( $note !== '' ) {
			$lines[] = '';
			$lines[] = 'Emergency details (from captain):';
			$lines[] = $note;
		}
		$lines[] = '';
		$lines[] = $url;
		$body = implode( "\n", $lines );
		foreach ( $to as $email ) {
			@mail( $email, $subject, $body, "Content-Type: text/plain; charset=UTF-8\r\n" );
		}
	}
}
