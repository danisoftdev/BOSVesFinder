<?php
declare(strict_types=1);

use Bvf\EnvConfig;

$pageTitle         = $pageTitle ?? 'BOSVesFinder';
$metaDescription   = $metaDescription ?? 'BOSVesFinder — vessel tracking and fleet intelligence for offshore operations. Live positions, SOS, geofences, trips, crew, and fuel logging from captains’ smartphones.';
$metaRobots        = $metaRobots ?? 'index,follow';
$base              = rtrim( EnvConfig::requestAppBaseUrl(), '/' );
$reqPath           = (string) ( $_SERVER['REQUEST_URI'] ?? '/' );
$pathOnly          = strtok( $reqPath, '?' );
if ( false === $pathOnly || $pathOnly === '' ) {
	$pathOnly = '/';
}
$canonicalUrl      = $base . $pathOnly;
$logoAbsolute      = $base . '/assets/img/vesfinder-logo.png';
$titleSafe         = htmlspecialchars( $pageTitle, ENT_QUOTES, 'UTF-8' );
$descSafe          = htmlspecialchars( $metaDescription, ENT_QUOTES, 'UTF-8' );
$canonicalSafe     = htmlspecialchars( $canonicalUrl, ENT_QUOTES, 'UTF-8' );
$logoUrlSafe       = htmlspecialchars( $logoAbsolute, ENT_QUOTES, 'UTF-8' );
$ld = array(
	'@context'    => 'https://schema.org',
	'@type'       => 'WebApplication',
	'name'        => 'BOSVesFinder',
	'description' => $metaDescription,
	'url'         => $base . '/',
	'image'       => $logoAbsolute,
	'applicationCategory' => 'BusinessApplication',
	'operatingSystem'     => 'Web',
);
?>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo $titleSafe; ?></title>
	<meta name="description" content="<?php echo $descSafe; ?>">
	<meta name="robots" content="<?php echo htmlspecialchars( $metaRobots, ENT_QUOTES, 'UTF-8' ); ?>">
	<link rel="canonical" href="<?php echo $canonicalSafe; ?>">
	<meta name="theme-color" content="#0d2137">
	<link rel="icon" href="/assets/img/vesfinder-logo.png" type="image/png">
	<link rel="apple-touch-icon" href="/assets/img/vesfinder-logo.png">
	<meta property="og:type" content="website">
	<meta property="og:site_name" content="BOSVesFinder">
	<meta property="og:title" content="<?php echo $titleSafe; ?>">
	<meta property="og:description" content="<?php echo $descSafe; ?>">
	<meta property="og:url" content="<?php echo $canonicalSafe; ?>">
	<meta property="og:image" content="<?php echo $logoUrlSafe; ?>">
	<meta property="og:image:alt" content="BOSVesFinder">
	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:title" content="<?php echo $titleSafe; ?>">
	<meta name="twitter:description" content="<?php echo $descSafe; ?>">
	<meta name="twitter:image" content="<?php echo $logoUrlSafe; ?>">
	<script type="application/ld+json"><?php echo json_encode( $ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
