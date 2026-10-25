@echo off
chcp 65001 >nul
echo ========================================================
echo   FlagShip - Feature Flag Management System
echo ========================================================
echo.

set PHP_CMD=php
where php >nul 2>nul
if %errorlevel% neq 0 (
    if exist "C:\xampp\php\php.exe" (
        set PHP_CMD="C:\xampp\php\php.exe"
    ) else if exist "C:\laragon\bin\php\php-*\php.exe" (
        set PHP_CMD="C:\laragon\bin\php\php-*\php.exe"
    ) else (
        echo [!] Không tìm thấy PHP trong hệ thống. Vui lòng cài đặt PHP hoặc kiểm tra XAMPP.
        pause
        exit /b 1
    )
)

echo [*] Sử dụng PHP: %PHP_CMD%
echo [*] Khởi động máy chủ tại: http://localhost:8000
echo [*] Đang mở trình duyệt...
echo.

start http://localhost:8000
%PHP_CMD% -S localhost:8000 router.php
