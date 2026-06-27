# Jalankan Laravel + Cloudflare Tunnel (akses publik)
# Quick (demo):  powershell -ExecutionPolicy Bypass -File scripts/start-cloudflare-tunnel.ps1
# Named (tetap):  powershell -ExecutionPolicy Bypass -File scripts/start-cloudflare-tunnel.ps1 -Mode Named

param(
    [ValidateSet("Quick", "Named")]
    [string]$Mode = "Quick",
    [int]$Port = 0,
    [switch]$SkipEnvUpdate
)

$ErrorActionPreference = "Stop"
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

function Write-Info($msg) { Write-Host $msg -ForegroundColor Cyan }
function Write-Ok($msg) { Write-Host $msg -ForegroundColor Green }
function Write-Warn($msg) { Write-Host $msg -ForegroundColor Yellow }

function Get-EnvFilePath {
    return Join-Path $projectRoot ".env"
}

function Get-EnvValue([string]$key) {
    $envFile = Get-EnvFilePath
    if (-not (Test-Path $envFile)) { return $null }
    foreach ($line in Get-Content $envFile) {
        if ($line -match "^\s*$key=(.*)$") {
            return $Matches[1].Trim().Trim('"').Trim("'")
        }
    }
    return $null
}

function Set-EnvValue([string]$key, [string]$value) {
    $envFile = Get-EnvFilePath
    if (-not (Test-Path $envFile)) {
        throw "File .env tidak ditemukan."
    }

    $lines = Get-Content $envFile
    $found = $false
    $updated = foreach ($line in $lines) {
        if ($line -match "^\s*$key=") {
            $found = $true
            "$key=$value"
        } else {
            $line
        }
    }

    if (-not $found) {
        $updated += "$key=$value"
    }

    $updated | Set-Content -Path $envFile -Encoding UTF8
}

function Update-EnvForPublicUrl([string]$publicUrl) {
    if ($SkipEnvUpdate) { return }

    Set-EnvValue "APP_URL" $publicUrl

    if ($publicUrl -like "https://*") {
        Set-EnvValue "SESSION_SECURE_COOKIE" "true"
    }

    php artisan config:clear | Out-Null
}

function Test-Cloudflared {
    return [bool](Get-Command cloudflared -ErrorAction SilentlyContinue)
}

function Test-Php {
    return [bool](Get-Command php -ErrorAction SilentlyContinue)
}

function Wait-QuickTunnelUrl([string]$logPath, [int]$timeoutSeconds = 90) {
    $deadline = (Get-Date).AddSeconds($timeoutSeconds)

    while ((Get-Date) -lt $deadline) {
        if (Test-Path $logPath) {
            $content = Get-Content $logPath -Raw -ErrorAction SilentlyContinue
            if ($content -and $content -match '(https://[a-z0-9-]+\.trycloudflare\.com)') {
                return $Matches[1]
            }
        }
        Start-Sleep -Seconds 1
    }

    return $null
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  COGS Perhitungan — Cloudflare Tunnel" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

if (-not (Test-Php)) {
    throw "PHP tidak ditemukan. Install PHP 8.3+ dan tambahkan ke PATH."
}

if (-not (Test-Cloudflared)) {
    Write-Warn "cloudflared belum terpasang."
    Write-Host "Install: winget install --id Cloudflare.cloudflared" -ForegroundColor Gray
    Write-Host "Atau setup lengkap: scripts/setup-cloudflare-tunnel.ps1" -ForegroundColor Gray
    throw "cloudflared tidak ditemukan di PATH."
}

if ($Port -le 0) {
    $envPort = Get-EnvValue "APP_PORT"
    if ($envPort -and [int]$envPort -gt 0) {
        $Port = [int]$envPort
    } else {
        $Port = 8900
    }
}

$localUrl = "http://127.0.0.1:$Port"
$configPath = Join-Path $projectRoot "scripts\cloudflare\config.yml"

if ($Mode -eq "Named" -and -not (Test-Path $configPath)) {
    Write-Warn "File config named tunnel belum ada: scripts/cloudflare/config.yml"
    Write-Host "Jalankan setup dulu:" -ForegroundColor Gray
    Write-Host "  powershell -ExecutionPolicy Bypass -File scripts/setup-cloudflare-tunnel.ps1" -ForegroundColor White
    throw "Named tunnel belum dikonfigurasi."
}

Write-Ok "[OK] PHP: $(php -r 'echo PHP_VERSION;')"
Write-Ok "[OK] cloudflared: $(cloudflared --version)"
Write-Ok "[OK] Laravel: $localUrl"
Write-Host ""
Write-Warn "Pastikan MySQL/XAMPP sudah berjalan — tunnel hanya membuka Laravel, bukan database."
Write-Host ""

Write-Info "Membersihkan cache config Laravel..."
php artisan config:clear | Out-Null

Write-Info "Memulai Laravel server..."
$phpJob = Start-Job -ScriptBlock {
    param($root, $port)
    Set-Location $root
    php artisan serve --host=127.0.0.1 --port=$port
} -ArgumentList $projectRoot, $Port

Start-Sleep -Seconds 2

if ($phpJob.State -eq "Failed") {
    Receive-Job $phpJob -ErrorAction SilentlyContinue
    throw "Gagal memulai php artisan serve."
}

Write-Ok "Laravel berjalan di $localUrl (Job ID: $($phpJob.Id))"
Write-Host ""

$tunnelJob = $null
$tunnelLog = Join-Path $env:TEMP "cogs-cloudflared.log"

try {
    if ($Mode -eq "Named") {
        $appUrl = Get-EnvValue "APP_URL"
        Write-Info "Mode: Named tunnel (subdomain permanen)"
        Write-Host "Config: scripts/cloudflare/config.yml" -ForegroundColor Gray

        if ($appUrl -like "https://*") {
            Write-Ok "APP_URL: $appUrl"
            if (-not $SkipEnvUpdate) {
                Set-EnvValue "SESSION_SECURE_COOKIE" "true"
                php artisan config:clear | Out-Null
            }
        } else {
            Write-Warn "Set APP_URL=https://subdomain-anda.com di .env agar link/QR meja benar."
        }

        Write-Host ""
        Write-Warn "Tekan Ctrl+C untuk menghentikan tunnel + server."
        Write-Host ""

        cloudflared tunnel --config $configPath run
    } else {
        Write-Info "Mode: Quick tunnel (URL sementara trycloudflare.com)"
        Write-Host "Cocok untuk demo — URL berubah setiap kali dijalankan." -ForegroundColor Gray
        Write-Host ""

        if (Test-Path $tunnelLog) {
            Remove-Item $tunnelLog -Force -ErrorAction SilentlyContinue
        }

        $tunnelJob = Start-Job -ScriptBlock {
            param($targetUrl, $logPath)
            cloudflared tunnel --url $targetUrl --loglevel info 2>&1 |
                Tee-Object -FilePath $logPath
        } -ArgumentList $localUrl, $tunnelLog

        Write-Info "Menunggu URL publik dari Cloudflare..."
        $publicUrl = Wait-QuickTunnelUrl $tunnelLog

        if ($publicUrl) {
            Write-Host ""
            Write-Ok "URL publik: $publicUrl"
            Update-EnvForPublicUrl $publicUrl
            Write-Ok "APP_URL di .env diperbarui (QR meja & link kasir memakai URL ini)."
            Write-Host ""
        } else {
            Write-Warn "URL belum terdeteksi otomatis. Lihat log: $tunnelLog"
            Write-Warn "Set manual APP_URL=https://....trycloudflare.com lalu php artisan config:clear"
            Write-Host ""
        }

        Write-Warn "Tekan Ctrl+C untuk menghentikan tunnel + server."
        Write-Host ""

        Wait-Job $tunnelJob
    }
} finally {
    Write-Host ""
    Write-Info "Menghentikan proses..."

    if ($tunnelJob) {
        Stop-Job $tunnelJob -ErrorAction SilentlyContinue
        Remove-Job $tunnelJob -Force -ErrorAction SilentlyContinue
    }

    if ($phpJob) {
        Stop-Job $phpJob -ErrorAction SilentlyContinue
        Remove-Job $phpJob -Force -ErrorAction SilentlyContinue
    }

    Write-Ok "Selesai."
}
