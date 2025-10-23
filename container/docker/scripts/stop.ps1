# PowerShell script to stop Docker containers for Inventory Management System

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Stopping Inventory Management System" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# Get the script directory (container/docker/scripts folder)
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$DockerDir = Split-Path -Parent $ScriptDir
$ContainerDir = Split-Path -Parent $DockerDir
$RootDir = Split-Path -Parent $ContainerDir

# Change to root directory
Set-Location $RootDir

# Stop containers
docker-compose -f container\docker-compose.yml down

Write-Host ""
Write-Host "All containers stopped successfully!" -ForegroundColor Green
Write-Host ""
Write-Host "To start again: .\container\docker\scripts\start.ps1" -ForegroundColor Yellow
Write-Host "To remove volumes: docker-compose -f container\docker-compose.yml down -v" -ForegroundColor Yellow
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Read-Host "Press Enter to continue"
