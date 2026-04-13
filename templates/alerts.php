<?php
/** @var list<array<string,mixed>> $alerts */
?>
<div class="bvf-panel bvf-table-wrap">
	<h1>Alerts</h1>
	<p class="bvf-muted">Latest100 alerts (SOS, geofence, signal loss, battery, etc.).</p>
	<table class="bvf-table">
		<thead>
			<tr>
				<th>ID</th><th>Type</th><th>Severity</th><th>Trip</th><th>Vessel</th><th>When</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $alerts as $a ) : ?>
				<tr>
					<td><?php echo (int) $a['id']; ?></td>
					<td><?php echo htmlspecialchars( (string) $a['alert_type'], ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo htmlspecialchars( (string) $a['severity'], ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo $a['trip_id'] ? (int) $a['trip_id'] : '—'; ?></td>
					<td><?php echo htmlspecialchars( (string) ( $a['vessel_name'] ?? '#' . ( $a['vessel_id'] ?? '' ) ), ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo htmlspecialchars( (string) $a['created_at'], ENT_QUOTES, 'UTF-8' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
