@echo off
title COGS - Cloudflare Tunnel
cd /d "%~dp0"
powershell -ExecutionPolicy Bypass -File "%~dp0scripts\start-cloudflare-tunnel.ps1" %*
echo.
pause
