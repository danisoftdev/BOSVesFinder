<?php
/** @var array<string,mixed> $certificate */
/** @var string $csrf */
/** @var \Bvf\Auth\User $user */
$tripId = (int) ( $certificate['trip_id'] ?? 0 );
$esc = static function ( string $s ): string {
	return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' );
};
?>
<div class="bvf-panel">
	<p class="bvf-muted">
		<a href="/app/trips/<?php echo $tripId; ?>">← Trip #<?php echo $tripId; ?></a>
		&nbsp;·&nbsp;
		<a href="/app/trips/<?php echo $tripId; ?>/job-certificate/print" target="_blank" rel="noopener">Printable view</a>
	</p>
	<h1>Services boat job request &amp; certificate</h1>
	<p class="bvf-muted">Complete all sections. Use <strong>Printable view</strong> for a clean sheet to file or sign in the office.</p>
	<?php if ( ! empty( $certificate['form_reference'] ) ) : ?>
		<p><strong>Form reference:</strong> <?php echo $esc( (string) $certificate['form_reference'] ); ?></p>
	<?php endif; ?>
</div>

<div class="bvf-panel bvf-job-cert-form-wrap">
	<form method="post" action="/app/trips/<?php echo $tripId; ?>/job-certificate" class="bvf-form bvf-job-cert-form">
		<input type="hidden" name="csrf" value="<?php echo $esc( $csrf ); ?>">

		<h2 class="bvf-job-cert-h2">Client &amp; vessel</h2>
		<label for="bvf-jc-client">Client&rsquo;s name</label>
		<input type="text" id="bvf-jc-client" name="client_name" maxlength="255" value="<?php echo $esc( (string) ( $certificate['client_name'] ?? '' ) ); ?>">

		<label for="bvf-jc-addr">Address &amp; phone no.</label>
		<textarea id="bvf-jc-addr" name="client_address_phone" rows="3" maxlength="2000"><?php echo $esc( (string) ( $certificate['client_address_phone'] ?? '' ) ); ?></textarea>

		<label for="bvf-jc-vessel">Vessel&rsquo;s name</label>
		<input type="text" id="bvf-jc-vessel" name="client_vessel_name" maxlength="255" value="<?php echo $esc( (string) ( $certificate['client_vessel_name'] ?? '' ) ); ?>">

		<label for="bvf-jc-pos">Position at anchorage</label>
		<input type="text" id="bvf-jc-pos" name="position_anchorage" maxlength="255" value="<?php echo $esc( (string) ( $certificate['position_anchorage'] ?? '' ) ); ?>">

		<label for="bvf-jc-date">Date</label>
		<input type="date" id="bvf-jc-date" name="service_date" value="<?php echo $esc( (string) ( $certificate['service_date'] ?? '' ) ); ?>">

		<h2 class="bvf-job-cert-h2">Certification (times)</h2>
		<label for="bvf-jc-svc-boat">Service boat name</label>
		<input type="text" id="bvf-jc-svc-boat" name="service_boat_name" maxlength="255" value="<?php echo $esc( (string) ( $certificate['service_boat_name'] ?? '' ) ); ?>">

		<div class="bvf-job-cert-grid-2">
			<div>
				<label for="bvf-jc-t1">Departed port at</label>
				<input type="text" id="bvf-jc-t1" name="time_departed_port" maxlength="64" placeholder="e.g. 08:15" value="<?php echo $esc( (string) ( $certificate['time_departed_port'] ?? '' ) ); ?>">
			</div>
			<div>
				<label for="bvf-jc-t2">Arrived alongside vessel at</label>
				<input type="text" id="bvf-jc-t2" name="time_arrived_alongside" maxlength="64" placeholder="e.g. 09:40" value="<?php echo $esc( (string) ( $certificate['time_arrived_alongside'] ?? '' ) ); ?>">
			</div>
			<div>
				<label for="bvf-jc-t3">Departed from vessel at</label>
				<input type="text" id="bvf-jc-t3" name="time_departed_vessel" maxlength="64" value="<?php echo $esc( (string) ( $certificate['time_departed_vessel'] ?? '' ) ); ?>">
			</div>
			<div>
				<label for="bvf-jc-t4">Arrived port at</label>
				<input type="text" id="bvf-jc-t4" name="time_arrived_port" maxlength="64" value="<?php echo $esc( (string) ( $certificate['time_arrived_port'] ?? '' ) ); ?>">
			</div>
		</div>

		<h2 class="bvf-job-cert-h2">Purpose of trip &amp; remarks</h2>
		<label for="bvf-jc-purpose">Purpose &amp; remarks</label>
		<textarea id="bvf-jc-purpose" name="purpose_remarks" rows="8" maxlength="8000"><?php echo $esc( (string) ( $certificate['purpose_remarks'] ?? '' ) ); ?></textarea>

		<h2 class="bvf-job-cert-h2">Signatures (print name; sign on paper after printing if required)</h2>
		<label for="bvf-jc-master">Master of vessel</label>
		<input type="text" id="bvf-jc-master" name="master_signed_name" maxlength="191" value="<?php echo $esc( (string) ( $certificate['master_signed_name'] ?? '' ) ); ?>">

		<label for="bvf-jc-cox">Coxswain in-charge</label>
		<input type="text" id="bvf-jc-cox" name="coxswain_signed_name" maxlength="191" value="<?php echo $esc( (string) ( $certificate['coxswain_signed_name'] ?? '' ) ); ?>">

		<p class="bvf-form-actions">
			<button type="submit" class="bvf-btn">Save certificate</button>
			<a class="bvf-btn bvf-btn--secondary" href="/app/trips/<?php echo $tripId; ?>/job-certificate/print" target="_blank" rel="noopener">Open printable view</a>
		</p>
	</form>
</div>
