# PowerShell Launcher for FlagShip Feature Flag System
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$phpPath = "php"
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    if (Test-Path "C:\xampp\php\php.exe") {
        $phpPath = "C:\xampp\php\php.exe"
    } else {
        Write-Error "Không tìm thấy php.exe trong hệ thống."
        exit 1
    }
}

Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "  FlagShip - Feature Flag Management System" -ForegroundColor Green
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "[*] Máy chủ đang chạy tại: http://localhost:8000" -ForegroundColor Yellow
Write-Host "[*] Bấm Ctrl+C để dừng máy chủ" -ForegroundColor Gray

Start-Process "http://localhost:8000"
& $phpPath -S localhost:8000 router.php
