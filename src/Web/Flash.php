<?php

declare(strict_types=1);

namespace Bvf\Web;

final class Flash {
	public static function set( string $message, string $type = 'info' ): void {
		$_SESSION['bvf_flash'] = array( 'message' => $message, 'type' => $type );
	}

	/** @return array{message:string,type:string}|null */
	public static function pull(): ?array {
		if ( empty( $_SESSION['bvf_flash'] ) || ! is_array( $_SESSION['bvf_flash'] ) ) {
			return null;
		}
		$f = $_SESSION['bvf_flash'];
		unset( $_SESSION['bvf_flash'] );
		return array(
			'message' => (string) ( $f['message'] ?? '' ),
			'type'    => (string) ( $f['type'] ?? 'info' ),
		);
	}
}
