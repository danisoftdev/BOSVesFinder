<?php

declare(strict_types=1);

namespace Bvf;

/**
 * Environment values: process env (Docker) overrides .env-loaded $_ENV.
 */
final class EnvConfig {
	public static function string( string $key, string $default = '' ): string {
		$g = getenv( $key );
		if ( is_string( $g ) && $g !== '' ) {
			return $g;
		}
		if ( isset( $_ENV[ $key ] ) && is_string( $_ENV[ $key ] ) && $_ENV[ $key ] !== '' ) {
			return $_ENV[ $key ];
		}
		return $default;
	}

	/**
	 * True when the inbound request is HTTPS (direct or, if TRUST_X_FORWARDED_PROTO=1, X-Forwarded-Proto).
	 */
	public static function requestIsHttps(): bool {
		if ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) {
			return true;
		}
		if ( self::envFlag( 'TRUST_X_FORWARDED_PROTO' ) ) {
			$proto = strtolower( (string) ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) );
			return $proto === 'https';
		}
		return false;
	}

	/**
	 * Session cookie Secure flag: SESSION_COOKIE_SECURE if set, else https APP_URL or request HTTPS.
	 */
	public static function sessionCookieSecure(): bool {
		$explicit = self::string( 'SESSION_COOKIE_SECURE' );
		if ( $explicit !== '' ) {
			return self::truthy( $explicit );
		}
		$app = strtolower( self::string( 'APP_URL' ) );
		if ( str_starts_with( $app, 'https://' ) ) {
			return true;
		}
		return self::requestIsHttps();
	}

	/**
	 * SameSite for session cookie (Lax, Strict, or None). None implies Secure.
	 */
	public static function sessionSameSite(): string {
		$raw = self::string( 'SESSION_COOKIE_SAMESITE', 'Lax' );
		$s = ucfirst( strtolower( trim( $raw ) ) );
		if ( in_array( $s, array( 'Lax', 'Strict', 'None' ), true ) ) {
			return $s;
		}
		return 'Lax';
	}

	/**
	 * Public base URL for links and REST calls: APP_URL, or derived from the current request.
	 */
	public static function requestAppBaseUrl(): string {
		$base = rtrim( self::string( 'APP_URL' ), '/' );
		if ( $base !== '' ) {
			return $base;
		}
		$scheme = self::requestIsHttps() ? 'https' : 'http';
		$host   = (string) ( $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080' );
		return $scheme . '://' . $host;
	}

	public static function envFlag( string $key ): bool {
		return self::truthy( self::rawString( $key ) );
	}

	private static function rawString( string $key ): string {
		$g = getenv( $key );
		if ( is_string( $g ) && $g !== '' ) {
			return $g;
		}
		if ( isset( $_ENV[ $key ] ) && is_string( $_ENV[ $key ] ) ) {
			return $_ENV[ $key ];
		}
		return '';
	}

	private static function truthy( string $v ): bool {
		return in_array( strtolower( trim( $v ) ), array( '1', 'true', 'yes', 'on' ), true );
	}
}
