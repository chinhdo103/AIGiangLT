@echo off
chcp 65001 >nul
title Real Python AI Service - Iris Biometrics
cd /d "%~dp0"

echo ==================================================
echo  Khởi Động AI Service Sinh Trắc Mống Mắt (Cổng 8000)
echo ==================================================
echo.

if exist "python\python.exe" (
    set "PYTHON_EXE=python\python.exe"
) else (
    set "PYTHON_EXE=python"
)

echo [INFO] Đang khởi chạy FastAPI Backend với %PYTHON_EXE%...
set PYTHONUTF8=1
set PYTHONIOENCODING=utf-8
%PYTHON_EXE% ai_service/main.py
