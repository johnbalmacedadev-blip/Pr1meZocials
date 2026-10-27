param(
    [Parameter(Mandatory = $true, Position = 0)]
    [ValidateSet('local', 'live')]
    [string]$Target
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $root

$active = Join-Path $root '.env'
$local = Join-Path $root '.env.local'
$live = Join-Path $root '.env.live'

function Read-EnvValue([string]$path, [string]$key) {
    if (-not (Test-Path $path)) { return $null }
    $line = Get-Content $path | Where-Object { $_ -match "^$key=" } | Select-Object -First 1
    if (-not $line) { return $null }
    return ($line -replace "^$key=", '').Trim('"')
}

function Save-ActiveBackup {
    if (-not (Test-Path $active)) { return }
    $envName = Read-EnvValue $active 'APP_ENV'
    if ($envName -eq 'local') {
        Copy-Item $active $local -Force
        Write-Host "Saved current .env → .env.local"
    } else {
        Copy-Item $active $live -Force
        Write-Host "Saved current .env → .env.live"
    }
}

if ($Target -eq 'local') {
    if (-not (Test-Path $local)) {
        throw ".env.local is missing. Create it first (XAMPP local settings)."
    }
    Save-ActiveBackup
    Copy-Item $local $active -Force
    Write-Host "Switched to LOCAL (.env.local → .env)"
} else {
    if (-not (Test-Path $live)) {
        throw ".env.live is missing. Create it first (staging/live settings)."
    }
    Save-ActiveBackup
    Copy-Item $live $active -Force
    Write-Host "Switched to LIVE (.env.live → .env)"
}

$appUrl = Read-EnvValue $active 'APP_URL'
$dbName = Read-EnvValue $active 'DB_DATABASE'
Write-Host "APP_URL=$appUrl"
Write-Host "DB_DATABASE=$dbName"

if (Test-Path (Join-Path $root 'artisan')) {
    try {
        php artisan config:clear 2>$null | Out-Null
        php artisan cache:clear 2>$null | Out-Null
        Write-Host "Cleared Laravel config/cache."
    } catch {
        Write-Host "Skipped artisan cache clear (PHP/DB may be offline)."
    }
}

Write-Host ""
if ($Target -eq 'local') {
    Write-Host "Next: create DB primez_creative_ops in phpMyAdmin if needed, then:"
    Write-Host "  php artisan migrate"
    Write-Host "  php artisan serve"
} else {
    Write-Host "Ready to commit/push. .env is back on live settings."
}
