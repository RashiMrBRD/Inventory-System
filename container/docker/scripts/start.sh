#!/bin/bash
# Start Docker containers for Inventory Management System

set -e

echo "=========================================="
echo "Starting Inventory Management System"
echo "=========================================="

# Check if .env file exists
if [ ! -f .env ]; then
    echo "Creating .env file from .env.docker..."
    cp .env.docker .env
fi

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "Error: Docker is not running. Please start Docker first."
    exit 1
fi

# Build and start containers
echo "Building Docker images..."
docker-compose build

echo "Starting containers..."
docker-compose up -d

# Wait for services to be healthy
echo "Waiting for services to be ready..."
sleep 10

# Check service status
echo ""
echo "=========================================="
echo "Service Status:"
echo "=========================================="
docker-compose ps

echo ""
echo "=========================================="
echo "Services are running!"
echo "=========================================="
echo "Web Application:     http://localhost:8080"
echo "MongoDB Express:     http://localhost:8081"
echo "MongoDB Connection:  mongodb://localhost:27017"
echo "=========================================="
echo ""
echo "To view logs: docker-compose logs -f"
echo "To stop: docker-compose down"
echo "=========================================="
