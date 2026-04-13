<?php

declare(strict_types=1);

namespace Bvf\Services;

final class RateLimiter {
	/** @return array{ok:bool,message?:string,status?:int} */
	public static function allow( string $bucket, int $userId, int $max, int $windowSec ): array {
		$userId = max( 0, $userId );
		if ( $userId <= 0 || $max < 1 || $windowSec < 1 ) {
			return array( 'ok' => true );
		}
		$key = preg_replace( '/[^a-z0-9_]/', '', strtolower( $bucket ) ) . '_' . $userId;
		$dir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bvf_rl';
		if ( ! is_dir( $dir ) ) {
			@mkdir( $dir, 0700, true );
		}
		$file = $dir . DIRECTORY_SEPARATOR . $key . '.json';
		$now  = time();
		$row  = array( 'count' => 0, 'window_end' => $now + $windowSec );
		if ( is_readable( $file ) ) {
			$j = json_decode( (string) file_get_contents( $file ), true );
			if ( is_array( $j ) && isset( $j['window_end'], $j['count'] ) ) {
				$row = $j;
			}
		}
		if ( $now >= (int) $row['window_end'] ) {
			$row = array( 'count' => 0, 'window_end' => $now + $windowSec );
		}
		if ( (int) $row['count'] >= $max ) {
			return array(
				'ok'      => false,
				'message' => 'Too many requests. Please wait a moment and try again.',
				'status'  => 429,
			);
		}
		$row['count'] = (int) $row['count'] + 1;
		$ttl          = max( 1, (int) $row['window_end'] - $now );
		file_put_contents( $file, json_encode( $row ), LOCK_EX );
		touch( $file, $now + $ttl );
		return array( 'ok' => true );
	}
}
