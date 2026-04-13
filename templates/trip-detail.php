<?php
/** @var array<string,mixed> $trip */
/** @var list<array<string,mixed>> $fuelLogs */
/** @var list<array<string,mixed>> $tripCrew */
/** @var list<array<string,mixed>> $crewPickList */
/** @var bool $canManageFleet */
/** @var bool $canManageTripCrew */
/** @var bool $canPostFuel */
/** @var bool $canToggleOnboard */
/** @var bool $isTripCaptain */
$csrf = \Bvf\Web\Csrf::token();
$tripId = (int) $trip['id'];
?>
<div class="bvf-panel">
	<p class="bvf-muted"><a href="/app/trips">← Trips</a></p>
	<h1>Trip #<?php echo $tripId; ?></h1>
	<p>
		<strong><?php echo htmlspecialchars( (string) ( $trip['vessel_name'] ?? '' ), ENT_QUOTES, 'UTF-8' ); ?></strong>
		— status <code><?php echo htmlspecialchars( (string) $trip['status'], ENT_QUOTES, 'UTF-8' ); ?></code>
		— captain user <code><?php echo (int) $trip['captain_user_id']; ?></code>
	</p>
	<?php if ( $trip['origin_label'] ?? '' ) : ?>
		<p>Origin: <?php echo htmlspecialchars( (string) $trip['origin_label'], ENT_QUOTES, 'UTF-8' ); ?></p>
	<?php endif; ?>
	<?php if ( $trip['destination_label'] ?? '' ) : ?>
		<p>Destination: <?php echo htmlspecialchars( (string) $trip['destination_label'], ENT_QUOTES, 'UTF-8' ); ?></p>
	<?php endif; ?>
	<p>
		<a href="/app/trips/<?php echo $tripId; ?>/fuel-logs.csv">Download fuel log CSV</a>
		<?php if ( $canManageFleet || $isTripCaptain ) : ?>
			&nbsp;·&nbsp;
			<a href="/app/trips/<?php echo $tripId; ?>/job-certificate">Job request &amp; certificate</a>
			&nbsp;·&nbsp;
			<a href="/app/trips/<?php echo $tripId; ?>/job-certificate/print" target="_blank" rel="noopener">Print certificate</a>
		<?php endif; ?>
	</p>
</div>

<div class="bvf-panel bvf-table-wrap">
	<h2>Fuel log</h2>
	<?php if ( $canPostFuel ) : ?>
		<p class="bvf-muted">Both loaded and consumed litres are required; use <strong>0</strong> if one does not apply.</p>
		<p><button type="button" class="bvf-btn" data-bvf-open="bvf-trip-fuel-dialog">Add fuel entry</button></p>
	<?php endif; ?>
	<table class="bvf-table">
		<thead>
			<tr><th>ID</th><th>User</th><th>Loaded L</th><th>Consumed L</th><th>Notes</th><th>When</th></tr>
		</thead>
		<tbody>
			<?php foreach ( $fuelLogs as $f ) : ?>
				<tr>
					<td><?php echo (int) $f['id']; ?></td>
					<td><?php echo (int) $f['user_id']; ?></td>
					<td><?php echo htmlspecialchars( (string) ( $f['fuel_loaded_litres'] ?? '—' ), ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo htmlspecialchars( (string) ( $f['fuel_consumed_litres'] ?? '—' ), ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo htmlspecialchars( (string) ( $f['notes'] ?? '' ), ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo htmlspecialchars( (string) ( $f['created_at'] ?? '' ), ENT_QUOTES, 'UTF-8' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<?php if ( $canPostFuel ) : ?>
<dialog class="bvf-dialog" id="bvf-trip-fuel-dialog" aria-labelledby="bvf-trip-fuel-title">
	<div class="bvf-dialog__inner">
		<h2 class="bvf-dialog__title" id="bvf-trip-fuel-title">Add fuel entry</h2>
		<form method="post" action="/app/trips/<?php echo $tripId; ?>/fuel" class="bvf-form">
			<input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf, ENT_QUOTES, 'UTF-8' ); ?>">
			<label for="bvf-trip-fuel-loaded">Loaded (L) *</label>
			<input type="number" step="0.001" min="0" id="bvf-trip-fuel-loaded" name="fuel_loaded_litres" required>
			<label for="bvf-trip-fuel-consumed">Consumed (L) *</label>
			<input type="number" step="0.001" min="0" id="bvf-trip-fuel-consumed" name="fuel_consumed_litres" required>
			<label for="bvf-trip-fuel-notes">Notes</label>
			<input type="text" id="bvf-trip-fuel-notes" name="notes" maxlength="500">
			<div class="bvf-dialog__actions">
				<button type="submit" class="bvf-btn">Add entry</button>
				<button type="button" class="bvf-btn bvf-btn--secondary" data-bvf-close="bvf-trip-fuel-dialog">Cancel</button>
			</div>
		</form>
	</div>
</dialog>
<?php endif; ?>

<div class="bvf-panel bvf-table-wrap">
	<h2>Crew on this trip</h2>
	<?php if ( $canManageTripCrew ) : ?>
		<p><button type="button" class="bvf-btn" data-bvf-open="bvf-trip-crew-add-dialog">Assign crew from registry</button></p>
	<?php endif; ?>
	<table class="bvf-table">
		<thead>
			<tr><th>Name</th><th>Role</th><th>Onboard</th><th></th></tr>
		</thead>
		<tbody>
			<?php foreach ( $tripCrew as $tc ) : ?>
				<?php
				$cname = (string) $tc['display_name'];
				$cname_esc = htmlspecialchars( $cname, ENT_QUOTES, 'UTF-8' );
				?>
				<tr>
					<td><?php echo $cname_esc; ?></td>
					<td><?php echo htmlspecialchars( (string) $tc['role_slug'], ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo ! empty( $tc['onboard_confirmed'] ) ? 'Yes' : 'No'; ?></td>
					<td class="bvf-actions">
						<?php if ( $canToggleOnboard ) : ?>
							<button
								type="button"
								class="bvf-btn bvf-btn--secondary"
								data-bvf-crew-onboard
								data-crew-id="<?php echo (int) $tc['crew_id']; ?>"
								data-next-onboard="<?php echo ! empty( $tc['onboard_confirmed'] ) ? '0' : '1'; ?>"
								data-crew-label="<?php echo $cname_esc; ?>"
							><?php echo ! empty( $tc['onboard_confirmed'] ) ? 'Mark off' : 'Mark onboard'; ?></button>
						<?php endif; ?>
						<?php if ( $canManageTripCrew ) : ?>
							<button
								type="button"
								class="bvf-btn bvf-btn--secondary"
								data-bvf-crew-remove
								data-crew-id="<?php echo (int) $tc['crew_id']; ?>"
								data-crew-label="<?php echo $cname_esc; ?>"
							>Remove</button>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<?php if ( $canManageTripCrew ) : ?>
<dialog class="bvf-dialog" id="bvf-trip-crew-add-dialog" aria-labelledby="bvf-trip-crew-add-title">
	<div class="bvf-dialog__inner">
		<h2 class="bvf-dialog__title" id="bvf-trip-crew-add-title">Assign crew</h2>
		<form method="post" action="/app/trips/<?php echo $tripId; ?>/crew/add" class="bvf-form">
			<input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf, ENT_QUOTES, 'UTF-8' ); ?>">
			<label for="bvf-trip-crew-id">Crew member *</label>
			<select id="bvf-trip-crew-id" name="crew_id" required>
				<option value="">— Select —</option>
				<?php foreach ( $crewPickList as $c ) : ?>
					<option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars( (string) $c['display_name'], ENT_QUOTES, 'UTF-8' ); ?> (<?php echo htmlspecialchars( (string) $c['role_slug'], ENT_QUOTES, 'UTF-8' ); ?>)</option>
				<?php endforeach; ?>
			</select>
			<div class="bvf-dialog__actions">
				<button type="submit" class="bvf-btn">Assign</button>
				<button type="button" class="bvf-btn bvf-btn--secondary" data-bvf-close="bvf-trip-crew-add-dialog">Cancel</button>
			</div>
		</form>
	</div>
</dialog>
<?php endif; ?>

<?php if ( $canToggleOnboard ) : ?>
<dialog class="bvf-dialog" id="bvf-trip-crew-onboard-dialog" aria-labelledby="bvf-trip-crew-onboard-title">
	<div class="bvf-dialog__inner">
		<h2 class="bvf-dialog__title" id="bvf-trip-crew-onboard-title">Onboard status</h2>
		<p class="bvf-muted" id="bvf-trip-crew-onboard-msg"></p>
		<form method="post" action="/app/trips/<?php echo $tripId; ?>/crew/onboard" class="bvf-form" id="bvf-trip-crew-onboard-form">
			<input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf, ENT_QUOTES, 'UTF-8' ); ?>">
			<input type="hidden" name="crew_id" id="bvf-trip-crew-onboard-crew-id" value="">
			<input type="hidden" name="onboard_confirmed" id="bvf-trip-crew-onboard-flag" value="">
			<div class="bvf-dialog__actions">
				<button type="submit" class="bvf-btn">Confirm</button>
				<button type="button" class="bvf-btn bvf-btn--secondary" data-bvf-close="bvf-trip-crew-onboard-dialog">Cancel</button>
			</div>
		</form>
	</div>
</dialog>
<?php endif; ?>

<?php if ( $canManageTripCrew ) : ?>
<dialog class="bvf-dialog" id="bvf-trip-crew-remove-dialog" aria-labelledby="bvf-trip-crew-remove-title">
	<div class="bvf-dialog__inner">
		<h2 class="bvf-dialog__title" id="bvf-trip-crew-remove-title">Remove crew</h2>
		<p class="bvf-muted" id="bvf-trip-crew-remove-msg"></p>
		<form method="post" action="/app/trips/<?php echo $tripId; ?>/crew/remove" class="bvf-form" id="bvf-trip-crew-remove-form">
			<input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf, ENT_QUOTES, 'UTF-8' ); ?>">
			<input type="hidden" name="crew_id" id="bvf-trip-crew-remove-crew-id" value="">
			<div class="bvf-dialog__actions">
				<button type="submit" class="bvf-btn">Remove from trip</button>
				<button type="button" class="bvf-btn bvf-btn--secondary" data-bvf-close="bvf-trip-crew-remove-dialog">Cancel</button>
			</div>
		</form>
	</div>
</dialog>
<?php endif; ?>

<script src="/assets/js/bvf-app-modals.js" defer></script>
