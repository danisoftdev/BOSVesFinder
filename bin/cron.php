<?php

declare(strict_types=1);

/**
 * CLI cron: signal-loss alerts for stale active trips.
 * Example: run every 15 minutes via crontab: cd repo && php bin/cron.php
 */

$root = dirname( __DIR__ );
require $root . '/vendor/autoload.php';

if ( is_readable( $root . '/.env' ) ) {
	\Dotenv\Dotenv::createImmutable( $root )->load();
}

$n = Bvf\Services\SignalLossCron::run();
echo 'Signal-loss alerts created: ' . $n . PHP_EOL;
