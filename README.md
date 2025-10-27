# Inventory Management System
![status](https://img.shields.io/badge/status-alpha-yellow) ![php](https://img.shields.io/badge/PHP-8.2-blue) ![db](https://img.shields.io/badge/MongoDB-7.x-green) ![server](https://img.shields.io/badge/Apache-2.4-lightgrey) ![docker](https://img.shields.io/badge/Docker%20Compose-3.8-2496ED) ![license](https://img.shields.io/badge/license-MIT-green)

A modern, secure inventory management system built with PHP 8.2, Apache 2.4, MongoDB 7, and Docker. It features a RESTful API architecture designed for production deployment. The application provides a comprehensive solution for tracking inventory items while maintaining security best practices and follows industry-standard code organization patterns.

## 📋 Table of Contents
- [Features](#features)
- [System Requirements](#system-requirements)
- [Quick Start](#quick-start-docker)
- [Architecture](#architecture-overview)
- [Installation](#installation)
- [API](#api)
- [Deployment](#deployment)
- [Security](#security-features)
- [Project Status](#project-status)
- [Known Issues](#known-issues)
- [Recent Fixes](#recent-fixes)
- [Troubleshooting](#troubleshooting)

## ✨ Features

- User authentication & session management
- Inventory CRUD operations
- Low stock alerts (≤ 5 items)
- Barcode tracking
- Search functionality
- Role-based access control
- RESTful API
- Responsive design
- MVC architecture
- PSR-4 autoloading
- MongoDB integration
- CORS support

## 💻 System Requirements

- PHP 8.2+
- MongoDB 7.0+
- Apache 2.4 or Nginx
- Composer
- Docker & Docker Compose (recommended)

## 🚀 Quick Start (Docker)

```bash
# 1. Create environment file
cp .env.sample .env

# 2. Install PHP dependencies (required for autoloader)
composer install

# 3. Start containers
docker-compose -f container/docker-compose.yml up -d --build

# 4. Access
# Web:            http://localhost:8082
# Mongo Express:  http://localhost:8081
# Login:          admin / admin123

# Stop
docker-compose -f container/docker-compose.yml down

# Alternative: Use PowerShell setup script
# .\container\docker\scripts\docker-setup.ps1
```

## 🏗️ Architecture Overview

### Directory Structure

```
inventory_demo/
├── api/                         # RESTful API endpoints
│   └── v1/                     # API version 1
│       ├── auth.php            # Authentication endpoints
│       ├── inventory.php       # Inventory endpoints
│       └── index.php           # API documentation
├── config/                      # Configuration files
│   ├── app.php                 # Application settings
│   └── database.php            # Database configuration
├── container/                   # Docker containerization
│   ├── docker/                 # Docker config files
│   │   ├── apache/
│   │   │   └── 000-default.conf
│   │   ├── mongodb/
│   │   │   └── init-mongo.js
│   │   ├── php/
│   │   │   └── php.ini
│   │   └── scripts/
│   │       ├── logs.ps1
│   │       ├── rebuild.ps1
│   │       ├── restart.ps1
│   │       ├── start.ps1
│   │       └── stop.ps1
│   ├── docker-compose.yml      # Docker Compose configuration
│   ├── Dockerfile              # Multi-stage build
│   └── .env.sample             # Docker env template
├── legacy/                      # Archived old files (not used in production)
│   ├── config.php
│   ├── index.php
│   └── logout.php
├── public/                      # Publicly accessible files (Document Root)
│   ├── api/                    # Public API endpoints
│   │   ├── delete-attachment.php
│   │   ├── download-attachment.php
│   │   ├── get-audit-trail.php
│   │   ├── get-journal-entry-print.php
│   │   ├── get-notifications.php
│   │   ├── get-recent-entries.php
│   │   ├── notifications.php
│   │   └── upload-attachment.php
│   ├── assets/                 # Static assets
│   │   ├── css/
│   │   │   ├── components.css
│   │   │   ├── core.css
│   │   │   ├── toast.css
│   │   │   └── utilities.css
│   │   └── js/
│   │       └── toast.js
│   ├── components/             # Reusable PHP components
│   │   ├── layout.php
│   │   ├── notifications-dropdown.php
│   │   └── sidebar.php
│   ├── account-form.php
│   ├── add_item.php
│   ├── chart-of-accounts.php
│   ├── dashboard.php
│   ├── delete_item.php
│   ├── edit_item.php
│   ├── export-report-pdf.php
│   ├── financial-reports.php
│   ├── index.php               # Main inventory page
│   ├── init_timezone.php
│   ├── journal-entries.php
│   ├── journal-entry-form.php
│   ├── login.php               # Login page
│   ├── logout.php              # Logout handler
│   ├── notifications.php
│   └── .htaccess               # Public security config
├── scripts/                     # Setup and utility scripts
│   └── setup_mongodb.php       # Database initialization
├── src/                         # PHP source code (PSR-4)
│   ├── Controller/             # Business logic controllers
│   │   ├── AccountController.php
│   │   ├── AuthController.php
│   │   ├── InventoryController.php
│   │   ├── JournalEntryController.php
│   │   └── NotificationController.php
│   ├── Helper/                 # Helper utilities
│   │   ├── CurrencyHelper.php
│   │   └── TimeHelper.php
│   ├── Model/                  # Data models
│   │   ├── Account.php
│   │   ├── Inventory.php
│   │   ├── JournalEntry.php
│   │   ├── Notification.php
│   │   └── User.php
│   └── Service/                # Application services
│       └── DatabaseService.php
├── var/                         # Runtime files
│   ├── logs/                   # Application logs
│   ├── cache/                  # Cache files
│   └── sessions/               # Session files
├── .env.sample                  # Environment template
├── .htaccess                    # Root security config
├── composer.json                # Dependency management
└── README.md                    # This file

# Excluded (see .gitignore): vendor/, features/, docs/, .env files, logs, cache
```

### Design Patterns
- Singleton (DatabaseService)
- MVC (Model-View-Controller)
- Front Controller (public/index.php)
- Repository (Model layer)

## 📦 Installation

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) or Apache + PHP 8.2+
- [MongoDB 7.0+](https://www.mongodb.com/try/download/community)
- [Composer](https://getcomposer.org/download/)
- MongoDB PHP extension: Add `extension=mongodb` to `php.ini`

### Setup

```bash
# Clone repository
git clone <repository-url>
cd inventory_demo

# Install dependencies
composer install

# Configure environment
cp .env.sample .env
# Edit .env with your MongoDB credentials

# Initialize database
# Visit: http://localhost/inventory_demo/scripts/setup_mongodb.php

# Access application
# Visit: http://localhost/inventory_demo/public/login.php
# Login: admin / admin123
```

## 🔌 API

Base URL: `/api/v1/`

### Authentication
- `POST /auth.php?action=login` - Login
- `POST /auth.php?action=logout` - Logout

### Inventory (requires auth)
- `GET /inventory.php` - List all items
- `GET /inventory.php?id={id}` - Get item by ID
- `GET /inventory.php?search={query}` - Search items
- `GET /inventory.php?low_stock=true` - Low stock items
- `POST /inventory.php` - Create item
- `PUT /inventory.php?id={id}` - Update item
- `DELETE /inventory.php?id={id}` - Delete item

## 🚀 Deployment

### Production Setup

```bash
# Update environment
APP_ENV=production
APP_DEBUG=false

# Optimize
composer install --no-dev --optimize-autoloader

# Apache VirtualHost
DocumentRoot "/path/to/inventory_demo/public"

# Security
- Change default admin password
- Configure MongoDB authentication
- Enable HTTPS
- Set proper file permissions
```

## 🔒 Security Features

- Password hashing (bcrypt)
- Session timeout protection
- Input validation & XSS protection
- MongoDB parameterized queries
- CSRF protection
- Security headers (X-Frame-Options, X-XSS-Protection, X-Content-Type-Options)
- HTTPS enforcement
- MongoDB authentication & role-based access

## 🧭 Project Status

**Version:** v0.1.x (Alpha) - Breaking changes possible

## 🐞 Known Issues

- Docker login HTTP 500: Check `.env` credentials match MongoDB user
- Proxy/Cloudflare Tunnel: Route to port `8082`, preserve cookies

## 🧰 Recent Fixes

- Debian trixie packages: `libzip5`, `libssl3t64`, `libcurl4t64`
- MongoDB PHP extension updated to 2.x
- Apache: global `ServerName`, `remoteip` with trusted subnets
- Docker network renamed to `inventory`
- MongoDB connection: `authSource` fix
- Ports: web `8082`, mongo-express `8081`

## 🔧 Troubleshooting

**MongoDB extension not found**
- Add `extension=mongodb` to `php.ini`, restart Apache
- Verify: `php -m | findstr mongodb`

**MongoDB connection refused**
- Start MongoDB service: `services.msc` → MongoDB → Start

**404 Not Found**
- Access via `http://localhost/inventory_demo/public/`
- Check Apache logs: `C:\xampp\apache\logs\error.log`

**Login doesn't work**
- Run setup: `http://localhost/inventory_demo/scripts/setup_mongodb.php`

**CSS not loading**
- Enable mod_rewrite in Apache
- Clear browser cache (Ctrl+F5)

**API Unauthorized**
- Login first, ensure cookies enabled

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guidelines](.github/CONTRIBUTING.md) and [Code of Conduct](.github/CODE_OF_CONDUCT.md).

## 🔐 Security

For security issues, please see our [Security Policy](.github/SECURITY.md).

## 🎯 Production Best Practices

- Regular backups
- Monitor logs
- Update dependencies
- Strong passwords
- Use HTTPS

## 🤝 Contributing

Issues and pull requests welcome.

---

**Developed with ❤️ for modern inventory management**
