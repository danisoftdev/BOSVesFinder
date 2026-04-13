<?php
/** @var \Bvf\Auth\User[] $users */
/** @var array<string,string> $roles */
$csrf = \Bvf\Web\Csrf::token();
?>
<div class="bvf-panel">
	<h1>Users</h1>
	<p class="bvf-muted">Create accounts for captains, viewers, and operations staff.</p>
	<p><button type="button" class="bvf-btn" data-bvf-open="bvf-user-dialog">Add user</button></p>
</div>
<div class="bvf-panel bvf-table-wrap">
	<h2>All users</h2>
	<table class="bvf-table">
		<thead>
			<tr><th>ID</th><th>Email</th><th>Name</th><th>Role</th></tr>
		</thead>
		<tbody>
			<?php foreach ( $users as $u ) : ?>
				<tr>
					<td><?php echo $u->id; ?></td>
					<td><?php echo htmlspecialchars( $u->email, ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo htmlspecialchars( $u->displayName, ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><?php echo htmlspecialchars( $u->role, ENT_QUOTES, 'UTF-8' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<dialog class="bvf-dialog" id="bvf-user-dialog" aria-labelledby="bvf-user-dialog-title">
	<div class="bvf-dialog__inner">
		<h2 class="bvf-dialog__title" id="bvf-user-dialog-title">Add user</h2>
		<form method="post" class="bvf-form">
			<input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf, ENT_QUOTES, 'UTF-8' ); ?>">
			<label for="bvf-user-email">Email *</label>
			<input type="email" id="bvf-user-email" name="email" required maxlength="191" autocomplete="off">
			<label for="bvf-user-password">Password *</label>
			<input type="password" id="bvf-user-password" name="password" required autocomplete="new-password">
			<label for="bvf-user-display">Display name</label>
			<input type="text" id="bvf-user-display" name="display_name" maxlength="191">
			<label for="bvf-user-role">Role</label>
			<select id="bvf-user-role" name="role">
				<?php foreach ( $roles as $slug => $label ) : ?>
					<option value="<?php echo htmlspecialchars( $slug, ENT_QUOTES, 'UTF-8' ); ?>"><?php echo htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' ); ?></option>
				<?php endforeach; ?>
			</select>
			<div class="bvf-dialog__actions">
				<button type="submit" class="bvf-btn">Add user</button>
				<button type="button" class="bvf-btn bvf-btn--secondary" data-bvf-close="bvf-user-dialog">Cancel</button>
			</div>
		</form>
	</div>
</dialog>
<script src="/assets/js/bvf-app-modals.js" defer></script>
