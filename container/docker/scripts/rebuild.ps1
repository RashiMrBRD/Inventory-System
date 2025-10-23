# PowerShell script to rebuild and restart Docker containers

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Rebuilding Inventory Management System" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# Get the script directory (container/docker/scripts folder)
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$DockerDir = Split-Path -Parent $ScriptDir
$ContainerDir = Split-Path -Parent $DockerDir
$RootDir = Split-Path -Parent $ContainerDir

# Change to root directory
Set-Location $RootDir

Write-Host "Stopping existing containers..." -ForegroundColor Yellow
docker-compose -f container\docker-compose.yml down

Write-Host ""
Write-Host "Rebuilding Docker images..." -ForegroundColor Green
docker-compose -f container\docker-compose.yml build --no-cache

Write-Host ""
Write-Host "Starting containers..." -ForegroundColor Green
docker-compose -f container\docker-compose.yml up -d

Write-Host ""
Write-Host "Waiting for services to be ready..." -ForegroundColor Yellow
Start-Sleep -Seconds 10

Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Service Status:" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
docker-compose -f container\docker-compose.yml ps

Write-Host ""
Write-Host "==========================================" -ForegroundColor Green
Write-Host "Rebuild complete! Services are running!" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Green
Write-Host "Web Application:     http://localhost:8080" -ForegroundColor White
Write-Host "MongoDB Express:     http://localhost:8081" -ForegroundColor White
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Read-Host "Press Enter to continue"
