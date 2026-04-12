@echo off
setlocal

cd /d "%~dp0"

echo ========================================
echo BrainRent - Local Runner
echo ========================================
echo.

set "PHP_EXE=C:\xampp\php\php.exe"
if not exist "%PHP_EXE%" set "PHP_EXE=php"

echo [1/3] Trying to start MySQL service (if available)...
net start MySQL80 >nul 2>&1

echo [2/3] Ensuring database schema (safe mode, keeps data)...
"%PHP_EXE%" database\setup_database.php
if errorlevel 1 (
    echo.
    echo Setup failed.
    echo If your database is corrupted and you want a full rebuild, run:
    echo   "%PHP_EXE%" database\setup_database.php --reset
    echo.
    pause
    exit /b 1
)

echo [3/3] Starting local PHP server...
echo Open: http://localhost:8000/pages/index.php
echo Press Ctrl+C to stop server.
echo.
"%PHP_EXE%" -S localhost:8000 -t .
