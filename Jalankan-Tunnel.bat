@echo off
title COGS Perhitungan - Cloudflare Tunnel
cd /d "%~dp0"

echo.
echo ========================================
echo   COGS Perhitungan - Cloudflare Tunnel
echo ========================================
echo.
echo Quick tunnel (default): double-click file ini
echo Named tunnel: Jalankan-Tunnel.bat -Mode Named
echo.
echo Pastikan MySQL/XAMPP sudah berjalan.
echo Tekan Ctrl+C untuk menghentikan tunnel + server.
echo.

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\start-cloudflare-tunnel.ps1" %*
set EXIT_CODE=%ERRORLEVEL%

echo.
if %EXIT_CODE% NEQ 0 (
    echo Selesai dengan error (kode %EXIT_CODE%).
) else (
    echo Selesai.
)
pause
