@echo off
REM Always use PHP 8.4+ (Winget PHP), not XAMPP's PHP 8.2.
set "PHP85=C:\Users\VJ Kyle\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.5_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"

if not exist "%PHP85%" (
  echo PHP 8.5 not found at:
  echo   %PHP85%
  echo Install PHP 8.4+ or update the path in serve-local.bat
  pause
  exit /b 1
)

echo Using:
"%PHP85%" -v
echo.
echo Open http://127.0.0.1:8000
echo.
"%PHP85%" artisan serve --host=127.0.0.1 --port=8000
pause
