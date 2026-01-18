<?php
/**
 * Database Configuration File
 * This file contains all the database connection settings for MongoDB
 */

return [
    'mongodb' => [
        'host' => getenv('MONGODB_HOST') ?: 'localhost',
        'port' => getenv('MONGODB_PORT') ?: 27017,
        'database' => getenv('MONGODB_DATABASE') ?: 'inventory_system',
        'username' => getenv('MONGODB_USERNAME') ?: '',
        'password' => getenv('MONGODB_PASSWORD') ?: '',
        'options' => [
            'ssl' => getenv('MONGODB_SSL') === 'true',
        ]
    ],
    
    'collections' => [
        'inventory' => 'inventory',
        'users' => 'users',
        'logs' => 'activity_logs',
        // Business documents
        'invoices' => 'invoices',
        'quotations' => 'quotations',
        'orders' => 'orders',
        'projects' => 'projects',
        'shipments' => 'shipments',
        // Compliance
        'bir_forms' => 'bir_forms',
        'fda_products' => 'fda_products',
        // System
        'notifications' => 'notifications',
        'invites' => 'invites'
    ]
];
