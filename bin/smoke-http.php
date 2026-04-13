<?php

declare(strict_types=1);

/**
 * CLI smoke test: boot the web app like a request. Usage: php bin/smoke-http.php
 */

$root = dirname( __DIR__ );
chdir( $root . '/public' );

$_SERVER['REQUEST_URI']     = $argv[1] ?? '/login';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['HTTP_HOST']       = 'localhost';
$_SERVER['HTTPS']           = 'off';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['SCRIPT_NAME']     = '/index.php';

require $root . '/public/index.php';
