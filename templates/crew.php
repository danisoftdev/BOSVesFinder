<?php
/** @var list<array<string,mixed>> $crewRows */
/** @var list<array<string,mixed>> $vlist */
/** @var list<array{id:int,email:string,display_name:string}> $appUsers */
/** @var bool $canManageCrew */

use Bvf\Auth\User;

$csrf       = \Bvf\Web\Csrf::token();
$roleLabels = User::roleChoices();
?>
<div class="bvf-panel">
	<h1>Crew registry</h1>
	<p class="bvf-muted">Captains register crew here; operations and administrators can review who added each person. Assign people to trips from the trip page.</p>
	<?php if ( $canManageCrew ) : ?>
		<p><button type="button" class="bvf-btn" id="bvf-crew-open-add">Add crew member</button></p>
	<?php endif; ?>
</div>

<div class="bvf-panel bvf-table-wrap">
	<table class="bvf-table">
		<thead>
			<tr>
				<th>Name</th>
				<th>Role</th>
				<th>Home vessel</th>
				<th>Linked app user</th>
				<th>Added by</th>
				<?php if ( $canManageCrew ) : ?><th></th><?php endif; ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $crewRows as $c ) : ?>
				<?php
				$cid = (int) $c['id'];
				$vesselId    = isset( $c['vessel_id'] ) && $c['vessel_id'] !== null ? (int) $c['vessel_id'] : '';
				$appUid      = isset( $c['app_user_id'] ) && $c['app_user_id'] !== null ? (int) $c['app_user_id'] : '';
				$byName      = trim( (string) ( $c['created_by_display_name'] ?? '' ) );
				$byEmail     = trim( (string) ( $c['created_by_email'] ?? '' ) );
				$byRole      = (string) ( $c['created_by_role'] ?? '' );
				$byRoleLabel = $byRole !== '' ? ( $roleLabels[ $byRole ] ?? $byRole ) : '';
				$linkName    = trim( (string) ( $c['linked_user_display_name'] ?? '' ) );
				$linkEmail   = trim( (string) ( $c['linked_user_email'] ?? '' ) );
				?>
				<tr>
					<td><?php echo htmlspecialchars( (string) $c['display_name'], ENT_QUOTES, 'UTF-8' ); ?></td>
					<td><code><?php echo htmlspecialchars( (string) $c['role_slug'], ENT_QUOTES, 'UTF-8' ); ?></code></td>
					<td><?php echo $c['vessel_name'] ? htmlspecialchars( (string) $c['vessel_name'], ENT_QUOTES, 'UTF-8' ) : '—'; ?></td>
					<td>
						<?php if ( $linkName !== '' || $linkEmail !== '' ) : ?>
							<?php echo htmlspecialchars( $linkName !== '' ? $linkName : $linkEmail, ENT_QUOTES, 'UTF-8' ); ?>
							<?php if ( $linkEmail !== '' && $linkName !== '' ) : ?>
								<span class="bvf-muted"> · <?php echo htmlspecialchars( $linkEmail, ENT_QUOTES, 'UTF-8' ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $byName !== '' || $byEmail !== '' ) : ?>
							<?php echo htmlspecialchars( $byName !== '' ? $byName : $byEmail, ENT_QUOTES, 'UTF-8' ); ?>
							<?php if ( $byRoleLabel !== '' ) : ?>
								<br><span class="bvf-muted"><?php echo htmlspecialchars( $byRoleLabel, ENT_QUOTES, 'UTF-8' ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							<span class="bvf-muted">—</span>
						<?php endif; ?>
					</td>
					<?php if ( $canManageCrew ) : ?>
						<td class="bvf-actions">
							<button
								type="button"
								class="bvf-btn bvf-btn--secondary bvf-crew-edit"
								data-id="<?php echo $cid; ?>"
								data-display-name="<?php echo htmlspecialchars( (string) $c['display_name'], ENT_QUOTES, 'UTF-8' ); ?>"
								data-role-slug="<?php echo htmlspecialchars( (string) $c['role_slug'], ENT_QUOTES, 'UTF-8' ); ?>"
								data-vessel-id="<?php echo $vesselId !== '' ? (string) $vesselId : ''; ?>"
								data-app-user-id="<?php echo $appUid !== '' ? (string) $appUid : ''; ?>"
							>Edit</button>
							<button
								type="button"
								class="bvf-btn bvf-btn--secondary bvf-crew-delete"
								data-id="<?php echo $cid; ?>"
								data-display-name="<?php echo htmlspecialchars( (string) $c['display_name'], ENT_QUOTES, 'UTF-8' ); ?>"
							>Delete</button>
						</td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<?php if ( $canManageCrew ) : ?>
<dialog class="bvf-dialog" id="bvf-crew-form-dialog" aria-labelledby="bvf-crew-dialog-title">
	<div class="bvf-dialog__inner">
		<h2 class="bvf-dialog__title" id="bvf-crew-dialog-title">Add crew member</h2>
		<form method="post" action="/app/crew" class="bvf-form" id="bvf-crew-save-form">
			<input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf, ENT_QUOTES, 'UTF-8' ); ?>">
			<label for="bvf-crew-dn">Display name *</label>
			<input type="text" id="bvf-crew-dn" name="display_name" required maxlength="191" autocomplete="name">
			<label for="bvf-crew-role">Role slug</label>
			<input type="text" id="bvf-crew-role" name="role_slug" value="crew" maxlength="64" placeholder="e.g. deckhand, engineer">
			<label for="bvf-crew-vessel">Home vessel (optional)</label>
			<select id="bvf-crew-vessel" name="vessel_id">
				<option value="">— None —</option>
				<?php foreach ( $vlist as $v ) : ?>
					<option value="<?php echo (int) $v['id']; ?>"><?php echo htmlspecialchars( (string) $v['name'], ENT_QUOTES, 'UTF-8' ); ?></option>
				<?php endforeach; ?>
			</select>
			<label for="bvf-crew-appuser">App user (optional)</label>
			<select id="bvf-crew-appuser" name="app_user_id">
				<option value="">— None —</option>
				<?php foreach ( $appUsers as $u ) : ?>
					<option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars( $u['display_name'] . ' (' . $u['email'] . ')', ENT_QUOTES, 'UTF-8' ); ?></option>
				<?php endforeach; ?>
			</select>
			<div class="bvf-dialog__actions">
				<button type="submit" class="bvf-btn">Save</button>
				<button type="button" class="bvf-btn bvf-btn--secondary bvf-dialog-cancel">Cancel</button>
			</div>
		</form>
	</div>
</dialog>

<dialog class="bvf-dialog" id="bvf-crew-delete-dialog" aria-labelledby="bvf-crew-delete-title">
	<div class="bvf-dialog__inner">
		<h2 class="bvf-dialog__title" id="bvf-crew-delete-title">Remove crew member</h2>
		<p class="bvf-muted" id="bvf-crew-delete-msg"></p>
		<form method="post" action="/app/crew/delete" class="bvf-form" id="bvf-crew-delete-form">
			<input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf, ENT_QUOTES, 'UTF-8' ); ?>">
			<input type="hidden" name="id" id="bvf-crew-delete-id" value="">
			<div class="bvf-dialog__actions">
				<button type="submit" class="bvf-btn">Remove from registry</button>
				<button type="button" class="bvf-btn bvf-btn--secondary bvf-dialog-cancel">Cancel</button>
			</div>
		</form>
	</div>
</dialog>

<script src="/assets/js/crew-registry.js" defer></script>
<?php endif; ?>
