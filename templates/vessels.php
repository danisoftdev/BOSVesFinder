<?php
/** @var list<array<string,mixed>> $rows */
/** @var list<array{id:int,email:string,display_name:string}> $captains */
$csrf = \Bvf\Web\Csrf::token();
?>
<div class="bvf-panel">
	<h1>Vessels</h1>
	<p class="bvf-muted">Create vessels and optionally assign a default captain.</p>
	<p><button type="button" class="bvf-btn" data-bvf-open="bvf-vessel-dialog">Add vessel</button></p>
</div>
<div class="bvf-panel bvf-table-wrap">
	<h2>All vessels</h2>
	<table class="bvf-table">
		<thead>
			<tr><th>ID</th><th>Name</th><th>External</th><th>Status</th><th>Captain user</th></tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $v ) : ?>
				<tr>
					<td><?php echo (int) $v['id']; ?></td>
					<td><?php echo htmlspecialchars( (string) $v['name'], ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo htmlspecialchars( (string) $v['external_id'], ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo htmlspecialchars( (string) $v['status'], ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo $v['assigned_captain_user_id'] ? (int) $v['assigned_captain_user_id'] : '—'; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<dialog class="bvf-dialog" id="bvf-vessel-dialog" aria-labelledby="bvf-vessel-dialog-title">
	<div class="bvf-dialog__inner">
		<h2 class="bvf-dialog__title" id="bvf-vessel-dialog-title">Add vessel</h2>
		<form method="post" class="bvf-form">
			<input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf, ENT_QUOTES, 'UTF-8' ); ?>">
			<label for="bvf-vessel-name">Name *</label>
			<input type="text" id="bvf-vessel-name" name="name" required maxlength="191">
			<label for="bvf-vessel-external">External ID</label>
			<input type="text" id="bvf-vessel-external" name="external_id" maxlength="64">
			<label for="bvf-vessel-status">Status</label>
			<select id="bvf-vessel-status" name="status">
				<option value="active">active</option>
				<option value="inactive">inactive</option>
				<option value="maintenance">maintenance</option>
			</select>
			<label for="bvf-vessel-captain">Default captain (optional)</label>
			<select id="bvf-vessel-captain" name="assigned_captain_user_id">
				<option value="">— None —</option>
				<?php foreach ( $captains as $c ) : ?>
					<option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars( $c['display_name'] . ' (' . $c['email'] . ')', ENT_QUOTES, 'UTF-8' ); ?></option>
				<?php endforeach; ?>
			</select>
			<div class="bvf-dialog__actions">
				<button type="submit" class="bvf-btn">Add vessel</button>
				<button type="button" class="bvf-btn bvf-btn--secondary" data-bvf-close="bvf-vessel-dialog">Cancel</button>
			</div>
		</form>
	</div>
</dialog>
<script src="/assets/js/bvf-app-modals.js" defer></script>
