<?php

declare(strict_types=1);

namespace Bvf;

use PDO;

final class Db {
	private static ?PDO $pdo = null;

	public static function pdo(): PDO {
		if ( self::$pdo instanceof PDO ) {
			return self::$pdo;
		}
		$host = EnvConfig::string( 'DB_HOST', '127.0.0.1' );
		$name = EnvConfig::string( 'DB_NAME', 'vesfinder' );
		$user = EnvConfig::string( 'DB_USER', 'root' );
		$pass = EnvConfig::string( 'DB_PASSWORD', '' );
		$dsn  = "mysql:host={$host};dbname={$name};charset=utf8mb4";
		self::$pdo = new PDO( $dsn, $user, $pass, array(
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			// Required for LIMIT/OFFSET placeholders on MySQL/MariaDB (otherwise bound as quoted strings).
			PDO::ATTR_EMULATE_PREPARES   => false,
		) );
		return self::$pdo;
	}
}
