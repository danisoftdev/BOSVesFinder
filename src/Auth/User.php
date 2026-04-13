<?php

declare(strict_types=1);

namespace Bvf\Auth;

final class User {
	public const ROLE_SYSTEM_ADMIN    = 'bvf_system_administrator';
	public const ROLE_OPS_MANAGER     = 'bvf_operations_manager';
	public const ROLE_CAPTAIN         = 'bvf_captain';
	public const ROLE_VIEWER          = 'bvf_viewer';

	public const CAP_VIEW_FLEET = 'bvf_view_fleet';
	public const CAP_SUBMIT_TRACKING  = 'bvf_submit_tracking';
	public const CAP_MANAGE_FLEET     = 'bvf_manage_fleet';
	public const CAP_MANAGE_CREW      = 'bvf_manage_crew';
	public const CAP_VIEW_CREW        = 'bvf_view_crew';
	public const CAP_MANAGE_SETTINGS  = 'bvf_manage_settings';

	public function __construct(
		public int $id,
		public string $email,
		public string $displayName,
		public string $role
	) {
	}

	/** @return array<string,string> slug => label */
	public static function roleChoices(): array {
		return array(
			self::ROLE_VIEWER           => 'Viewer',
			self::ROLE_CAPTAIN          => 'Captain',
			self::ROLE_OPS_MANAGER      => 'Operations manager',
			self::ROLE_SYSTEM_ADMIN     => 'System administrator',
		);
	}

	public static function assertValidRole( string $role ): void {
		if ( ! isset( self::roleChoices()[ $role ] ) ) {
			throw new \InvalidArgumentException( 'Invalid role.' );
		}
	}

	public function can( string $cap ): bool {
		$map = array(
			self::ROLE_VIEWER => array( self::CAP_VIEW_FLEET, self::CAP_VIEW_CREW ),
			self::ROLE_CAPTAIN         => array( self::CAP_VIEW_FLEET, self::CAP_SUBMIT_TRACKING, self::CAP_VIEW_CREW, self::CAP_MANAGE_CREW ),
			self::ROLE_OPS_MANAGER     => array( self::CAP_VIEW_FLEET, self::CAP_SUBMIT_TRACKING, self::CAP_MANAGE_FLEET, self::CAP_VIEW_CREW, self::CAP_MANAGE_CREW ),
			self::ROLE_SYSTEM_ADMIN    => array( self::CAP_VIEW_FLEET, self::CAP_SUBMIT_TRACKING, self::CAP_MANAGE_FLEET, self::CAP_VIEW_CREW, self::CAP_MANAGE_CREW, self::CAP_MANAGE_SETTINGS ),
		);
		$caps = $map[ $this->role ] ?? array();
		return in_array( $cap, $caps, true );
	}
}
