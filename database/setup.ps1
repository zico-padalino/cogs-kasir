# Setup database COGS — MySQL (XAMPP / MySQL Server)
# Jalankan: powershell -ExecutionPolicy Bypass -File database\setup.ps1

$ErrorActionPreference = "Stop"
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

$dbName = "cogs_perhitungan"
$dbHost = if ($env:DB_HOST) { $env:DB_HOST } else { "127.0.0.1" }
$dbPort = if ($env:DB_PORT) { $env:DB_PORT } else { "3306" }
$dbUser = if ($env:DB_USERNAME) { $env:DB_USERNAME } else { "root" }
$dbPass = if ($env:DB_PASSWORD) { $env:DB_PASSWORD } else { "" }

Write-Host "=== COGS Database Setup (MySQL) ===" -ForegroundColor Cyan
Write-Host "Database: $dbName @ ${dbHost}:${dbPort}" -ForegroundColor Gray

# Coba buat database via mysql CLI (opsional)
$mysql = Get-Command mysql -ErrorAction SilentlyContinue
if ($mysql) {
    Write-Host "Membuat database jika belum ada..." -ForegroundColor Cyan
    if ($dbPass) {
        & mysql -h $dbHost -P $dbPort -u $dbUser -p$dbPass -e "CREATE DATABASE IF NOT EXISTS ``$dbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    } else {
        & mysql -h $dbHost -P $dbPort -u $dbUser -e "CREATE DATABASE IF NOT EXISTS ``$dbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    }
    Write-Host "[OK] Database $dbName siap" -ForegroundColor Green
} else {
    Write-Host "[INFO] mysql CLI tidak ditemukan — buat database manual:" -ForegroundColor Yellow
    Write-Host "       CREATE DATABASE $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
}

Write-Host "Membersihkan cache config..." -ForegroundColor Cyan
php artisan config:clear

Write-Host "Menjalankan migrasi Laravel..." -ForegroundColor Cyan
php artisan migrate --force

Write-Host "Membuat user login demo..." -ForegroundColor Cyan
php artisan db:seed --class=UserSeeder --force

Write-Host ""
Write-Host "Database MySQL siap digunakan!" -ForegroundColor Green
Write-Host ""
Write-Host "Pastikan .env sudah diatur:" -ForegroundColor Cyan
Write-Host "  DB_CONNECTION=mysql"
Write-Host "  DB_HOST=$dbHost"
Write-Host "  DB_PORT=$dbPort"
Write-Host "  DB_DATABASE=$dbName"
Write-Host "  DB_USERNAME=$dbUser"
Write-Host ""
Write-Host "Perintah berguna:" -ForegroundColor Cyan
Write-Host "  php artisan serve --port=8900"
Write-Host "  php artisan db:seed --class=UserSeeder"
Write-Host ""
Write-Host "File SQL:" -ForegroundColor Cyan
Write-Host "  database\queries.sql       — query laporan (MySQL)"
Write-Host "  database\mysql_full.sql    — install + data demo"
Write-Host "  database\fix_users.sql     — perbaiki akun login jika password salah"
Write-Host ""
Write-Host "Login demo:" -ForegroundColor Cyan
Write-Host "  COGS  → cogs@local.test / password"
Write-Host "  Kasir → kasir@local.test / password"
