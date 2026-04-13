<?php
/** @var \Bvf\Auth\User $user */
/** @var string $restBase */
/** @var string $nonce */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
$pageTitle       = 'Trips / Emergencies — BOSVesFinder';
$metaDescription = 'Start trips, live vessel tracking, fuel, and SOS emergencies for BOSVesFinder.';
require __DIR__ . '/partials/html-head.php';
?>
	<link rel="stylesheet" href="/assets/css/app.css">
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
	<link rel="stylesheet" href="/assets/css/captain-tracker.css">
</head>
<body class="bvf-app">
<?php
$nav_active = 'trips_emergencies';
require __DIR__ . '/partials/app-chrome.php';
?>
	<main class="bvf-main" id="bvf-captain-root">
		<div class="bvf-captain">
			<h2 class="bvf-captain__title">Trips / Emergencies</h2>
			<p class="bvf-captain__intro bvf-muted">Start a trip for a vessel assigned to you, then use tracking and SOS as needed.</p>
			<p class="bvf-captain__status" aria-live="polite"></p>
			<p class="bvf-captain__trip-actions">
				<button type="button" class="bvf-btn bvf-btn--secondary" id="bvf-captain-open-start-trip" data-bvf-open="bvf-captain-start-trip-dialog">Start new trip</button>
			</p>
			<p class="bvf-captain__job-cert" id="bvf-captain-job-cert-wrap" hidden>
				<a class="bvf-btn bvf-btn--secondary" href="#" id="bvf-captain-job-cert-link">Office job certificate (print)</a>
			</p>
			<div class="bvf-captain__actions">
				<button type="button" class="bvf-captain__start" disabled>Start trip</button>
				<button type="button" class="bvf-captain__pause" disabled>Pause tracking</button>
				<button type="button" class="bvf-captain__end" disabled>End trip</button>
				<button type="button" class="bvf-captain__sos" disabled>SOS emergency</button>
			</div>
			<div class="bvf-captain-sos-sent" id="bvf-sos-sent-strip" hidden role="status" aria-live="polite">
				<strong class="bvf-captain-sos-sent__title" id="bvf-sos-sent-title">Emergency alert sent</strong>
				<p class="bvf-captain-sos-sent__text" id="bvf-sos-sent-text"></p>
			</div>
			<section class="bvf-captain-nav" id="bvf-captain-nav" hidden aria-label="Live navigation">
				<div class="bvf-captain-nav__banner" role="status" aria-live="polite">
					<span class="bvf-captain-live" id="bvf-captain-live">
						<span class="bvf-captain-live__pulse" aria-hidden="true"></span>
						<span class="bvf-captain-live__text">Live tracking</span>
					</span>
					<span class="bvf-captain-nav__speed" id="bvf-captain-speed" hidden></span>
				</div>
				<div class="bvf-captain-nav__map-wrap">
					<div id="bvf-captain-map-el" class="bvf-captain-nav__map" role="application" aria-label="Position and heading map"></div>
				</div>
				<div class="bvf-captain-nav__compass-row">
					<div class="bvf-captain-compass" id="bvf-captain-compass">
						<svg class="bvf-captain-compass__svg" viewBox="0 0 100 100" aria-hidden="true">
							<circle class="bvf-captain-compass__ring" cx="50" cy="50" r="46" fill="rgba(255,255,255,0.92)" stroke="currentColor" stroke-width="1.5"/>
							<text class="bvf-captain-compass__label" x="50" y="15" text-anchor="middle">N</text>
							<text class="bvf-captain-compass__label" x="86" y="54" text-anchor="middle">E</text>
							<text class="bvf-captain-compass__label" x="50" y="93" text-anchor="middle">S</text>
							<text class="bvf-captain-compass__label" x="14" y="54" text-anchor="middle">W</text>
							<g class="bvf-captain-compass__needle" id="bvf-compass-needle-g" transform="rotate(0 50 50)">
								<polygon points="50,10 45,50 50,44 55,50" fill="#c62828"/>
								<polygon points="50,90 45,50 50,56 55,50" fill="#37474f"/>
							</g>
						</svg>
						<p class="bvf-captain-compass__deg" id="bvf-captain-compass-deg">—</p>
					</div>
					<p class="bvf-captain-nav__hint" id="bvf-captain-nav-hint"></p>
				</div>
			</section>
			<section class="bvf-captain-fuel" aria-labelledby="bvf-captain-fuel-heading">
				<h3 id="bvf-captain-fuel-heading" class="bvf-captain-fuel__title">Fuel</h3>
				<p class="bvf-captain-fuel__hint">Both loaded and consumed litres are required each time (enter <strong>0</strong> if one does not apply).</p>
				<p><button type="button" class="bvf-captain-fuel__open bvf-btn" id="bvf-captain-fuel-open" data-bvf-open="bvf-captain-fuel-dialog" disabled>Log fuel entry</button></p>
				<div class="bvf-captain-fuel__recent" hidden>
					<h4 class="bvf-captain-fuel__recent-title">Recent entries</h4>
					<ul class="bvf-captain-fuel__list"></ul>
				</div>
			</section>
			<p class="bvf-captain__msg" aria-live="polite"></p>
		</div>
		<dialog class="bvf-dialog" id="bvf-captain-fuel-dialog" aria-labelledby="bvf-captain-fuel-dialog-title">
			<div class="bvf-dialog__inner">
				<h2 class="bvf-dialog__title" id="bvf-captain-fuel-dialog-title">Log fuel</h2>
				<form class="bvf-captain-fuel__form">
					<label class="bvf-captain-fuel__label" for="bvf-fuel-loaded">Loaded (L) *</label>
					<input class="bvf-captain-fuel__input" type="number" id="bvf-fuel-loaded" name="fuel_loaded_litres" inputmode="decimal" step="any" min="0" placeholder="0" required>
					<label class="bvf-captain-fuel__label" for="bvf-fuel-consumed">Consumed (L) *</label>
					<input class="bvf-captain-fuel__input" type="number" id="bvf-fuel-consumed" name="fuel_consumed_litres" inputmode="decimal" step="any" min="0" placeholder="0" required>
					<label class="bvf-captain-fuel__label" for="bvf-fuel-notes">Notes</label>
					<input class="bvf-captain-fuel__input" type="text" id="bvf-fuel-notes" name="notes" maxlength="500" placeholder="optional" autocomplete="off">
					<div class="bvf-dialog__actions">
						<button type="submit" class="bvf-captain-fuel__submit bvf-btn" disabled>Save fuel entry</button>
						<button type="button" class="bvf-btn bvf-btn--secondary" data-bvf-close="bvf-captain-fuel-dialog">Cancel</button>
					</div>
				</form>
			</div>
		</dialog>
		<dialog class="bvf-dialog" id="bvf-captain-start-trip-dialog" aria-labelledby="bvf-captain-start-trip-title">
			<div class="bvf-dialog__inner">
				<h2 class="bvf-dialog__title" id="bvf-captain-start-trip-title">Start new trip</h2>
				<p class="bvf-muted bvf-captain-start-trip__intro">Choose your vessel and optional route labels. The trip appears on the fleet map when status is <strong>active</strong>.</p>
				<form class="bvf-captain-start-trip__form" id="bvf-captain-start-trip-form">
					<label class="bvf-captain-fuel__label" for="bvf-start-trip-vessel">Vessel *</label>
					<select class="bvf-captain-fuel__input" id="bvf-start-trip-vessel" name="vessel_id" required autocomplete="off">
						<option value="">Loading vessels…</option>
					</select>
					<?php if ( $user->can( \Bvf\Auth\User::CAP_MANAGE_FLEET ) ) : ?>
					<label class="bvf-captain-fuel__label" for="bvf-start-trip-captain-id">Captain user ID *</label>
					<input class="bvf-captain-fuel__input" type="number" id="bvf-start-trip-captain-id" name="captain_user_id" inputmode="numeric" min="1" step="1" value="<?php echo (int) $user->id; ?>" required>
					<label class="bvf-captain-fuel__label" for="bvf-start-trip-status">Status</label>
					<select class="bvf-captain-fuel__input" id="bvf-start-trip-status" name="status" autocomplete="off">
						<option value="active">Active (live on fleet map)</option>
						<option value="pending">Pending</option>
					</select>
					<?php endif; ?>
					<label class="bvf-captain-fuel__label" for="bvf-start-trip-origin">Origin</label>
					<input class="bvf-captain-fuel__input" type="text" id="bvf-start-trip-origin" name="origin_label" maxlength="255" placeholder="optional" autocomplete="off">
					<label class="bvf-captain-fuel__label" for="bvf-start-trip-dest">Destination</label>
					<input class="bvf-captain-fuel__input" type="text" id="bvf-start-trip-dest" name="destination_label" maxlength="255" placeholder="optional" autocomplete="off">
					<p class="bvf-captain-start-trip__err" id="bvf-start-trip-err" hidden role="alert"></p>
					<div class="bvf-dialog__actions">
						<button type="submit" class="bvf-btn" id="bvf-start-trip-submit">Create trip</button>
						<button type="button" class="bvf-btn bvf-btn--secondary" data-bvf-close="bvf-captain-start-trip-dialog">Cancel</button>
					</div>
				</form>
			</div>
		</dialog>
		<dialog class="bvf-dialog" id="bvf-sos-dialog" aria-labelledby="bvf-sos-dialog-title">
			<div class="bvf-dialog__inner">
				<h2 class="bvf-dialog__title" id="bvf-sos-dialog-title">Report emergency</h2>
				<p class="bvf-muted bvf-captain-sos__intro" id="bvf-sos-dialog-intro"></p>
				<label class="bvf-captain-sos__label" for="bvf-sos-note">What is the emergency? *</label>
				<textarea id="bvf-sos-note" class="bvf-captain-sos__textarea" rows="5" maxlength="2000" required autocomplete="off" placeholder=""></textarea>
				<p class="bvf-captain-sos__err" id="bvf-sos-field-err" hidden role="alert"></p>
				<div class="bvf-dialog__actions">
					<button type="button" class="bvf-btn bvf-captain-sos__send" id="bvf-sos-send">Send SOS alert</button>
					<button type="button" class="bvf-btn bvf-btn--secondary" id="bvf-sos-cancel">Cancel</button>
				</div>
			</div>
		</dialog>
	</main>
	<script>
	window.bvfCaptain = {
		restBase: <?php echo json_encode( $restBase, JSON_UNESCAPED_UNICODE ); ?>,
		nonce: <?php echo json_encode( $nonce, JSON_UNESCAPED_UNICODE ); ?>,
		canManageFleet: <?php echo $user->can( \Bvf\Auth\User::CAP_MANAGE_FLEET ) ? 'true' : 'false'; ?>,
		currentUserId: <?php echo (int) $user->id; ?>,
		intervalMs: 30000,
		i18n: {
			loading: 'Loading your trip…',
			noTrip: 'No trip assigned to you yet. Use “Start new trip” or wait for operations to schedule one.',
			tripLabel: 'Trip',
			statusPending: 'Pending — tap Start trip when you are underway',
			statusActive: 'Active',
			btnBeginTrip: 'Start trip',
			btnStartTracking: 'Start tracking',
			trackingPaused: 'Tracking paused. Tap Start tracking to resume, or End trip when finished.',
			tripEndedComplete: 'Trip completed. Tracking has stopped.',
			endTripConfirm: 'End this trip for good? Tracking will stop and the voyage will be marked completed.',
			endTripFailed: 'Could not end the trip.',
			activateTripFailed: 'Could not start this trip.',
			sosDialogTitle: 'Report emergency',
			sosDialogIntro: 'Describe exactly what is happening. Operations receive this immediately with your SOS.',
			sosNoteLabel: 'What is the emergency? *',
			sosNotePlaceholder: 'e.g. Medical emergency — crew member unconscious / Engine room flooding / Collision with object — hull damage…',
			sosSend: 'Send SOS alert',
			sosCancel: 'Cancel',
			sosNoteRequired: 'Describe the emergency in at least 10 characters.',
			sosSent: 'SOS recorded. Operations have been notified in the system.',
			sosSentStripTitle: 'Emergency alert sent',
			sosSentStripDetail:
				'Administrators and operations managers were notified by email at {time}. If you are still in danger, contact local emergency services.',
			fuelNeedAmount: 'Enter both loaded and consumed litres (use 0 if not applicable).',
			fuelSaved: 'Fuel entry saved.',
			fuelLoadFailed: 'Could not load fuel history.',
			fuelNoEntries: 'No fuel entries yet.',
			navHeadingLine: 'Dashed line: approximate direction of travel (when GPS reports heading).',
			navHeadingWait: 'Heading fills in when you move, or allow compass access on your device.',
			navSpeed: 'Speed',
			speedUnitKn: 'kn',
			startTripVesselPlaceholder: 'Choose vessel…',
			startTripVesselLoading: 'Loading vessels…',
			startTripNoVessels: 'No vessels available. Ask operations to assign you on Vessels.',
			startTripVesselLoadFailed: 'Could not load vessels.',
			startTripCreated: 'Trip created. You can start tracking when ready.',
			startTripCaptainRequired: 'Enter a valid captain user ID.',
			startTripPickVessel: 'Choose a vessel.'
		}
	};
	</script>
	<script src="/assets/js/bvf-app-modals.js" defer></script>
	<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
	<script src="/assets/js/captain-tracker.js"></script>
</body>
</html>
