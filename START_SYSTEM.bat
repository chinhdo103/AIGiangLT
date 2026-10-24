@echo off
chcp 65001 >nul
title Iris Recognition - System Startup
cd /d "%~dp0"

echo ========================================
echo  Iris Recognition System - Startup
echo ========================================
echo.

echo [1/4] Checking PHP...
where php >nul 2>&1
if errorlevel 1 (
    echo ❌ PHP not found! Install PHP 8.0+ and add to PATH
    echo Download: https://windows.php.net/download/
    pause
    exit /b 1
)
php -v | findstr /C:"PHP"
echo.

echo [2/4] Starting AI Mock Server (port 8000)...
start "AI Mock Server - Iris" cmd /k "cd /d %~dp0 && START_AI_SERVER.bat"
timeout /t 3 /nobreak >nul

echo [3/4] Starting Web Admin Server (port 3000)...
start "Web Admin - Iris" cmd /k "cd /d %~dp0 && START_WEB_ADMIN.bat"
timeout /t 3 /nobreak >nul

echo [4/4] Testing connection...
php test_ai_connection.php
if errorlevel 1 (
    echo.
    echo ❌ AI Server connection failed!
    echo See FIX_CONNECTION_ERROR.md for troubleshooting
    pause
    exit /b 1
)

echo.
echo ========================================
echo  ✅ System Started Successfully!
echo ========================================
echo  AI Server:  http://127.0.0.1:8000/health
echo  Web Admin:  http://127.0.0.1:3000
echo ========================================
echo.
echo Opening browser in 2 seconds...
timeout /t 2 /nobreak >nul
start http://127.0.0.1:3000

echo.
echo System is running. Close this window to stop servers.
echo Or press any key to open manual stop menu...
pause >nul

echo.
echo Stopping all servers...
taskkill /FI "WINDOWTITLE eq AI Mock Server*" /F >nul 2>&1
taskkill /FI "WINDOWTITLE eq Web Admin*" /F >nul 2>&1
echo ✅ All servers stopped.
timeout /t 2 /nobreak >nul
