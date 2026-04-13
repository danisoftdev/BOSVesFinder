<?php
/** @var string $opsEmails */
$csrf = \Bvf\Web\Csrf::token();
?>
<div class="bvf-panel">
	<h1>Settings</h1>
	<p class="bvf-muted">Operations email list for SOS and other notifications.</p>
	<p><button type="button" class="bvf-btn" data-bvf-open="bvf-settings-dialog">Edit notification emails</button></p>
</div>

<dialog class="bvf-dialog" id="bvf-settings-dialog" aria-labelledby="bvf-settings-dialog-title">
	<div class="bvf-dialog__inner">
		<h2 class="bvf-dialog__title" id="bvf-settings-dialog-title">Operations notification emails</h2>
		<p class="bvf-muted">Comma- or space-separated addresses for SOS and other mail from the app. Falls back to <code>BVF_OPS_EMAILS</code> in <code>.env</code> if empty.</p>
		<form method="post" class="bvf-form">
			<input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf, ENT_QUOTES, 'UTF-8' ); ?>">
			<label for="bvf-settings-emails">Email list</label>
			<textarea id="bvf-settings-emails" name="ops_notification_emails" rows="6"><?php echo htmlspecialchars( $opsEmails, ENT_QUOTES, 'UTF-8' ); ?></textarea>
			<div class="bvf-dialog__actions">
				<button type="submit" class="bvf-btn">Save</button>
				<button type="button" class="bvf-btn bvf-btn--secondary" data-bvf-close="bvf-settings-dialog">Cancel</button>
			</div>
		</form>
	</div>
</dialog>
<script src="/assets/js/bvf-app-modals.js" defer></script>
