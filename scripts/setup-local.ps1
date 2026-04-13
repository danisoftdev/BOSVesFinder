# One-shot local setup (Windows). From repo root:
#   .\scripts\setup-local.ps1
# With DB + seed (after editing .env):
#   .\scripts\setup-local.ps1 -ApplyDb -Seed

param(
	[switch]$ApplyDb,
	[switch]$Seed
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
	Write-Error "composer not found in PATH."
	exit 1
}

composer install
if (-not (Test-Path ".\.env")) {
	if (Test-Path ".\.env.example") {
		Copy-Item ".\.env.example" ".\.env"
		Write-Host "Created .env from .env.example — edit DB_* then re-run with -ApplyDb -Seed if needed."
	}
}

if ($ApplyDb) {
	& "$PSScriptRoot\apply-local-mysql-setup.ps1"
}

if ($Seed) {
	if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
		Write-Error "php not found in PATH (needed for -Seed)."
		exit 1
	}
	php bin\seed.php
}

Write-Host "Next: set DB_* in .env, ensure MySQL is running, then: php bin\seed.php (or -Seed)"
Write-Host "Run app: .\scripts\dev-server.ps1"
Write-Host "Cron (optional): composer cron or php bin\cron.php"
