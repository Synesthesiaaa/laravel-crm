@echo off
title Laravel CRM - Startup
set "PROJECT_DIR=%~dp0"
set "PROJECT_DIR=%PROJECT_DIR:~0,-1%"
cd /d "%PROJECT_DIR%"

echo Starting Laravel CRM services in one window...
echo.

echo Applying pending database migrations before starting services...
php artisan migrate --force
if errorlevel 1 (
    echo Migration failed. Services were not started.
    exit /b 1
)
echo.

start /b php artisan serve --host=localhost --port=8000
timeout /t 1 /nobreak >nul

start /b php artisan reverb:start
timeout /t 1 /nobreak >nul

start /b php artisan queue:work --queue=telephony,default --tries=1 --timeout=800
timeout /t 1 /nobreak >nul

start /b php artisan queue:work --queue=imports --tries=1 --timeout=800
timeout /t 1 /nobreak >nul

start /b php artisan queue:work --queue=asterisk --tries=1 --timeout=120
timeout /t 1 /nobreak >nul

start /b php artisan queue:work database --queue=telephony-sync --tries=1 --timeout=60
timeout /t 1 /nobreak >nul

start /b php artisan ami:listen

if "%START_EMAIL_WORKER%"=="1" (
    start /b php artisan queue:work email-database --queue=email-campaigns --tries=1 --timeout=60
)

echo.
echo Services running (serve, reverb, queue lanes, telephony sync, AMI). Set START_EMAIL_WORKER=1 to consume email campaigns.
echo.
pause
