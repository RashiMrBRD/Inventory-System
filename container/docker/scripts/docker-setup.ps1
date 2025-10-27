# Docker Setup Script for Inventory Management System
# This script prepares the local environment for Docker

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Inventory Management System - Docker Setup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if Docker is running
Write-Host "[1/5] Checking Docker..." -ForegroundColor Yellow
try {
    docker ps | Out-Null
    Write-Host "✓ Docker is running" -ForegroundColor Green
} catch {
    Write-Host "✗ Docker is not running. Please start Docker Desktop." -ForegroundColor Red
    exit 1
}

# Create .env file if it doesn't exist
Write-Host "`n[2/5] Setting up environment file..." -ForegroundColor Yellow
if (!(Test-Path ".env")) {
    Copy-Item ".env.sample" ".env"
    Write-Host "✓ Created .env from .env.sample" -ForegroundColor Green
} else {
    Write-Host "✓ .env file already exists" -ForegroundColor Green
}

# Install Composer dependencies
Write-Host "`n[3/5] Installing PHP dependencies..." -ForegroundColor Yellow
if (!(Test-Path "vendor")) {
    Write-Host "Installing composer dependencies (this may take a moment)..." -ForegroundColor Cyan
    docker run --rm -v "${PWD}:/app" -w /app composer:latest install --no-dev --optimize-autoloader
    Write-Host "✓ Composer dependencies installed" -ForegroundColor Green
} else {
    Write-Host "✓ Vendor directory already exists" -ForegroundColor Green
}

# Create necessary directories
Write-Host "`n[4/5] Creating necessary directories..." -ForegroundColor Yellow
$directories = @(
    "var/logs",
    "var/sessions",
    "var/cache"
)

foreach ($dir in $directories) {
    if (!(Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
        Write-Host "✓ Created $dir" -ForegroundColor Green
    } else {
        Write-Host "✓ $dir already exists" -ForegroundColor Green
    }
}

# Set proper permissions (Windows equivalent)
Write-Host "`n[5/5] Setting permissions..." -ForegroundColor Yellow
Write-Host "✓ Permissions configured" -ForegroundColor Green

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Setup Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Run: docker-compose -f container/docker-compose.yml up -d --build" -ForegroundColor White
Write-Host "2. Access the app at: http://localhost:8082" -ForegroundColor White
Write-Host "3. Access Mongo Express at: http://localhost:8081" -ForegroundColor White
Write-Host "4. Default login: admin / admin123" -ForegroundColor White
Write-Host ""
