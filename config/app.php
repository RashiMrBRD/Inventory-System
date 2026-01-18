<?php
/**
 * Application Configuration File
 * This file contains general application settings
 */

if (!function_exists('isPrivateIP')) {
    /**
     * Check if an IP address is in a private range
     * @param string $ip The IP address to check
     * @param array $ranges Array of CIDR ranges (e.g., ['10.0.0.0/8', '172.16.0.0/12'])
     * @return bool True if IP is in any of the ranges
     */
    function isPrivateIP($ip, $ranges = []) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return false;
        }

        foreach ($ranges as $range) {
            list($network, $mask) = explode('/', $range);
            $networkLong = ip2long($network);
            $maskLong = -1 << (32 - $mask);

            if (($ipLong & $maskLong) === ($networkLong & $maskLong)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('isHostAllowed')) {
    /**
     * Check if a host is allowed to access sensitive endpoints
     * @param string $host The host to check
     * @param array $config The access control configuration
     * @return bool True if host is allowed
     */
    function isHostAllowed($host, $config) {
        $hostOnly = parse_url('http://' . $host, PHP_URL_HOST) ?: $host;

        // Check if it's the demo domain
        if (strpos($hostOnly, $config['demo_domain']) === 0) {
            return true;
        }

        // Check if it's in allowed hosts list
        if (in_array($hostOnly, $config['allowed_hosts'], true)) {
            return true;
        }

        // Check if it's a private IP
        if (isPrivateIP($hostOnly, $config['private_ip_ranges'])) {
            return true;
        }

        return false;
    }
}

return [
    'app_name' => 'Inventory Management System',
    'app_version' => '0.4.2',
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
            'https://live.rashlink.eu.org',
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
        ],
        'allow_registration' => true, // Allow new user registration
        'allow_invitations' => false, // Allow invitation-based registration

        // Access control for page-loader and other sensitive endpoints
        'access_control' => [
            'demo_domain' => 'demo.rashlink.eu.org',
            'allowed_hosts' => [
                'demo.rashlink.eu.org',
                'localhost',
                '127.0.0.1',
                '::1'
            ],
            'private_ip_ranges' => [
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16'
            ]
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
    ],

    // Asset proxy configuration
    'assets' => [
        // Prefer environment; fallback is dev-only. Set ASSET_SIGNING_KEY in production.
        'signing_key' => getenv('ASSET_SIGNING_KEY') ?: 'change-me-dev-key'
    ]
];
