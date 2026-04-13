<?php
/** @var list<array<string,mixed>> $trips */
/** @var list<array<string,mixed>> $vlist */
/** @var list<array{id:int,email:string,display_name:string}> $captains */
$csrf = \Bvf\Web\Csrf::token();
?>
<div class="bvf-panel">
	<h1>Trips</h1>
	<p class="bvf-muted">Start a trip for a vessel and captain. Active trips appear on the fleet map.</p>
	<p><button type="button" class="bvf-btn" data-bvf-open="bvf-trip-create-dialog">Create trip</button></p>
</div>
<div class="bvf-panel bvf-table-wrap">
	<h2>Recent trips</h2>
	<table class="bvf-table">
		<thead>
			<tr><th>ID</th><th>Vessel</th><th>Captain</th><th>Status</th><th>Started</th><th></th><th></th><th></th></tr>
		</thead>
		<tbody>
			<?php foreach ( $trips as $t ) : ?>
				<tr>
					<td><?php echo (int) $t['id']; ?></td>
					<td><?php echo htmlspecialchars( (string) ( $t['vessel_name'] ?? '' ), ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo (int) $t['captain_user_id']; ?></td>
					<td><?php echo htmlspecialchars( (string) $t['status'], ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo htmlspecialchars( (string) ( $t['started_at'] ?? '—' ), ENT_QUOTES, 'UTF-8' ); ?></td>
					<td>
						<a href="/app/trips/<?php echo (int) $t['id']; ?>">Details</a>
					</td>
					<td>
						<a href="/app/trips/<?php echo (int) $t['id']; ?>/job-certificate">Certificate</a>
					</td>
					<td class="bvf-actions">
						<?php if ( ( $t['status'] ?? '' ) === 'active' ) : ?>
							<button
								type="button"
								class="bvf-btn bvf-btn--secondary"
								data-bvf-trip-complete="<?php echo (int) $t['id']; ?>"
							>Complete</button>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<dialog class="bvf-dialog" id="bvf-trip-create-dialog" aria-labelledby="bvf-trip-create-title">
	<div class="bvf-dialog__inner">
		<h2 class="bvf-dialog__title" id="bvf-trip-create-title">Create trip</h2>
		<form method="post" class="bvf-form">
			<input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf, ENT_QUOTES, 'UTF-8' ); ?>">
			<label for="bvf-trip-vessel">Vessel *</label>
			<select id="bvf-trip-vessel" name="vessel_id" required>
				<option value="">— Select —</option>
				<?php foreach ( $vlist as $v ) : ?>
					<option value="<?php echo (int) $v['id']; ?>"><?php echo htmlspecialchars( (string) $v['name'], ENT_QUOTES, 'UTF-8' ); ?></option>
				<?php endforeach; ?>
			</select>
			<label for="bvf-trip-captain">Captain *</label>
			<select id="bvf-trip-captain" name="captain_user_id" required>
				<option value="">— Select —</option>
				<?php foreach ( $captains as $c ) : ?>
					<option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars( $c['display_name'] . ' (' . $c['email'] . ')', ENT_QUOTES, 'UTF-8' ); ?></option>
				<?php endforeach; ?>
			</select>
			<label for="bvf-trip-status">Status</label>
			<select id="bvf-trip-status" name="status">
				<option value="active">active</option>
				<option value="pending">pending</option>
			</select>
			<label for="bvf-trip-origin">Origin</label>
			<input type="text" id="bvf-trip-origin" name="origin_label" maxlength="255">
			<label for="bvf-trip-dest">Destination</label>
			<input type="text" id="bvf-trip-dest" name="destination_label" maxlength="255">
			<div class="bvf-dialog__actions">
				<button type="submit" class="bvf-btn">Create trip</button>
				<button type="button" class="bvf-btn bvf-btn--secondary" data-bvf-close="bvf-trip-create-dialog">Cancel</button>
			</div>
		</form>
	</div>
</dialog>

<dialog class="bvf-dialog" id="bvf-trip-complete-dialog" aria-labelledby="bvf-trip-complete-title">
	<div class="bvf-dialog__inner">
		<h2 class="bvf-dialog__title" id="bvf-trip-complete-title">Complete trip</h2>
		<p class="bvf-muted">Mark this trip as completed? The vessel will no longer show as active on the fleet map.</p>
		<form method="post" class="bvf-form" id="bvf-trip-complete-form" action="">
			<input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf, ENT_QUOTES, 'UTF-8' ); ?>">
			<div class="bvf-dialog__actions">
				<button type="submit" class="bvf-btn">Complete trip</button>
				<button type="button" class="bvf-btn bvf-btn--secondary" data-bvf-close="bvf-trip-complete-dialog">Cancel</button>
			</div>
		</form>
	</div>
</dialog>
<script src="/assets/js/bvf-app-modals.js" defer></script>
