<?php
/** @var array<string,mixed> $certificate */
$esc = static function ( string $s ): string {
	return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' );
};
$ref = isset( $certificate['form_reference'] ) && $certificate['form_reference'] !== null && $certificate['form_reference'] !== ''
	? (string) $certificate['form_reference']
	: '—';
$svcDate = '';
if ( ! empty( $certificate['service_date'] ) ) {
	$svcDate = (string) $certificate['service_date'];
}
?>
<div class="bvf-cert-sheet">
	<header class="bvf-cert-head">
		<div class="bvf-cert-head__left">
			<img src="/assets/img/Benmarine%20logo.png" width="120" height="" alt="Benmarine Services" class="bvf-cert-head__logo" decoding="async">
			<div class="bvf-cert-head__title-block">
				<div class="bvf-cert-head__company">Benmarine Services</div>
				<p class="bvf-cert-head__tagline">Total Maritime Service Provider in West Africa</p>
			</div>
		</div>
		<div class="bvf-cert-head__right">
			<div class="bvf-cert-head__serial">Form ref.: <strong><?php echo $esc( $ref ); ?></strong></div>
			<div class="bvf-cert-head__contact">
				<div>P.O. Box BT 683, Tema</div>
				<div>Tel.: +233 303 330 475 / +233 303 330 476 / +233 303 330 477</div>
				<div>Mob.: +233 24 432 0871 / +233 24 432 0872</div>
				<div>Email: info@benmarineservices.com · www.benmarineservices.com</div>
				<div class="bvf-cert-head__branches">Tema Branch · Takoradi Branch · Ada Branch</div>
			</div>
		</div>
	</header>

	<h1 class="bvf-cert-form-title">Services boat&rsquo;s job request &amp; certificate form</h1>

	<section class="bvf-cert-block">
		<div class="bvf-cert-row bvf-cert-row--2">
			<div class="bvf-cert-field">
				<span class="bvf-cert-label">Client&rsquo;s name</span>
				<div class="bvf-cert-line"><?php echo $esc( (string) ( $certificate['client_name'] ?? '' ) ); ?></div>
			</div>
		</div>
		<div class="bvf-cert-row">
			<div class="bvf-cert-field bvf-cert-field--full">
				<span class="bvf-cert-label">Address &amp; phone no.</span>
				<div class="bvf-cert-line bvf-cert-line--tall"><?php echo nl2br( $esc( (string) ( $certificate['client_address_phone'] ?? '' ) ) ); ?></div>
			</div>
		</div>
		<div class="bvf-cert-row bvf-cert-row--3">
			<div class="bvf-cert-field">
				<span class="bvf-cert-label">Vessel&rsquo;s name</span>
				<div class="bvf-cert-line"><?php echo $esc( (string) ( $certificate['client_vessel_name'] ?? '' ) ); ?></div>
			</div>
			<div class="bvf-cert-field">
				<span class="bvf-cert-label">Position at anchorage</span>
				<div class="bvf-cert-line"><?php echo $esc( (string) ( $certificate['position_anchorage'] ?? '' ) ); ?></div>
			</div>
			<div class="bvf-cert-field">
				<span class="bvf-cert-label">Date</span>
				<div class="bvf-cert-line"><?php echo $esc( $svcDate ); ?></div>
			</div>
		</div>
	</section>

	<section class="bvf-cert-section-title">Certification</section>
	<section class="bvf-cert-block bvf-cert-block--boxed">
		<p class="bvf-cert-certify-intro">This is to certify that the service boat <strong><?php echo $esc( (string) ( $certificate['service_boat_name'] ?? '' ) ); ?></strong></p>
		<div class="bvf-cert-row bvf-cert-row--2">
			<div class="bvf-cert-field">
				<span class="bvf-cert-label">Departed port at</span>
				<div class="bvf-cert-line"><?php echo $esc( (string) ( $certificate['time_departed_port'] ?? '' ) ); ?></div>
			</div>
			<div class="bvf-cert-field">
				<span class="bvf-cert-label">Arrived alongside vessel at</span>
				<div class="bvf-cert-line"><?php echo $esc( (string) ( $certificate['time_arrived_alongside'] ?? '' ) ); ?></div>
			</div>
		</div>
		<div class="bvf-cert-row bvf-cert-row--2">
			<div class="bvf-cert-field">
				<span class="bvf-cert-label">Departed from vessel at</span>
				<div class="bvf-cert-line"><?php echo $esc( (string) ( $certificate['time_departed_vessel'] ?? '' ) ); ?></div>
			</div>
			<div class="bvf-cert-field">
				<span class="bvf-cert-label">Arrived port at</span>
				<div class="bvf-cert-line"><?php echo $esc( (string) ( $certificate['time_arrived_port'] ?? '' ) ); ?></div>
			</div>
		</div>
	</section>

	<section class="bvf-cert-section-title">Purpose of trip &amp; remarks</section>
	<section class="bvf-cert-block bvf-cert-block--boxed bvf-cert-block--remarks">
		<div class="bvf-cert-remarks"><?php echo nl2br( $esc( (string) ( $certificate['purpose_remarks'] ?? '' ) ) ); ?></div>
	</section>

	<footer class="bvf-cert-signatures">
		<div class="bvf-cert-sig">
			<div class="bvf-cert-line"><?php echo $esc( (string) ( $certificate['master_signed_name'] ?? '' ) ); ?></div>
			<span class="bvf-cert-sig-label">Master of vessel</span>
		</div>
		<div class="bvf-cert-sig">
			<div class="bvf-cert-line"><?php echo $esc( (string) ( $certificate['coxswain_signed_name'] ?? '' ) ); ?></div>
			<span class="bvf-cert-sig-label">Coxswain in-charge</span>
		</div>
	</footer>

	<p class="bvf-cert-footer-note">email: info@benmarineservices.com &nbsp;|&nbsp; website: www.benmarineservices.com</p>
</div>
