<?php

declare(strict_types=1);

namespace Bvf\Services;

use Bvf\Db;

final class SettingsRepository {
	public function get( string $key, string $default = '' ): string {
		$st = Db::pdo()->prepare( 'SELECT value FROM bvf_settings WHERE `key` = ? LIMIT 1' );
		$st->execute( array( $key ) );
		$row = $st->fetchColumn();
		return is_string( $row ) ? $row : $default;
	}

	public function set( string $key, string $value ): void {
		$st = Db::pdo()->prepare(
			'INSERT INTO bvf_settings (`key`, `value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
		);
		$st->execute( array( $key, $value ) );
	}
}
