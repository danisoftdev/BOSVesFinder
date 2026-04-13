<?php

declare(strict_types=1);

namespace Bvf\Web;

final class Csrf {
	public static function token(): string {
		if ( empty( $_SESSION['bvf_csrf'] ) ) {
			$_SESSION['bvf_csrf'] = bin2hex( random_bytes( 32 ) );
		}
		return (string) $_SESSION['bvf_csrf'];
	}

	public static function verify( ?string $posted ): bool {
		return is_string( $posted )
			&& $posted !== ''
			&& ! empty( $_SESSION['bvf_csrf'] )
			&& hash_equals( (string) $_SESSION['bvf_csrf'], $posted );
	}
}
