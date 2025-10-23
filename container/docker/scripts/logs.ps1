# PowerShell script to view logs from Docker containers

param(
    [string]$ServiceName = ""
)

# Get the script directory (container/docker/scripts folder)
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$DockerDir = Split-Path -Parent $ScriptDir
$ContainerDir = Split-Path -Parent $DockerDir
$RootDir = Split-Path -Parent $ContainerDir

# Change to root directory
Set-Location $RootDir

# Show logs for specific service or all services
if ($ServiceName -ne "") {
    Write-Host "Showing logs for $ServiceName..." -ForegroundColor Cyan
    docker-compose -f container\docker-compose.yml logs -f $ServiceName
} else {
    Write-Host "Showing logs for all services..." -ForegroundColor Cyan
    Write-Host "Press Ctrl+C to exit" -ForegroundColor Yellow
    Write-Host ""
    docker-compose -f container\docker-compose.yml logs -f
}
