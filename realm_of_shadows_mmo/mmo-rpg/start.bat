@echo off
chcp 65001 >nul 2>&1
title Realm of Shadows - MMORPG

echo.
echo ==========================================
echo    Realm of Shadows - MMORPG
echo ==========================================
echo.

cd /d "%~dp0public"

where php >nul 2>&1
if %errorlevel% equ 0 (
    echo [OK] PHP found. Starting server...
    echo.
    echo Open http://localhost:8080 in your browser.
    echo Press Ctrl+C to stop the server.
    echo.
    echo ==========================================
    echo.
    php -S localhost:8080
    echo.
    echo Server stopped.
    goto :end
)

echo [INFO] PHP not found on your system.
echo.
echo Opening game in DEMO MODE (single-player, no server needed)...
echo.
start "" "index.html"

:end
echo.
echo ==========================================
pause
