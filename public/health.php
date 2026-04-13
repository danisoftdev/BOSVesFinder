<?php

declare(strict_types=1);

/**
 * No DB, no framework — use to verify the web server document root points at /public.
 * Open: http://127.0.0.1:8080/health.php (or your host/port).
 */
header( 'Content-Type: text/plain; charset=utf-8' );
echo "BOSVesFinder health OK\n";
echo 'PHP ' . PHP_VERSION . "\n";
echo 'Time ' . gmdate( 'c' ) . "\n";
