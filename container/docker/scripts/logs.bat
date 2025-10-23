@echo off
REM View logs from Docker containers
REM This script has been moved. Please use the PowerShell version instead.

echo ==========================================
echo NOTICE: This script has been reorganized
echo ==========================================
echo.
echo Please use the new PowerShell script instead:
echo     .\container\logs.ps1 [service-name]
echo.
echo Or use docker-compose directly from root:
echo     docker-compose -f container\docker-compose.yml logs -f
echo.
echo ==========================================
pause
