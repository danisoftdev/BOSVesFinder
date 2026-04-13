<?php

declare(strict_types=1);

namespace Bvf\Web;

final class Templates {
/** @param array<string,mixed> $vars */
	public static function render( string $name, array $vars ): string {
		$path = dirname( __DIR__, 2 ) . '/templates/' . $name;
		if ( ! is_readable( $path ) ) {
			return '<p>Missing template: ' . htmlspecialchars( $name, ENT_QUOTES, 'UTF-8' ) . '</p>';
		}
		extract( $vars, EXTR_SKIP );
		ob_start();
		require $path;
		return (string) ob_get_clean();
	}
}
