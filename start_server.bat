@echo off
title SIMuatanTahuna - Sistem Informasi Muatan pelabuhan
color 0A

echo ================================================
echo   SIMuatanTahuna - Startup Script
echo ================================================
echo.

:: Check if XAMPP MySQL is running on port 3307
netstat -an | findstr ":3307" > nul
if %errorlevel% neq 0 (
    echo [WARNING] MySQL on port 3307 tidak berjalan!
    echo Jalankan XAMPP Control Panel dan start MySQL
    echo.
    pause
    exit /b 1
)

echo [OK] MySQL sudah berjalan pada port 3307
echo.

:: Laravel 12 requires PHP 8.2+; use the installed PHP 8.3 runtime.
set "PHP_PATH=%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
if not exist "%PHP_PATH%" (
    echo [ERROR] PHP 8.3 tidak ditemukan di:
    echo %PHP_PATH%
    echo Install PHP 8.3 atau perbaiki PHP_PATH pada start_server.bat
    pause
    exit /b 1
)

"%PHP_PATH%" -r "exit(PHP_VERSION_ID >= 80300 ? 0 : 1);"
if errorlevel 1 (
    echo [ERROR] PHP yang dipilih bukan PHP 8.3 atau lebih baru.
    "%PHP_PATH%" -v
    pause
    exit /b 1
)

echo [INFO] Menggunakan PHP dari: %PHP_PATH%
echo.

:: Navigate to project
cd /d "%~dp0backend"

echo ================================================
echo   Starting Backend (Laravel API)
echo ================================================
start "Laravel API + Filament" cmd /k "cd /d %~dp0backend && \"%PHP_PATH%\" artisan serve --host=0.0.0.0 --port=8000"

timeout /t 2 /nobreak > nul

echo ================================================
echo   Flutter Mobile connects to the Laravel API
echo ================================================

echo.
echo ================================================
echo   Sistem berhasil di-start!
echo ================================================
echo.
echo Backend API: http://127.0.0.1:8000
echo Filament:    http://localhost:8000/admin
echo Mobile API:  http://localhost:8000/api
echo.
echo Login Default:
echo   Admin utama (web): admin / password123
echo   Operator (mobile): operator / password123
echo   Operator:  operator / password123
echo   Checker:   checker / password123
echo   Manajer:   manajer / password123
echo   UPP:       upp / password123
echo   Pelanggan: pelanggan / password123
echo.
pause
