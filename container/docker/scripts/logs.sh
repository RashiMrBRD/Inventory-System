#!/bin/bash
# View logs from Docker containers

# Navigate to project root
cd "$(dirname "$0")/../../.."

# If service name is provided, show logs for that service only
if [ ! -z "$1" ]; then
    echo "Showing logs for $1..."
    docker-compose -f container/docker-compose.yml logs -f "$1"
else
    echo "Showing logs for all services..."
    docker-compose -f container/docker-compose.yml logs -f
fi
