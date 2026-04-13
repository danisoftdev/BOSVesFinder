<?php
/** @var list<array<string,mixed>> $zones */
$csrf = \Bvf\Web\Csrf::token();
?>
<div class="bvf-panel">
	<h1>Geofences</h1>
	<p class="bvf-muted">Circular zones: <strong>operational</strong> = must stay inside; <strong>restricted</strong> = must stay outside.</p>
	<p><button type="button" class="bvf-btn" data-bvf-open="bvf-geofence-dialog">Add geofence</button></p>
</div>
<div class="bvf-panel bvf-table-wrap">
	<h2>Active definitions</h2>
	<table class="bvf-table">
		<thead>
			<tr><th>ID</th><th>Name</th><th>Type</th><th>Lat</th><th>Lng</th><th>Radius m</th><th></th></tr>
		</thead>
		<tbody>
			<?php foreach ( $zones as $z ) : ?>
				<tr>
					<td><?php echo (int) $z['id']; ?></td>
					<td><?php echo htmlspecialchars( (string) $z['name'], ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo htmlspecialchars( (string) $z['zone_type'], ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo htmlspecialchars( (string) ( $z['center_lat'] ?? '' ), ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo htmlspecialchars( (string) ( $z['center_lng'] ?? '' ), ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo $z['radius_meters'] !== null ? (int) $z['radius_meters'] : '—'; ?></td>
					<td>
						<button
							type="button"
							class="bvf-btn bvf-btn--secondary"
							data-bvf-geofence-del="<?php echo (int) $z['id']; ?>"
							data-bvf-geofence-name="<?php echo htmlspecialchars( (string) $z['name'], ENT_QUOTES, 'UTF-8' ); ?>"
						>Delete</button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<dialog class="bvf-dialog" id="bvf-geofence-dialog" aria-labelledby="bvf-geofence-create-title">
	<div class="bvf-dialog__inner">
		<h2 class="bvf-dialog__title" id="bvf-geofence-create-title">Add geofence</h2>
		<form method="post" class="bvf-form">
			<input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf, ENT_QUOTES, 'UTF-8' ); ?>">
			<label for="bvf-gf-name">Name *</label>
			<input type="text" id="bvf-gf-name" name="name" required maxlength="191">
			<label for="bvf-gf-type">Zone type</label>
			<select id="bvf-gf-type" name="zone_type">
				<option value="operational">operational</option>
				<option value="restricted">restricted</option>
			</select>
			<label for="bvf-gf-lat">Center latitude</label>
			<input type="text" id="bvf-gf-lat" name="center_lat" placeholder="e.g. 4.1234567">
			<label for="bvf-gf-lng">Center longitude</label>
			<input type="text" id="bvf-gf-lng" name="center_lng" placeholder="e.g. 8.7654321">
			<label for="bvf-gf-radius">Radius (meters)</label>
			<input type="number" id="bvf-gf-radius" name="radius_meters" min="1" step="1" placeholder="5000">
			<div class="bvf-dialog__actions">
				<button type="submit" class="bvf-btn">Add geofence</button>
				<button type="button" class="bvf-btn bvf-btn--secondary" data-bvf-close="bvf-geofence-dialog">Cancel</button>
			</div>
		</form>
	</div>
</dialog>

<dialog class="bvf-dialog" id="bvf-geofence-delete-dialog" aria-labelledby="bvf-geofence-delete-title">
	<div class="bvf-dialog__inner">
		<h2 class="bvf-dialog__title" id="bvf-geofence-delete-title">Delete geofence</h2>
		<p class="bvf-muted" id="bvf-geofence-delete-msg"></p>
		<form method="post" action="/app/geofences/delete" class="bvf-form" id="bvf-geofence-delete-form">
			<input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf, ENT_QUOTES, 'UTF-8' ); ?>">
			<input type="hidden" name="id" id="bvf-geofence-delete-id" value="">
			<div class="bvf-dialog__actions">
				<button type="submit" class="bvf-btn">Delete</button>
				<button type="button" class="bvf-btn bvf-btn--secondary" data-bvf-close="bvf-geofence-delete-dialog">Cancel</button>
			</div>
		</form>
	</div>
</dialog>
<script src="/assets/js/bvf-app-modals.js" defer></script>
