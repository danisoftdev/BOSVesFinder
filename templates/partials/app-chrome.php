<?php
/** @var \Bvf\Auth\User $user */
/** @var string $nav_active */
if ( ! isset( $nav_active ) ) {
	$nav_active = '';
}
?>
	<header class="bvf-header">
		<div class="bvf-header__brand">
			<a href="/app/fleet" class="bvf-header__brand-link" title="BOSVesFinder — Fleet home">
				<img src="/assets/img/vesfinder-logo.png" class="bvf-header__logo" alt="BOSVesFinder — vessel tracking and fleet intelligence" decoding="async" fetchpriority="high">
			</a>
		</div>
		<nav class="bvf-nav">
			<?php if ( $user->can( \Bvf\Auth\User::CAP_VIEW_FLEET ) ) : ?>
				<a href="/app/fleet" class="<?php echo $nav_active === 'fleet' ? 'is-active' : ''; ?>">Fleet map</a>
				<a href="/app/alerts" class="<?php echo $nav_active === 'alerts' ? 'is-active' : ''; ?>">Alerts</a>
			<?php endif; ?>
			<?php if ( $user->can( \Bvf\Auth\User::CAP_SUBMIT_TRACKING ) ) : ?>
				<a href="/app/captain" class="<?php echo ( $nav_active === 'captain' || $nav_active === 'trips_emergencies' ) ? 'is-active' : ''; ?>">Trips/Emergencies</a>
			<?php endif; ?>
			<?php if ( $user->can( \Bvf\Auth\User::CAP_MANAGE_FLEET ) ) : ?>
				<a href="/app/vessels" class="<?php echo $nav_active === 'vessels' ? 'is-active' : ''; ?>">Vessels</a>
				<a href="/app/trips" class="<?php echo $nav_active === 'trips' ? 'is-active' : ''; ?>">Trips</a>
				<a href="/app/geofences" class="<?php echo $nav_active === 'geofences' ? 'is-active' : ''; ?>">Geofences</a>
			<?php endif; ?>
			<?php if ( $user->can( \Bvf\Auth\User::CAP_VIEW_CREW ) ) : ?>
				<a href="/app/crew" class="<?php echo $nav_active === 'crew' ? 'is-active' : ''; ?>">Crew</a>
			<?php endif; ?>
			<?php if ( $user->can( \Bvf\Auth\User::CAP_MANAGE_SETTINGS ) ) : ?>
				<a href="/app/users" class="<?php echo $nav_active === 'users' ? 'is-active' : ''; ?>">Users</a>
				<a href="/app/settings" class="<?php echo $nav_active === 'settings' ? 'is-active' : ''; ?>">Settings</a>
			<?php endif; ?>
		</nav>
		<div class="bvf-header__user">
			<?php echo htmlspecialchars( $user->displayName, ENT_QUOTES, 'UTF-8' ); ?>
			<a href="/logout" class="bvf-logout">Logout</a>
		</div>
	</header>
