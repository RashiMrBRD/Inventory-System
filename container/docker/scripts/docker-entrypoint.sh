#!/bin/bash
# Docker entrypoint script for Inventory Management System
# This script ensures proper permissions and starts Apache

set -e

echo "Starting Inventory Management System..."

# Fix permissions for mounted volumes
echo "Setting up directory permissions..."
chown -R www-data:www-data /var/www/html/var/logs /var/www/html/var/sessions /var/www/html/var/cache 2>/dev/null || true
chmod -R 775 /var/www/html/var/logs /var/www/html/var/sessions 2>/dev/null || true
chmod -R 755 /var/www/html/var/cache 2>/dev/null || true

# Ensure session directory is writable
if [ -d "/var/www/html/var/sessions" ]; then
    echo "Session directory ready: /var/www/html/var/sessions"
else
    echo "Warning: Session directory not found!"
    mkdir -p /var/www/html/var/sessions
    chown www-data:www-data /var/www/html/var/sessions
    chmod 775 /var/www/html/var/sessions
fi

# Ensure logs directory is writable
if [ -d "/var/www/html/var/logs" ]; then
    echo "Logs directory ready: /var/www/html/var/logs"
else
    echo "Warning: Logs directory not found!"
    mkdir -p /var/www/html/var/logs
    chown www-data:www-data /var/www/html/var/logs
    chmod 775 /var/www/html/var/logs
fi

# Check if vendor directory exists
if [ ! -d "/var/www/html/vendor" ]; then
    echo "Warning: vendor directory not found! Run composer install on the host."
fi

echo "All checks passed. Starting Apache..."

# Execute the main container command
exec "$@"
