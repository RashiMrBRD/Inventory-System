#!/bin/bash
# Stop Docker containers for Inventory Management System

set -e

echo "=========================================="
echo "Stopping Inventory Management System"
echo "=========================================="

docker-compose down

echo ""
echo "All containers stopped successfully!"
echo ""
echo "To start again: ./docker/scripts/start.sh"
echo "To remove volumes: docker-compose down -v"
echo "=========================================="
