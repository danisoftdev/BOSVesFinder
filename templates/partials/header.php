<?php
/** @var \Bvf\Auth\User $user */
/** @var string $title */
/** @var string $nav_active */
/** @var array{message:string,type:string}|null $flash */
if ( ! isset( $nav_active ) ) {
	$nav_active = '';
}
$flash = $flash ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
$pageTitle = $title . ' — BOSVesFinder';
require __DIR__ . '/html-head.php';
?>
	<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bvf-app">
<?php require __DIR__ . '/app-chrome.php'; ?>
	<?php if ( $flash && $flash['message'] !== '' ) : ?>
		<div class="bvf-flash bvf-flash--<?php echo htmlspecialchars( $flash['type'], ENT_QUOTES, 'UTF-8' ); ?>">
			<?php echo htmlspecialchars( $flash['message'], ENT_QUOTES, 'UTF-8' ); ?>
		</div>
	<?php endif; ?>
	<main class="bvf-main">
