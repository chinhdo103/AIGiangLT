@echo off
chcp 65001 >nul
title Web Admin Server - Iris Recognition
cd /d "%~dp0"

echo ========================================
echo  Web Admin Server (Port 3000)
echo ========================================
echo.

echo [INFO] Khởi động PHP Web Server...
echo [INFO] URL: http://127.0.0.1:3000
echo [INFO] Quản trị: admin / admin123
echo.
echo [INFO] Bấm Ctrl+C để dừng
echo ========================================
echo.

php -S 0.0.0.0:3000
