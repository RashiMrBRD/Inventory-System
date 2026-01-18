# Docker Debugging Guide

## Error 500 on Login - Troubleshooting Steps

If you're experiencing HTTP 500 errors when trying to login, follow these steps:

### 1. Check Container Status

```bash
docker-compose -f container/docker-compose.yml ps
```

All services should show "Up" status.

### 2. View PHP Error Logs

```bash
# View live logs from web container
docker-compose -f container/docker-compose.yml logs -f web

# Or check the PHP error log file
docker exec inventory_web tail -f /var/www/html/var/logs/php_errors.log
```

### 3. Check Directory Permissions

```bash
# Check if directories exist and have correct permissions
docker exec inventory_web ls -la /var/www/html/var/

# Should show:
# drwxrwxr-x www-data www-data logs
# drwxrwxr-x www-data www-data sessions
# drwxr-xr-x www-data www-data cache
```

### 4. Verify Vendor Directory

```bash
# Check if vendor directory is properly mounted
docker exec inventory_web ls -la /var/www/html/vendor/

# Should show autoload.php and various packages
```

### 5. Test MongoDB Connection

```bash
# Test MongoDB connection from web container
docker exec inventory_web php -r "
try {
    \$client = new MongoDB\Client('mongodb://mongodb:27017');
    \$databases = \$client->listDatabases();
    echo 'MongoDB connection: SUCCESS\n';
} catch (Exception \$e) {
    echo 'MongoDB connection: FAILED - ' . \$e->getMessage() . '\n';
}
"
```

### 6. Check Session Directory

```bash
# Verify session directory is writable
docker exec inventory_web touch /var/www/html/var/sessions/test.txt
docker exec inventory_web rm /var/www/html/var/sessions/test.txt

# If this fails, session directory is not writable
```

### 7. Restart with Fresh Build

```bash
# Stop containers
docker-compose -f container/docker-compose.yml down

# Remove old images
docker rmi inventory_demo-web

# Rebuild from scratch
docker-compose -f container/docker-compose.yml build --no-cache

# Start containers
docker-compose -f container/docker-compose.yml up -d

# Watch logs
docker-compose -f container/docker-compose.yml logs -f web
```

### 8. Check Environment Variables

```bash
# Verify .env is loaded correctly
docker exec inventory_web env | grep MONGODB

# Should show:
# MONGODB_HOST=mongodb
# MONGODB_PORT=27017
# MONGODB_DATABASE=inventory_system
```

### 9. Test PHP Configuration

```bash
# Check PHP version
docker exec inventory_web php -v

# Should show: PHP 8.5.x

# Check loaded extensions
docker exec inventory_web php -m | grep mongodb

# Should show: mongodb
```

### 10. Manual Login Test

```bash
# Access the container shell
docker exec -it inventory_web bash

# Inside container, test autoloader
php -r "require '/var/www/html/vendor/autoload.php'; echo 'Autoloader OK\n';"

# Test database connection
cd /var/www/html
php -r "
require 'vendor/autoload.php';
require 'config/database.php';
echo 'Database config loaded\n';
"

# Exit container
exit
```

## Common Issues & Solutions

### Issue: "Failed to open stream" errors
**Cause:** Vendor directory not mounted or composer dependencies not installed
**Solution:**
```bash
# On host machine
composer install
# Then restart containers
docker-compose -f container/docker-compose.yml restart web
```

### Issue: "Permission denied" for sessions
**Cause:** Session directory not writable
**Solution:**
```bash
# On host machine
chmod -R 775 var/sessions var/logs
# Or run setup script
./setup.sh
```

### Issue: "Connection refused" to MongoDB
**Cause:** MongoDB container not ready or .env not loaded
**Solution:**
```bash
# Check if .env exists
ls -la .env

# Check MongoDB status
docker-compose -f container/docker-compose.yml logs mongodb

# Wait for MongoDB to be healthy
docker-compose -f container/docker-compose.yml ps
```

### Issue: "Class not found" errors
**Cause:** Autoloader not working
**Solution:**
```bash
# Ensure vendor directory exists
ls -la vendor/autoload.php

# Rebuild with vendor
docker-compose -f container/docker-compose.yml down
docker-compose -f container/docker-compose.yml build --no-cache
docker-compose -f container/docker-compose.yml up -d
```

## Quick Fix Commands

```bash
# Complete reset
docker-compose -f container/docker-compose.yml down -v
rm -rf var/logs/* var/sessions/*
./setup.sh
docker-compose -f container/docker-compose.yml up -d --build

# Fix permissions only
./setup.sh
docker-compose -f container/docker-compose.yml restart web

# View all logs
docker-compose -f container/docker-compose.yml logs --tail=100
```

## Getting Help

If issues persist after following this guide:

1. Collect logs:
```bash
docker-compose -f container/docker-compose.yml logs > docker-logs.txt
```

2. Check system resources:
```bash
docker stats
```

3. Verify Docker version:
```bash
docker --version
docker-compose --version
```

4. Check for port conflicts:
```bash
# On Linux/Mac
sudo netstat -tulpn | grep -E '8082|27017|8081'

# On Windows
netstat -ano | findstr "8082 27017 8081"
```
