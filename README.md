# Inventory Management System
![status](https://img.shields.io/badge/status-alpha-yellow) ![php](https://img.shields.io/badge/PHP-8.3-blue) ![db](https://img.shields.io/badge/MongoDB-7.x-green) ![server](https://img.shields.io/badge/Apache-2.4-lightgrey) ![docker](https://img.shields.io/badge/Docker%20Compose-3.8-2496ED) ![license](https://img.shields.io/badge/license-MIT-green)

This system is a modern and secure way for businesses to effortlessly track their inventory, designed to be simple yet powerful so anyone can use it without hassle. While inventory management can often feel complex, this solution keeps things straightforward by focusing on security and reliability, ensuring that business data stays protected at all times. It is built to grow with your needs, whether you are a small shop or a larger operation, and it connects easily with other tools you might already use. The system follows industry-standard organization, so it feels familiar and intuitive, meaning you will not waste time figuring out how it works because you can start managing your inventory right away.

## What This System Does

Managing inventory is something that every business needs, but there is often a gap between simple spreadsheets and complex enterprise software. This system fills that gap by providing a complete inventory management solution built on modern technology. The idea behind this project is to give businesses a professional tool they can trust without requiring extensive training or setup.

The system handles everything from basic item tracking to advanced features like barcode scanning, automated low stock alerts, and role-based access control. Because it is built with security in mind from the ground up, you can be confident that your business data stays protected. There is also a full RESTful API included, which means that you can integrate this system with other tools your business uses, from accounting software to e-commerce platforms.

## Key Features That Make This System Powerful

The system includes comprehensive user authentication and session management, which means that multiple team members can access the system securely with different permission levels. Every inventory operation is supported, from creating new items to updating existing ones, searching through your catalog, and removing items that are no longer needed.

One of the most practical features is the automated low stock alert system. When any item reaches 5 units or fewer, the system notifies you automatically so that you can reorder before running out completely. There is also built-in barcode tracking support, making it easy to scan items in and out of your inventory quickly.

The search functionality is fast and intuitive because it is designed to help you find items instantly, whether you are looking by name, category, or barcode. Role-based access control ensures that employees only see what they need to see, while managers get full administrative access. The responsive design means that you can manage inventory from any device, whether that is a desktop computer, tablet, or smartphone.

Behind the scenes, the system uses a clean MVC architecture with PSR-4 autoloading standards, making the codebase maintainable and extensible. MongoDB provides the database layer, offering flexibility and scalability as your inventory grows. There is full CORS support configured, so integrating with external services and APIs works smoothly.

## What You Need To Run This System

Before you start, there are a few technical requirements that your server or computer needs to meet. The system is built on PHP version 8.3 or newer because this version provides the latest security features and performance improvements. For data storage, you will need MongoDB version 7.0 or newer, which is a modern database that handles inventory data efficiently.

You can run the application on either Apache version 2.4 or Nginx web servers, depending on your preference or existing infrastructure. Composer is required for managing PHP dependencies, and while you can install everything manually, using Docker and Docker Compose is highly recommended because it simplifies the entire setup process and ensures consistency across different environments.

## Getting Started With Docker

The fastest way to get this system running is by using Docker because it handles all the complex setup automatically. If you are on Linux or Debian 12, there is an automated setup script that does everything for you. Simply run `chmod +x setup.sh` to make the script executable, then run `./setup.sh` and wait for it to complete. After that, start the containers with `docker-compose -f container/docker-compose.yml up -d --build` and you are ready to go.

Once everything is running, you can access the web interface at `http://localhost:8082` and the MongoDB admin interface at `http://localhost:8081`. When you first run the system, you will need to set up an admin account by filling out the initial setup form. After that, users can create their own accounts through the sign up page or sign in if they already have credentials. There is also a guest role feature that allows visitors to access certain pages without signing up or signing in, but only if a team member or admin has sent them an invite link. This makes it easy to share specific information with partners or clients without requiring them to create full accounts. When you want to stop the system, simply run `docker-compose -f container/docker-compose.yml down`.

If you prefer manual setup, start by creating your environment file with `cp .env.sample .env`, then install the PHP dependencies using `composer install` because the autoloader needs these files. Finally, start the containers the same way as before. There are also convenient helper scripts available in the `container/docker/scripts` directory that make starting, stopping, and viewing logs easier.

## How The System Is Organized

Understanding the project structure helps you navigate the codebase and make modifications when needed. The architecture follows industry-standard patterns that many developers will recognize immediately. Below is the complete directory structure showing where everything lives

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
│   │   ├── js/
│   │   │   └── toast.js
│   │   └── logo/
│   │       └── favicon.svg
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

The system uses several well-established design patterns that make the code easier to maintain and extend. The DatabaseService class implements the Singleton pattern, which ensures that there is only one database connection throughout the application lifecycle. The overall structure follows the MVC or Model-View-Controller pattern, separating business logic from presentation and data access. There is a Front Controller pattern implemented in `public/index.php` that handles all incoming requests, and the Model layer acts as a Repository, abstracting data access from the rest of the application.

## Installing Without Docker

If you prefer not to use Docker or need to run the system on an existing server, you can install it manually. Before you begin, make sure you have XAMPP installed or at least Apache with PHP 8.3 or newer. You will also need MongoDB version 7.0 or higher and Composer for dependency management. There is one additional step that is important, which is adding the MongoDB PHP extension to your `php.ini` file by including the line `extension=mongodb`.

Once you have all the prerequisites ready, clone the repository to your local machine and navigate into the `inventory_demo` directory. Run `composer install` to download all required PHP dependencies. Next, create your environment configuration file by copying the sample file with `cp .env.sample .env`, then open the `.env` file and edit it with your MongoDB credentials.

After configuration is complete, initialize the database by visiting `http://localhost/inventory_demo/scripts/setup_mongodb.php` in your browser. This script will create the necessary database structure and prepare everything for first use. Finally, you can access the application by navigating to `http://localhost/inventory_demo/public/login.php` where you will be prompted to create your admin account.

## Using The API

The system includes a complete RESTful API that allows you to integrate with other applications or build custom interfaces. All API endpoints are located under the base URL `/api/v1/` and they return JSON responses that are easy to parse and work with.

For authentication, there are two main endpoints. You can log in by sending a POST request to `/auth.php?action=login` with your credentials, and log out by posting to `/auth.php?action=logout`. Once authenticated, your session is maintained through cookies, which means subsequent requests will automatically include your authentication token.

The inventory endpoints require authentication because they handle sensitive business data. You can list all items by sending a GET request to `/inventory.php`, or retrieve a specific item by including its ID like `/inventory.php?id={id}`. There is a search feature available at `/inventory.php?search={query}` that returns matching items, and you can filter for low stock items specifically by using `/inventory.php?low_stock=true`. Creating new items requires a POST request to `/inventory.php`, while updating existing items uses PUT at `/inventory.php?id={id}`. Finally, you can delete items with a DELETE request to the same endpoint pattern.

## Preparing For Production Deployment

When you are ready to deploy this system to a production environment, there are several important steps you need to follow to ensure security and optimal performance. First, update your environment variables by setting `APP_ENV=production` and `APP_DEBUG=false` because debug mode should never be enabled in production where it could expose sensitive information.

Next, optimize your PHP dependencies by running `composer install --no-dev --optimize-autoloader`. This command removes development dependencies that are not needed in production and optimizes the autoloader for faster performance. If you are using Apache, configure your VirtualHost to point the DocumentRoot to `/path/to/inventory_demo/public` so that only public files are accessible.

Security is critical in production environments. Make sure to change any default passwords immediately, including the admin account you created during setup. Configure MongoDB authentication properly with strong credentials and restrict database access to only the application server. Enable HTTPS on your web server because transmitting inventory data over unencrypted connections is a security risk. Finally, set proper file permissions on the server so that the web server can read files but not write to them except in specific directories like `var/logs` and `var/sessions`.

## Security Built Into Every Layer

Security is not an afterthought in this system but rather a fundamental part of the architecture. All passwords are hashed using bcrypt, which is an industry-standard algorithm that makes it extremely difficult for attackers to recover the original passwords even if they gain access to the database. Session timeout protection is implemented so that inactive sessions expire automatically, preventing unauthorized access if someone walks away from their computer.

Input validation and XSS protection are applied to every user input because malicious users might try to inject scripts or harmful code. All database queries use MongoDB parameterized queries, which means that user input is never directly concatenated into query strings where it could be exploited. CSRF protection is enabled for all form submissions, ensuring that requests are actually coming from your application and not from malicious third-party sites.

The system sends security headers with every response, including X-Frame-Options, X-XSS-Protection, and X-Content-Type-Options, which tell browsers to enforce additional security policies. HTTPS enforcement is recommended and easy to configure because inventory data should always be transmitted over encrypted connections. MongoDB authentication is required, and the role-based access control system ensures that users can only access the features and data they are authorized to see.

## Current Development Status

This system is currently in version 0.1.x alpha stage, which means that while it is functional and usable, there may still be breaking changes as development continues. The alpha designation indicates that we are actively improving features and fixing issues, so if you deploy this in production, be prepared to adapt to updates. Feedback from users during this phase is incredibly valuable because it helps us identify what works well and what needs improvement.

## Issues You Might Encounter

While we have tested the system extensively, there are a couple of known issues that you might run into. If you see an HTTP 500 error when trying to log in through Docker, the most common cause is that the credentials in your `.env` file do not match the MongoDB user that was created. Double check these values and make sure they are identical.

If you are deploying behind a reverse proxy like Cloudflare Tunnel, make sure your proxy configuration routes traffic to port 8082 and preserves cookies. Without cookie preservation, authentication will not work properly because the system relies on session cookies to maintain login state.

## Recent Improvements and Fixes

We have recently made several important improvements to the system. The Docker configuration was updated to work with Debian trixie, which required updating package references to `libzip5`, `libssl3t64`, and `libcurl4t64`. The MongoDB PHP extension was upgraded to version 2.x, bringing better performance and bug fixes.

Apache configuration was improved by adding a global `ServerName` directive and enabling the `remoteip` module with trusted subnets, which is important for deployments behind proxies. The Docker network was renamed to `inventory` for better clarity. There was also a MongoDB connection fix related to the `authSource` parameter that was causing authentication issues. Finally, the ports were standardized so that the web interface always runs on port 8082 and the MongoDB Express admin interface runs on port 8081.

## Solving Common Problems

If you encounter an error saying the MongoDB extension is not found, you need to add `extension=mongodb` to your `php.ini` file and then restart Apache. You can verify that the extension loaded correctly by running `php -m | findstr mongodb` in your terminal, which should show the MongoDB extension in the list.

When you see a MongoDB connection refused error, it usually means that the MongoDB service is not running. On Windows, you can start it by opening `services.msc`, finding MongoDB in the list, and clicking Start. On Linux, use your system's service manager like `systemctl start mongod`.

If you get a 404 Not Found error, make sure you are accessing the application through the correct URL, which should be `http://localhost/inventory_demo/public/`. Sometimes the issue is with your Apache configuration, so check the Apache error logs at `C:\xampp\apache\logs\error.log` for more details about what went wrong.

When login does not work even though you entered the correct credentials, you might need to initialize the database. Visit `http://localhost/inventory_demo/scripts/setup_mongodb.php` to run the setup script that creates the necessary database structure and user accounts.

If CSS files are not loading and the page looks broken, there are two common causes. First, make sure that `mod_rewrite` is enabled in Apache because the application uses URL rewriting for clean URLs. Second, try clearing your browser cache by pressing Ctrl+F5, which forces the browser to download fresh copies of all assets.

When the API returns Unauthorized errors, it usually means you are not logged in or your session has expired. Make sure you log in first through the web interface, and verify that cookies are enabled in your browser because they are required for session management.

## License and Legal Information

This project is licensed under the MIT License, which means you are free to use, modify, and distribute the software for both personal and commercial purposes. The only requirement is that you include the original copyright notice and license text in any copies or substantial portions of the software. You can find the complete license details in the LICENSE file included with this repository.

## Contributing to This Project

We welcome contributions from developers of all skill levels because community input makes this project better. If you want to contribute code, documentation, or bug reports, please start by reading our Contributing Guidelines and Code of Conduct, which are located in the `.github` directory. These documents explain how to submit pull requests, report issues, and interact with other contributors in a respectful and productive manner.

## Reporting Security Vulnerabilities

If you discover a security vulnerability in this system, please do not open a public issue because that would expose the vulnerability to potential attackers. Instead, follow the instructions in our Security Policy document located at `.github/SECURITY.md`, which explains how to report security issues privately so that we can fix them before they are disclosed publicly.

## Best Practices For Production Use

When running this system in production, there are several practices that will help ensure reliability and security. Regular backups are essential because they protect you from data loss due to hardware failure, software bugs, or security breaches. Schedule automated backups of your MongoDB database and store them in a secure location separate from your primary server.

Monitoring logs is important for catching problems before they become serious. Set up log rotation so that old logs are archived and new ones are created regularly. Review error logs frequently to identify patterns that might indicate issues.

Keep your dependencies up to date because software updates often include security patches and bug fixes. Run `composer update` periodically, but test updates in a staging environment before applying them to production. Use strong passwords for all accounts, especially administrative accounts, and consider implementing two-factor authentication for added security. Finally, always use HTTPS in production because it encrypts data in transit and protects against man-in-the-middle attacks.

---

Developed with care for modern inventory management
