# Inventory Management System
![status](https://img.shields.io/badge/status-alpha-yellow) ![php](https://img.shields.io/badge/PHP-8.2-blue) ![db](https://img.shields.io/badge/MongoDB-7.x-green) ![server](https://img.shields.io/badge/Apache-2.4-lightgrey) ![docker](https://img.shields.io/badge/Docker%20Compose-3.8-2496ED)

A modern, secure inventory management system built with PHP 8.2, Apache 2.4, MongoDB 7, and Docker. It features a RESTful API architecture designed for production deployment. The application provides a comprehensive solution for tracking inventory items while maintaining security best practices and follows industry-standard code organization patterns.

## 📋 Table of Contents
- [Features](#features)
- [System Requirements](#system-requirements)
- [Quick Start (Docker)](#quick-start-docker)
- [Architecture Overview](#architecture-overview)
- [Installation Guide](#installation-guide)
- [API Documentation](#api-documentation)
- [Deployment to Production](#deployment-to-production)
- [Security Features](#security-features)
- [Project Status](#project-status)
- [Known Issues](#known-issues)
- [Recent Fixes & Changes](#recent-fixes--changes)
- [Troubleshooting](#troubleshooting)

## ✨ Features

### Core Functionality
- ✅ **User Authentication** - Secure login/logout system with session management
- ✅ **Inventory Management** - Complete CRUD operations for inventory items
- ✅ **Low Stock Alerts** - Automatic highlighting of items with quantity ≤ 5
- ✅ **Barcode Support** - Track items using unique barcode identifiers
- ✅ **Search Functionality** - Find items by name, barcode, or type
- ✅ **Role-based Access Control** - Different access levels for users
- ✅ **RESTful API** - Modern API endpoints for third-party integrations
- ✅ **Responsive Design** - Works seamlessly on desktop, tablet, and mobile devices

### Technical Features
- ✅ **MVC Architecture** - Clean separation of concerns using Model-View-Controller pattern
- ✅ **PSR-4 Autoloading** - Standard PHP autoloading for efficient class loading
- ✅ **MongoDB Integration** - NoSQL database for flexible data storage
- ✅ **Security Hardening** - Multiple layers of security protection
- ✅ **CORS Support** - Cross-origin resource sharing for API access
- ✅ **Environment Configuration** - Separate configurations for development and production

## 💻 System Requirements

### For Windows 11 (Development & Production)
- **PHP** 7.4 or higher (8.0+ recommended)
- **MongoDB** 4.4 or higher
- **Web Server** Apache (via XAMPP) or Nginx
- **Composer** Latest version for dependency management
- **MongoDB PHP Extension** Required for database connectivity

### Recommended Specifications
- **RAM:** 4GB minimum, 8GB recommended
- **Storage:** 500MB free space
- **Network:** Stable internet connection for deployment

## 🚀 Quick Start (Docker)

The fastest way to run the app locally is with Docker.

Requirements: Docker Desktop + Docker Compose

```bash
# 1) From project root, create environment file
cp container/.env.docker .env
# Windows PowerShell:
# copy container\.env.docker .env

# 2) Start the stack (web + mongodb + mongo-express)
docker-compose -f container/docker-compose.yml up -d --build

# 3) Open the app
# Web:            http://localhost:8082
# Mongo Express:  http://localhost:8081

# Default login (change in production)
# Username: admin
# Password: admin123

# 4) Stop
docker-compose -f container/docker-compose.yml down

# Logs (follow)
docker-compose -f container/docker-compose.yml logs -f web
```

Notes:
- Web container maps to host port `8082` by default (see `container/docker-compose.yml`).
- App connects to MongoDB via internal host `mongodb:27017` (no public DB exposure).
- If using Cloudflare Tunnel, route `demo.rashlink.eu.org` to port `8082`.

## 🏗️ Architecture Overview

### Directory Structure
The application follows PHP best practices with a well-organized structure that separates concerns and enhances maintainability:

```
inventory_demo/
├── api/                    # RESTful API endpoints
│   └── v1/                # API version 1
│       ├── auth.php       # Authentication endpoints
│       ├── inventory.php  # Inventory endpoints
│       └── index.php      # API documentation
├── config/                # Configuration files
│   ├── app.php           # Application settings
│   └── database.php      # Database configuration
├── docs/                  # Documentation (READ THESE FIRST!)
│   ├── README.md         # Documentation guide
│   ├── QUICKSTART.md     # 5-minute setup guide
│   ├── INSTALLATION_VERIFICATION.md  # Verify setup
│   ├── DEPLOYMENT_CHECKLIST.md       # Production deployment
│   ├── PROJECT_STRUCTURE.md          # Architecture guide
│   └── REORGANIZATION_SUMMARY.md     # What changed
├── features/              # Feature documentation
│   └── README.md         # Features overview
├── legacy/               # Archived old files (not used)
│   └── README.md         # Legacy files info
├── public/               # Publicly accessible files (Document Root)
│   ├── css/             # Stylesheets
│   │   ├── style.css    # Main page styles
│   │   ├── login.css    # Login page styles
│   │   └── form.css     # Form page styles
│   ├── index.php        # Main inventory page
│   ├── login.php        # Login page
│   ├── logout.php       # Logout handler
│   ├── add_item.php     # Add item page
│   ├── edit_item.php    # Edit item page
│   ├── delete_item.php  # Delete handler
│   └── .htaccess        # Public security config
├── scripts/              # Setup and utility scripts
│   ├── README.md        # Scripts documentation
│   ├── setup_mongodb.php  # Database initialization
│   └── database.sql     # Legacy SQL schema (reference)
├── src/                 # PHP source code (PSR-4)
│   ├── Controller/      # Business logic controllers
│   │   ├── AuthController.php
│   │   └── InventoryController.php
│   ├── Model/          # Data models
│   │   ├── User.php
│   │   └── Inventory.php
│   └── Service/        # Application services
│       └── DatabaseService.php
├── var/                # Temporary files
│   ├── logs/          # Application logs
│   └── cache/         # Cache files
├── vendor/            # Composer dependencies (auto-generated)
├── .env.example       # Environment template
├── .gitignore        # Git ignore rules
├── .htaccess         # Root security config
├── composer.json     # Dependency management
└── README.md         # This file
```

### Key Design Patterns
- **Singleton Pattern** - Used in DatabaseService for single database connection
- **MVC Pattern** - Separation of data, business logic, and presentation
- **Front Controller** - Single entry point through public/index.php
- **Repository Pattern** - Models handle all database operations

## 📦 Installation Guide

### Step 1: Install Prerequisites (Windows 11)

#### 1.1 Install XAMPP
XAMPP provides Apache web server and PHP in one package, which makes it ideal for running this application on Windows 11.

1. Download XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Run the installer and follow the setup wizard
3. Install to `C:\xampp` (default location)
4. Start Apache from XAMPP Control Panel

#### 1.2 Install MongoDB
MongoDB is the database system that stores all your inventory and user data.

1. Download MongoDB Community Server from [https://www.mongodb.com/try/download/community](https://www.mongodb.com/try/download/community)
2. Run the installer and select "Complete" installation
3. Install MongoDB as a Windows Service (check the option during installation)
4. Default port is 27017 - note this for configuration
5. Verify MongoDB is running by opening Command Prompt and typing:
   ```bash
   mongo --version
   ```

#### 1.3 Install MongoDB PHP Extension
This extension allows PHP to communicate with MongoDB.

1. Download the appropriate DLL file from [https://pecl.php.net/package/mongodb](https://pecl.php.net/package/mongodb)
   - Choose the version that matches your PHP version and architecture (x64 or x86)
   - Download the "Thread Safe" (TS) version if using XAMPP
2. Extract the `php_mongodb.dll` file
3. Copy it to `C:\xampp\php\ext\`
4. Edit `C:\xampp\php\php.ini` and add this line:
   ```ini
   extension=mongodb
   ```
5. Restart Apache from XAMPP Control Panel

#### 1.4 Install Composer
Composer manages PHP dependencies and autoloading.

1. Download Composer from [https://getcomposer.org/download/](https://getcomposer.org/download/)
2. Run the installer (it will detect your PHP installation automatically)
3. Complete the installation
4. Verify by opening Command Prompt and typing:
   ```bash
   composer --version
   ```

### Step 2: Deploy Application Files

1. **Clone or copy the project** to your XAMPP directory:
   ```bash
   cd C:\xampp\htdocs
   # Copy the inventory_demo folder here
   ```

2. **Navigate to the project directory:**
   ```bash
   cd C:\xampp\htdocs\inventory_demo
   ```

3. **Install dependencies** using Composer:
   ```bash
   composer install
   ```
   This command will download all required packages and set up autoloading.

4. **Set up environment configuration:**
   ```bash
   copy .env.example .env
   ```
   Edit the `.env` file if you need to customize MongoDB connection settings.

### Step 3: Configure Web Server

#### For Local Development (XAMPP)
The application is designed to work directly under XAMPP. After installation:

1. Ensure XAMPP Apache is running
2. Access the application at: `http://localhost/inventory_demo/public/`

#### For Production Deployment
Configure your Apache VirtualHost to point to the `public` directory:

```apache
<VirtualHost *:80>
    ServerName demo.rashlink.eu.org
    DocumentRoot "C:/xampp/htdocs/inventory_demo/public"
    
    <Directory "C:/xampp/htdocs/inventory_demo/public">
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog "logs/inventory_demo-error.log"
    CustomLog "logs/inventory_demo-access.log" combined
</VirtualHost>
```

### Step 4: Initialize Database

1. **Open your web browser** and navigate to:
   ```
   http://localhost/inventory_demo/scripts/setup_mongodb.php
   ```

2. This script will automatically:
   - Create the necessary MongoDB collections
   - Insert sample data including a default admin user
   - Display a success message when complete

3. **Verify the setup** was successful - you should see:
   ```
   MongoDB setup completed successfully!
   ```

**Note:** The setup script is located in the `scripts/` folder for better organization.

### Step 5: Access the Application

1. **Open your browser** and go to:
   ```
   http://localhost/inventory_demo/public/login.php
   ```

2. **Login with default credentials:**
   - **Username:** admin
   - **Password:** admin123

3. You will be redirected to the main inventory dashboard where you can manage items.

## 🔌 API Documentation

The application includes a comprehensive RESTful API that can be accessed at `/api/v1/`. This API allows external applications to interact with your inventory system.

### Base URL
- **Development:** `http://localhost/inventory_demo/api/v1/`
- **Production:** `https://demo.rashlink.eu.org/api/v1/`

### Authentication Endpoints

#### POST /api/v1/auth.php?action=login
Authenticate a user and create a session.

**Request Body:**
```json
{
  "username": "admin",
  "password": "admin123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "user": {
    "id": "507f1f77bcf86cd799439011",
    "username": "admin",
    "full_name": "Administrator",
    "access_level": "admin"
  }
}
```

#### POST /api/v1/auth.php?action=logout
Logout the current user.

**Response:**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

### Inventory Endpoints

All inventory endpoints require authentication. You must be logged in before accessing these APIs.

#### GET /api/v1/inventory.php
Get all inventory items.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "_id": "507f1f77bcf86cd799439011",
      "barcode": "123456789",
      "name": "Laptop Dell XPS",
      "type": "Electronics",
      "lifespan": "5 years",
      "quantity": 10,
      "date_added": "2025-01-15"
    }
  ],
  "count": 1
}
```

#### GET /api/v1/inventory.php?id={item_id}
Get a specific item by ID.

#### GET /api/v1/inventory.php?search={query}
Search inventory items by name, barcode, or type.

#### GET /api/v1/inventory.php?low_stock=true&threshold=5
Get items with low stock (quantity ≤ threshold).

#### POST /api/v1/inventory.php
Create a new inventory item.

**Request Body:**
```json
{
  "barcode": "987654321",
  "name": "iPhone 15",
  "type": "Electronics",
  "lifespan": "3 years",
  "quantity": 25
}
```

#### PUT /api/v1/inventory.php?id={item_id}
Update an existing item.

**Request Body:**
```json
{
  "quantity": 30
}
```

#### DELETE /api/v1/inventory.php?id={item_id}
Delete an item from inventory.

## 🚀 Deployment to Production (demo.rashlink.eu.org)

This section guides you through deploying the application to your production domain on Windows 11.

### Prerequisites for Production
- Domain name configured (demo.rashlink.eu.org)
- SSL certificate (recommended via Let's Encrypt or Cloudflare)
- MongoDB accessible from production server
- Apache web server configured

### Step 1: Prepare Production Environment

1. **Update .env file** for production:
   ```bash
   APP_ENV=production
   APP_DEBUG=false
   APP_DOMAIN=demo.rashlink.eu.org
   MONGODB_HOST=localhost
   MONGODB_PORT=27017
   MONGODB_DATABASE=inventory_system
   ```

2. **Configure Apache VirtualHost** (in `httpd-vhosts.conf`):
   ```apache
   <VirtualHost *:80>
       ServerName demo.rashlink.eu.org
       DocumentRoot "C:/path/to/inventory_demo/public"
       
       <Directory "C:/path/to/inventory_demo/public">
           Options -Indexes +FollowSymLinks
           AllowOverride All
           Require all granted
       </Directory>
       
       # Redirect to HTTPS (if SSL is configured)
       RewriteEngine On
       RewriteCond %{HTTPS} off
       RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   </VirtualHost>
   
   <VirtualHost *:443>
       ServerName demo.rashlink.eu.org
       DocumentRoot "C:/path/to/inventory_demo/public"
       
       SSLEngine on
       SSLCertificateFile "path/to/certificate.crt"
       SSLCertificateKeyFile "path/to/private.key"
       
       <Directory "C:/path/to/inventory_demo/public">
           Options -Indexes +FollowSymLinks
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

3. **Optimize Composer** for production:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

### Step 2: Security Hardening

1. **Ensure proper file permissions:**
   - Set read-only permissions for PHP files
   - Ensure `var/logs` directory is writable
   - Protect `.env` file from public access

2. **Enable all security headers** (already configured in `.htaccess`)

3. **Change default admin password** immediately after deployment

4. **Configure MongoDB authentication** (if not already done):
   ```javascript
   // In MongoDB shell
   use admin
   db.createUser({
     user: "inventory_admin",
     pwd: "strong_password_here",
     roles: [{role: "readWrite", db: "inventory_system"}]
   })
   ```

### Step 3: DNS Configuration

Point your domain to your server:
1. Add an A record in your DNS settings:
   ```
   Type: A
   Name: demo
   Value: [Your Server IP]
   TTL: 3600
   ```

2. Wait for DNS propagation (can take up to 24 hours)

3. Verify by pinging your domain:
   ```bash
   ping demo.rashlink.eu.org
   ```

## 🔒 Security Features

This application implements multiple layers of security to protect your data:

### Application-Level Security
- **Password Hashing** - All passwords are hashed using PHP's password_hash()
- **Session Management** - Secure session handling with timeout protection
- **Input Validation** - All user inputs are validated and sanitized
- **SQL Injection Protection** - MongoDB's parameterized queries prevent injection
- **XSS Protection** - All outputs are escaped using htmlspecialchars()
- **CSRF Protection** - Form submissions are protected

### Server-Level Security
- **Directory Browsing Disabled** - Prevents listing of directory contents
- **Sensitive File Protection** - .env, composer.json, and config files are protected
- **Security Headers** - X-Frame-Options, X-XSS-Protection, X-Content-Type-Options
- **HTTPS Enforcement** - Redirects all HTTP traffic to HTTPS in production
- **File Upload Restrictions** - Only allowed file types can be uploaded

### Database Security
- **MongoDB Authentication** - Connection requires username and password
- **Connection Encryption** - SSL/TLS support for database connections
- **Access Control** - Role-based permissions in MongoDB

## 🧭 Project Status

- **Version:** v0.1.x (Alpha)
- **Scope:** Core inventory, authentication, REST API, Dockerized dev/prod.
- **Breaking changes:** Possible during alpha; pin versions when deploying.

## 🐞 Known Issues

- If login fails in Docker with HTTP 500, ensure `.env` credentials match the app user and that MongoDB has the default admin. Check `web` logs.
- When running behind a proxy (e.g., Cloudflare Tunnel), ensure the tunnel routes to port `8082` and cookies are preserved. Apache is configured to trust proxy ranges.
- Debian trixie image changed package names; see fixes below if you fork older Dockerfiles.

## 🧰 Recent Fixes & Changes

- Docker (Debian trixie) packages: switched to `libzip5`, `libssl3t64`, `libcurl4t64` in `container/Dockerfile`.
- MongoDB PHP extension: install latest `pecl mongodb` (2.x) to satisfy `mongodb/mongodb ^2.1`.
- Apache config: set global `ServerName` and enable `remoteip`; trust subnets `172.24.0.0/16`, `192.168.123.0/24`, `192.168.100.0/24`.
- Docker network name: standardized to `inventory` in `container/docker-compose.yml`.
- App DB connection: force `authSource` to the app database in `src/Service/DatabaseService.php`.
- Cloudflare Tunnel: documented safe exposure of web and Mongo Express only (no direct DB exposure).
- Ports: web service exposed on `8082` by default; Mongo Express on `8081`.

> These changes address build errors, proxy headers, and the login 500 issue seen only in Docker.

## 🔧 Troubleshooting

### Common Issues and Solutions

#### Issue: "Class 'MongoDB\Client' not found"
**Cause:** MongoDB PHP extension is not installed or not enabled.

**Solution:**
1. Verify the extension is installed: Check if `php_mongodb.dll` exists in `C:\xampp\php\ext\`
2. Enable it in php.ini: Add `extension=mongodb` if missing
3. Restart Apache from XAMPP Control Panel
4. Verify: Run `php -m | findstr mongodb` in Command Prompt

#### Issue: "Connection refused" or "Failed to connect to MongoDB"
**Cause:** MongoDB service is not running.

**Solution:**
1. Open Windows Services (press Win+R, type `services.msc`)
2. Find "MongoDB" in the list
3. Right-click and select "Start"
4. Set startup type to "Automatic" for future starts
5. Verify: Run `mongo` in Command Prompt

#### Issue: "404 Not Found" when accessing the application
**Cause:** Web server document root is not correctly configured.

**Solution:**
1. Verify Apache is running in XAMPP Control Panel
2. Check that files are in `C:\xampp\htdocs\inventory_demo\`
3. Access via: `http://localhost/inventory_demo/public/`
4. Check Apache error logs: `C:\xampp\apache\logs\error.log`

#### Issue: "Permission denied" errors
**Cause:** File permissions are too restrictive.

**Solution:**
1. Ensure the `var/logs` directory is writable
2. Right-click the directory → Properties → Security
3. Grant "Full Control" to the Apache user (usually SYSTEM or your user account)

#### Issue: Login page shows but login doesn't work
**Cause:** Database is not initialized or user data is missing.

**Solution:**
1. Run the setup script: `http://localhost/inventory_demo/setup_mongodb.php`
2. Verify MongoDB is running and accessible
3. Check that the `users` collection exists and contains the admin user

#### Issue: CSS/Styles are not loading
**Cause:** Path to CSS files is incorrect or .htaccess is not working.

**Solution:**
1. Verify mod_rewrite is enabled in Apache configuration
2. Check that `.htaccess` files are present in root and public directories
3. Clear browser cache (Ctrl+F5)
4. Check browser console for errors (F12)

#### Issue: API returns "Unauthorized" error
**Cause:** User is not logged in or session has expired.

**Solution:**
1. Login first through the web interface or API
2. Ensure cookies are enabled in your API client
3. Check session timeout settings in `config/app.php`

### Getting Help

If you encounter issues not covered here:
1. Check Apache error logs: `C:\xampp\apache\logs\error.log`
2. Check PHP error logs: `var/logs/php_errors.log` (if configured)
3. Enable debug mode temporarily (set `APP_DEBUG=true` in `.env`)
4. Review MongoDB logs in `C:\Program Files\MongoDB\Server\[version]\log\`

## 📝 License

This project is licensed under the MIT License, which means you are free to use, modify, and distribute it for both commercial and non-commercial purposes.

## 🎯 Best Practices for Production

1. **Regular Backups** - Schedule daily backups of your MongoDB database
2. **Monitor Logs** - Regularly check error logs for issues
3. **Update Dependencies** - Keep composer packages updated: `composer update`
4. **Security Updates** - Apply PHP and MongoDB security patches promptly
5. **Performance Monitoring** - Monitor server resources and application performance
6. **Access Control** - Use strong passwords and change them regularly
7. **SSL Certificate** - Always use HTTPS in production environments

## 🤝 Contributing

Contributions are welcome! If you find bugs or have suggestions for improvements, please create an issue or submit a pull request.

---

**Developed with ❤️ for modern inventory management**
