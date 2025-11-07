<?php
/**
 * Application Configuration File
 * This file contains general application settings
 */

return [
    'app_name' => 'Inventory Management System',
    'app_version' => '0.3.2',
    'environment' => getenv('APP_ENV') ?: 'development',
    'debug' => getenv('APP_DEBUG') === 'true',
    'timezone' => 'UTC',
    
    'session' => [
        'lifetime' => 120, // minutes
        'cookie_name' => 'inventory_session',
        'cookie_secure' => getenv('APP_ENV') === 'production',
        'cookie_httponly' => true
    ],
    
    'cors' => [
        'allowed_origins' => [
            'https://demo.rashlink.eu.org',
            'http://localhost'
        ],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With']
    ],
    
    'api' => [
        'version' => 'v1',
        'rate_limit' => [
            'enabled' => true,
            'max_requests' => 100,
            'per_minutes' => 60
        ]
    ],

    // Network security / proxy awareness
    'security' => [
        'trusted_proxies' => [
            '172.24.0.0/16',
            '192.168.123.0/24',
            '192.168.100.0/24'
        ],
        'trusted_hosts' => [
            'demo.rashlink.eu.org',
            'localhost',
            '192.168.123.10'
        ]
    ],
    
    'mail' => [
        'driver' => getenv('MAIL_DRIVER') ?: null, // smtp, sendmail, mailgun, etc
        'host' => getenv('MAIL_HOST') ?: null,
        'port' => getenv('MAIL_PORT') ?: 587,
        'username' => getenv('MAIL_USERNAME') ?: null,
        'password' => getenv('MAIL_PASSWORD') ?: null,
        'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
        'from' => [
            'address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@inventory.local',
            'name' => getenv('MAIL_FROM_NAME') ?: 'Inventory Management System'
        ]
    ]
];
