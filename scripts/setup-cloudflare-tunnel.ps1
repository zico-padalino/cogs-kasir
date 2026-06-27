# Setup Cloudflare Tunnel (named tunnel + subdomain permanen)
# Jalankan: powershell -ExecutionPolicy Bypass -File scripts/setup-cloudflare-tunnel.ps1

$ErrorActionPreference = "Stop"
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

Write-Host "=== Setup Cloudflare Tunnel — COGS Perhitungan ===" -ForegroundColor Cyan
Write-Host ""

function Test-Cloudflared {
    return [bool](Get-Command cloudflared -ErrorAction SilentlyContinue)
}

if (-not (Test-Cloudflared)) {
    Write-Host "[!] cloudflared belum terpasang." -ForegroundColor Yellow
    Write-Host "    Install via winget:" -ForegroundColor Gray
    Write-Host "    winget install --id Cloudflare.cloudflared" -ForegroundColor White
    Write-Host ""
    $install = Read-Host "Coba install otomatis dengan winget? (y/n)"
    if ($install -eq "y") {
        winget install --id Cloudflare.cloudflared --accept-package-agreements --accept-source-agreements
    } else {
        Write-Host "Unduh manual: https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/" -ForegroundColor Gray
        exit 1
    }
}

if (-not (Test-Cloudflared)) {
    throw "cloudflared masih tidak ditemukan di PATH. Restart terminal lalu coba lagi."
}

Write-Host "[OK] cloudflared: $(cloudflared --version)" -ForegroundColor Green
Write-Host ""

$tunnelName = Read-Host "Nama tunnel [cogs-perhitungan]"
if ([string]::IsNullOrWhiteSpace($tunnelName)) { $tunnelName = "cogs-perhitungan" }

$hostname = Read-Host "Subdomain publik (contoh: cogs.domainanda.com)"
if ([string]::IsNullOrWhiteSpace($hostname)) {
    throw "Hostname wajib diisi (domain harus sudah di Cloudflare)."
}

$port = Read-Host "Port Laravel lokal [8900]"
if ([string]::IsNullOrWhiteSpace($port)) { $port = "8900" }

Write-Host ""
Write-Host "1) Login Cloudflare (browser akan terbuka)..." -ForegroundColor Cyan
cloudflared tunnel login

Write-Host ""
Write-Host "2) Membuat tunnel '$tunnelName'..." -ForegroundColor Cyan
$createOutput = cloudflared tunnel create $tunnelName 2>&1 | Out-String
Write-Host $createOutput

$tunnelId = $null
if ($createOutput -match "([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})") {
    $tunnelId = $Matches[1]
}

if (-not $tunnelId) {
    Write-Host "Tunnel mungkin sudah ada. Mencoba ambil ID..." -ForegroundColor Yellow
    $listOutput = cloudflared tunnel list 2>&1 | Out-String
    Write-Host $listOutput
    $tunnelId = Read-Host "Masukkan Tunnel ID (UUID)"
}

$credDir = Join-Path $projectRoot "scripts\cloudflare"
New-Item -ItemType Directory -Force -Path $credDir | Out-Null

$credFile = Join-Path $credDir "$tunnelId.json"
if (-not (Test-Path $credFile)) {
    $defaultCred = Join-Path $env:USERPROFILE ".cloudflared\$tunnelId.json"
    if (Test-Path $defaultCred) {
        Copy-Item $defaultCred $credFile -Force
        Write-Host "[OK] Credentials disalin ke scripts/cloudflare/" -ForegroundColor Green
    } else {
        Write-Host "[!] File credentials tidak ditemukan. Pastikan tunnel create berhasil." -ForegroundColor Yellow
    }
}

$configPath = Join-Path $credDir "config.yml"
$credPathForYaml = $credFile -replace "\\", "/"

@(
    "tunnel: $tunnelId"
    "credentials-file: $credPathForYaml"
    ""
    "ingress:"
    "  - hostname: $hostname"
    "    service: http://127.0.0.1:$port"
    "  - service: http_status:404"
) | Set-Content -Path $configPath -Encoding UTF8

Write-Host ""
Write-Host "3) Routing DNS $hostname -> tunnel..." -ForegroundColor Cyan
cloudflared tunnel route dns $tunnelId $hostname

Write-Host ""
Write-Host "=== Setup selesai ===" -ForegroundColor Green
Write-Host "Config : $configPath"
Write-Host "URL    : https://$hostname"
Write-Host ""
Write-Host "Update .env Anda:" -ForegroundColor Cyan
Write-Host "  APP_URL=https://$hostname"
Write-Host "  APP_PORT=$port"
Write-Host ""
Write-Host "Jalankan aplikasi + tunnel:" -ForegroundColor Cyan
Write-Host "  powershell -ExecutionPolicy Bypass -File scripts/start-cloudflare-tunnel.ps1 -Mode Named"
Write-Host ""
