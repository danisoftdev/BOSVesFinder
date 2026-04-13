<?php

declare(strict_types=1);

namespace Bvf\Auth;

use Bvf\Db;

final class UserRepository {
	public function findById( int $id ): ?User {
		$st = Db::pdo()->prepare( 'SELECT id, email, display_name, role FROM bvf_app_users WHERE id = ? LIMIT 1' );
		$st->execute( array( $id ) );
		$row = $st->fetch();
		if ( ! $row ) {
			return null;
		}
		return new User( (int) $row['id'], (string) $row['email'], (string) $row['display_name'], (string) $row['role'] );
	}

	public function verifyPassword( string $email, string $plain ): ?User {
		$st = Db::pdo()->prepare( 'SELECT id, email, display_name, role, password_hash FROM bvf_app_users WHERE email = ? LIMIT 1' );
		$st->execute( array( $email ) );
		$row = $st->fetch();
		if ( ! $row || ! password_verify( $plain, (string) $row['password_hash'] ) ) {
			return null;
		}
		return new User( (int) $row['id'], (string) $row['email'], (string) $row['display_name'], (string) $row['role'] );
	}

	/** @return User[] */
	public function listAllByEmail(): array {
		$st = Db::pdo()->query( 'SELECT id, email, display_name, role FROM bvf_app_users ORDER BY email ASC' );
		$out = array();
		while ( $row = $st->fetch() ) {
			$out[] = new User( (int) $row['id'], (string) $row['email'], (string) $row['display_name'], (string) $row['role'] );
		}
		return $out;
	}

	/**
	 * Emails for system administrators and operations managers (SOS routing).
	 *
	 * @return list<string>
	 */
	public function emailsForSosNotifications(): array {
		$roles = array( User::ROLE_SYSTEM_ADMIN, User::ROLE_OPS_MANAGER );
		$in    = implode( ',', array_fill( 0, count( $roles ), '?' ) );
		$st    = Db::pdo()->prepare( "SELECT email FROM bvf_app_users WHERE role IN ($in)" );
		$st->execute( $roles );
		$out = array();
		while ( $row = $st->fetch() ) {
			$e = filter_var( strtolower( trim( (string) ( $row['email'] ?? '' ) ) ), FILTER_VALIDATE_EMAIL );
			if ( is_string( $e ) ) {
				$out[] = $e;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** @return list<array{id:int,email:string,display_name:string}> */
	public function listCaptainAssignable(): array {
		$roles = array( User::ROLE_CAPTAIN, User::ROLE_OPS_MANAGER, User::ROLE_SYSTEM_ADMIN );
		$in    = implode( ',', array_fill( 0, count( $roles ), '?' ) );
		$st    = Db::pdo()->prepare(
			"SELECT id, email, display_name FROM bvf_app_users WHERE role IN ($in) ORDER BY display_name ASC, email ASC"
		);
		$st->execute( $roles );
		return $st->fetchAll() ?: array();
	}

	public function emailExists( string $email ): bool {
		$st = Db::pdo()->prepare( 'SELECT 1 FROM bvf_app_users WHERE email = ? LIMIT 1' );
		$st->execute( array( strtolower( trim( $email ) ) ) );
		return (bool) $st->fetchColumn();
	}

	public function createUser( string $email, string $plainPassword, string $displayName, string $role ): int {
		User::assertValidRole( $role );
		$email = strtolower( trim( $email ) );
		if ( $email === '' || $plainPassword === '' ) {
			throw new \InvalidArgumentException( 'Email and password required.' );
		}
		if ( $this->emailExists( $email ) ) {
			throw new \InvalidArgumentException( 'Email already registered.' );
		}
		$hash = password_hash( $plainPassword, PASSWORD_DEFAULT );
		$st   = Db::pdo()->prepare(
			'INSERT INTO bvf_app_users (email, password_hash, display_name, role) VALUES (?,?,?,?)'
		);
		$st->execute( array( $email, $hash, trim( $displayName ), $role ) );
		return (int) Db::pdo()->lastInsertId();
	}
}
