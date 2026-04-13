@echo off
title Laravel CRM - Startup
set "PROJECT_DIR=%~dp0"
set "PROJECT_DIR=%PROJECT_DIR:~0,-1%"
cd /d "%PROJECT_DIR%"

echo Starting Laravel CRM services in one window...
echo.

start /b php artisan serve --host=localhost --port=8000
timeout /t 1 /nobreak >nul

start /b php artisan reverb:start
timeout /t 1 /nobreak >nul

start /b php artisan queue:work
timeout /t 1 /nobreak >nul

start /b php artisan ami:listen

start /b php artisan migrate --force

echo.
echo All services running (serve, reverb, queue, ami, migrate). Close this window to stop all.
echo.
pause
