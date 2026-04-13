# Applies db/local-mysql-setup.sql using PowerShell-safe piping.
# From repo root:  .\scripts\apply-local-mysql-setup.ps1
# With password:   .\scripts\apply-local-mysql-setup.ps1 -UsePassword

param([switch] $UsePassword)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$sql = Join-Path $root "db\local-mysql-setup.sql"

if (-not (Test-Path $sql)) {
	Write-Error "Missing: $sql"
	exit 1
}

function Get-MySqlClient {
	if (Get-Command mysql -ErrorAction SilentlyContinue) {
		return "mysql"
	}
	$candidates = @(
		"C:\xampp\mysql\bin\mysql.exe",
		"C:\wamp64\bin\mysql\mysql8.3.0\bin\mysql.exe"
	)
	foreach ($c in $candidates) {
		if (Test-Path $c) { return $c }
	}
	if (Test-Path "C:\laragon\bin\mysql") {
		$found = Get-ChildItem "C:\laragon\bin\mysql\*\bin\mysql.exe" -ErrorAction SilentlyContinue | Select-Object -First 1
		if ($found) { return $found.FullName }
	}
	foreach ($v in @("8.4", "8.0")) {
		$p = "C:\Program Files\MySQL\MySQL Server $v\bin\mysql.exe"
		if (Test-Path $p) { return $p }
	}
	return $null
}

$mysql = Get-MySqlClient
if (-not $mysql) {
	Write-Error "mysql not found. Add MySQL bin to PATH or install XAMPP/Laragon/MySQL Server."
	exit 1
}

$raw = Get-Content $sql -Raw -Encoding UTF8
if ($UsePassword) {
	$raw | & $mysql -u root -p
} else {
	$raw | & $mysql -u root
}

if ($LASTEXITCODE -ne 0) {
	Write-Error "mysql exited with code $LASTEXITCODE"
	exit $LASTEXITCODE
}

Write-Host "OK: database setup applied (vesfinder)."
