<?php
/** @var \Bvf\Auth\User $user */
/** @var string $restUrl */
/** @var string $nonce */
/** @var string $appUrl */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
$pageTitle       = 'Fleet map — BOSVesFinder';
$metaDescription = 'Live fleet map for BOSVesFinder: vessel positions, speed, and last report time from captain devices.';
require __DIR__ . '/partials/html-head.php';
?>
	<link rel="stylesheet" href="/assets/css/app.css">
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
	<link rel="stylesheet" href="/assets/css/fleet-map.css">
</head>
<body class="bvf-app">
<?php
$nav_active = 'fleet';
require __DIR__ . '/partials/app-chrome.php';
?>
	<main class="bvf-main bvf-main--full">
		<h1>Fleet map</h1>
		<div class="bvf-fleet-map-wrap bvf-fleet-map-wrap--loading" style="height:420px">
			<div class="bvf-fleet-map__loading" aria-live="polite" role="status">Loading map…</div>
			<div id="bvf-fleet-map-main" class="bvf-fleet-map" role="region" aria-label="Fleet map"></div>
		</div>
	</main>
	<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
	<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js" crossorigin=""></script>
	<script>
	window.bvfFleetMap = {
		restUrl: <?php echo json_encode( $restUrl, JSON_UNESCAPED_UNICODE ); ?>,
		tracksUrl: <?php echo json_encode( $appUrl . '/api/v1/fleet/tracks?hours=48', JSON_UNESCAPED_UNICODE ); ?>,
		heatmap: false,
		nonce: <?php echo json_encode( $nonce, JSON_UNESCAPED_UNICODE ); ?>,
		pollMs: 15000,
		i18n: { loading: 'Loading fleet…', error: 'Could not load fleet data.' }
	};
	</script>
	<script src="/assets/js/fleet-map.js"></script>
</body>
</html>
