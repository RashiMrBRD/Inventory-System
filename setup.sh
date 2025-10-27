#!/bin/bash
# Inventory Management System - Docker Setup Script for Debian 12
# This script sets up the complete Docker environment

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Print colored messages
print_info() {
    echo -e "${CYAN}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_header() {
    echo ""
    echo -e "${CYAN}========================================${NC}"
    echo -e "${CYAN}$1${NC}"
    echo -e "${CYAN}========================================${NC}"
    echo ""
}

# Check if running as root
if [ "$EUID" -eq 0 ]; then
    print_warning "Please do not run this script as root"
    exit 1
fi

print_header "Inventory Management System - Setup"

# Step 1: Check Docker
print_info "[1/6] Checking Docker installation..."
if ! command -v docker &> /dev/null; then
    print_error "Docker is not installed"
    print_info "Install Docker with: sudo apt-get install docker.io docker-compose"
    exit 1
fi

if ! docker info > /dev/null 2>&1; then
    print_error "Docker daemon is not running or you don't have permission"
    print_info "Start Docker: sudo systemctl start docker"
    print_info "Add user to docker group: sudo usermod -aG docker $USER"
    exit 1
fi
print_success "Docker is installed and running"

# Step 2: Check Docker Compose
print_info "[2/6] Checking Docker Compose..."
if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    print_error "Docker Compose is not installed"
    print_info "Install with: sudo apt-get install docker-compose"
    exit 1
fi
print_success "Docker Compose is available"

# Step 3: Check Composer
print_info "[3/6] Checking Composer..."
if ! command -v composer &> /dev/null; then
    print_warning "Composer not found, will use Docker to install dependencies"
    USE_DOCKER_COMPOSER=true
else
    print_success "Composer is installed"
    USE_DOCKER_COMPOSER=false
fi

# Step 4: Create environment file
print_info "[4/6] Setting up environment file..."
if [ ! -f .env ]; then
    if [ -f .env.sample ]; then
        cp .env.sample .env
        print_success "Created .env from .env.sample"
    else
        print_error ".env.sample not found!"
        exit 1
    fi
else
    print_success ".env file already exists"
fi

# Step 5: Install PHP dependencies
print_info "[5/6] Installing PHP dependencies..."
if [ ! -d "vendor" ]; then
    if [ "$USE_DOCKER_COMPOSER" = true ]; then
        print_info "Using Docker to install Composer dependencies..."
        docker run --rm -v "$(pwd):/app" -w /app composer:latest install --no-dev --optimize-autoloader --no-interaction
    else
        print_info "Installing with local Composer..."
        composer install --no-dev --optimize-autoloader --no-interaction
    fi
    print_success "Composer dependencies installed"
else
    print_success "Vendor directory already exists"
fi

# Step 6: Create necessary directories
print_info "[6/6] Creating necessary directories..."
mkdir -p var/logs var/sessions var/cache
chmod 775 var/logs var/sessions
chmod 755 var/cache
print_success "Directories created with proper permissions"

# Display summary
print_header "Setup Complete!"

echo -e "${GREEN}✓${NC} All prerequisites are ready"
echo ""
echo -e "${CYAN}Next steps:${NC}"
echo ""
echo "  1. Review and update .env file if needed:"
echo -e "     ${YELLOW}nano .env${NC}"
echo ""
echo "  2. Start the Docker containers:"
echo -e "     ${YELLOW}docker-compose -f container/docker-compose.yml up -d --build${NC}"
echo ""
echo "  3. Access the application:"
echo -e "     ${GREEN}• Web Interface:${NC}  http://localhost:8082"
echo -e "     ${GREEN}• Mongo Express:${NC}  http://localhost:8081"
echo -e "     ${GREEN}• Default Login:${NC}  admin / admin123"
echo ""
echo "  4. View logs:"
echo -e "     ${YELLOW}docker-compose -f container/docker-compose.yml logs -f web${NC}"
echo ""
echo "  5. Stop containers:"
echo -e "     ${YELLOW}docker-compose -f container/docker-compose.yml down${NC}"
echo ""
print_info "For production deployment, see README.md"
echo ""
