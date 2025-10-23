#!/bin/bash
# View logs from Docker containers

# If service name is provided, show logs for that service only
if [ ! -z "$1" ]; then
    echo "Showing logs for $1..."
    docker-compose logs -f "$1"
else
    echo "Showing logs for all services..."
    docker-compose logs -f
fi
