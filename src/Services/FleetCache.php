<?php

declare(strict_types=1);

namespace Bvf\Services;

final class FleetCache {
	private const TTL_SEC = 12;

	private static function path(): string {
		$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bvf_cache';
		if ( ! is_dir( $dir ) ) {
			@mkdir( $dir, 0700, true );
		}
		return $dir . DIRECTORY_SEPARATOR . 'fleet_live_v1.json';
	}

/** @return array<int,array<string,mixed>>|null */
	public static function get(): ?array {
		$p = self::path();
		if ( ! is_readable( $p ) ) {
			return null;
		}
		$raw = @file_get_contents( $p );
		if ( $raw === false ) {
			return null;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || ! isset( $data['t'], $data['payload'] ) ) {
			return null;
		}
		if ( time() - (int) $data['t'] > self::TTL_SEC ) {
			return null;
		}
		return is_array( $data['payload'] ) ? $data['payload'] : null;
	}

	/** @param array<int,array<string,mixed>> $payload */
	public static function set( array $payload ): void {
		$p = self::path();
		file_put_contents(
			$p,
			json_encode( array( 't' => time(), 'payload' => $payload ), JSON_UNESCAPED_UNICODE ),
			LOCK_EX
		);
	}

	public static function bust(): void {
		$p = self::path();
		if ( is_file( $p ) ) {
			@unlink( $p );
		}
	}
}
