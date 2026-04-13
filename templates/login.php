<?php
/** @var string|null $error */
$pageTitle       = 'Sign in — BOSVesFinder';
$metaDescription = 'Sign in to BOSVesFinder for fleet visibility, captain tracking, alerts, and operations.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/partials/html-head.php'; ?>
	<link rel="stylesheet" href="/assets/css/app.css">
	<style>
		.bvf-login-page { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
		.bvf-login { width: 100%; max-width: 380px; background: #fff; padding: 1.5rem 1.35rem; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
		.bvf-login__logo { display: block; max-width: 220px; height: auto; margin: 0 auto 1.25rem; }
		.bvf-login h1 { font-size: 1.1rem; margin: 0 0 0.35rem; text-align: center; font-weight: 600; }
		.bvf-login > p { margin: 0 0 1rem; text-align: center; color: #555; font-size: 14px; }
		.bvf-login label { display: block; margin: 0.75rem 0 0.25rem; font-size: 14px; font-weight: 500; }
		.bvf-login input { width: 100%; padding: 0.5rem 0.55rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 15px; }
		.bvf-login button { margin-top: 1.1rem; padding: 0.55rem 1rem; width: 100%; cursor: pointer; border-radius: 4px; border: 1px solid #0d2137; background: #0d2137; color: #fff; font-size: 15px; }
		.bvf-login .err { color: #b71c1c; margin-top: 0.75rem; font-size: 14px; }
	</style>
</head>
<body class="bvf-app bvf-login-page">
	<div class="bvf-login">
		<img class="bvf-login__logo" src="/assets/img/vesfinder-logo.png" alt="BOSVesFinder — vessel tracking and fleet intelligence" decoding="async">
		<h1>Sign in</h1>
		<p>Use your operations account to continue.</p>
		<?php if ( ! empty( $error ) ) : ?>
			<p class="err"><?php echo htmlspecialchars( (string) $error, ENT_QUOTES, 'UTF-8' ); ?></p>
		<?php endif; ?>
		<form method="post" action="/login">
			<label for="email">Email</label>
			<input type="email" id="email" name="email" required autocomplete="username">
			<label for="password">Password</label>
			<input type="password" id="password" name="password" required autocomplete="current-password">
			<button type="submit">Sign in</button>
		</form>
	</div>
</body>
</html>
