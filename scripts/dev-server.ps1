# Run BOSVesFinder (standalone PHP). From repo root:
#   composer install
#   copy .env.example .env  — set DB_*
#   mysql ... < database/schema.sql   OR php bin/seed.php
#   .\scripts\dev-server.ps1
#
# Default: http://localhost:8080 — login admin@example.local / admin (after seed)
#
# Bind host: use "localhost" so both http://localhost:PORT and http://127.0.0.1:PORT
# work on Windows (127.0.0.1-only binds break "localhost" when the browser uses IPv6).
# Override: .\scripts\dev-server.ps1 -BindHost 127.0.0.1

param(
	[int] $Port = 8080,
	[string] $BindHost = 'localhost'
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

$php = $null
if (Get-Command php -ErrorAction SilentlyContinue) { $php = "php" }
elseif (Test-Path "C:\xampp\php\php.exe") { $php = "C:\xampp\php\php.exe" }
else { Write-Error "PHP not found."; exit 1 }

if (-not (Test-Path ".\vendor\autoload.php")) {
	Write-Error "Run: composer install"
	exit 1
}

Write-Host "http://localhost:$Port/  (also try http://127.0.0.1:$Port/) Ctrl+C to stop"
& $php -S "${BindHost}:${Port}" -t public public/router.php
