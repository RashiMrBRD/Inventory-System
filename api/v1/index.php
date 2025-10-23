<?php
/**
 * API Index/Documentation
 * This file provides information about the available API endpoints
 */

header('Content-Type: application/json');

$apiInfo = [
    'name' => 'Inventory Management System API',
    'version' => '1.0.0',
    'base_url' => '/api/v1',
    'endpoints' => [
        'authentication' => [
            'path' => '/api/v1/auth.php',
            'methods' => [
                [
                    'method' => 'POST',
                    'action' => 'login',
                    'description' => 'Authenticate user and create session',
                    'parameters' => [
                        'username' => 'string (required)',
                        'password' => 'string (required)'
                    ],
                    'example' => [
                        'url' => '/api/v1/auth.php?action=login',
                        'body' => [
                            'username' => 'admin',
                            'password' => 'admin123'
                        ]
                    ]
                ],
                [
                    'method' => 'POST',
                    'action' => 'logout',
                    'description' => 'Logout current user',
                    'example' => [
                        'url' => '/api/v1/auth.php?action=logout'
                    ]
                ],
                [
                    'method' => 'POST',
                    'action' => 'check',
                    'description' => 'Check if user is logged in',
                    'example' => [
                        'url' => '/api/v1/auth.php?action=check'
                    ]
                ]
            ]
        ],
        'inventory' => [
            'path' => '/api/v1/inventory.php',
            'authentication' => 'required',
            'methods' => [
                [
                    'method' => 'GET',
                    'description' => 'Get all inventory items',
                    'example' => '/api/v1/inventory.php'
                ],
                [
                    'method' => 'GET',
                    'description' => 'Get a specific item by ID',
                    'parameters' => [
                        'id' => 'string (required)'
                    ],
                    'example' => '/api/v1/inventory.php?id=507f1f77bcf86cd799439011'
                ],
                [
                    'method' => 'GET',
                    'description' => 'Search inventory items',
                    'parameters' => [
                        'search' => 'string (required)'
                    ],
                    'example' => '/api/v1/inventory.php?search=laptop'
                ],
                [
                    'method' => 'GET',
                    'description' => 'Get low stock items',
                    'parameters' => [
                        'low_stock' => 'true',
                        'threshold' => 'integer (optional, default: 5)'
                    ],
                    'example' => '/api/v1/inventory.php?low_stock=true&threshold=10'
                ],
                [
                    'method' => 'GET',
                    'description' => 'Get inventory statistics',
                    'parameters' => [
                        'statistics' => 'true'
                    ],
                    'example' => '/api/v1/inventory.php?statistics=true'
                ],
                [
                    'method' => 'POST',
                    'description' => 'Create a new inventory item',
                    'body' => [
                        'barcode' => 'string (required)',
                        'name' => 'string (required)',
                        'type' => 'string (required)',
                        'lifespan' => 'string (optional)',
                        'quantity' => 'integer (required)'
                    ],
                    'example' => [
                        'url' => '/api/v1/inventory.php',
                        'body' => [
                            'barcode' => '123456789',
                            'name' => 'Laptop Dell XPS',
                            'type' => 'Electronics',
                            'lifespan' => '5 years',
                            'quantity' => 10
                        ]
                    ]
                ],
                [
                    'method' => 'PUT',
                    'description' => 'Update an existing inventory item',
                    'parameters' => [
                        'id' => 'string (required)'
                    ],
                    'body' => [
                        'barcode' => 'string (optional)',
                        'name' => 'string (optional)',
                        'type' => 'string (optional)',
                        'lifespan' => 'string (optional)',
                        'quantity' => 'integer (optional)'
                    ],
                    'example' => [
                        'url' => '/api/v1/inventory.php?id=507f1f77bcf86cd799439011',
                        'body' => [
                            'quantity' => 15
                        ]
                    ]
                ],
                [
                    'method' => 'DELETE',
                    'description' => 'Delete an inventory item',
                    'parameters' => [
                        'id' => 'string (required)'
                    ],
                    'example' => '/api/v1/inventory.php?id=507f1f77bcf86cd799439011'
                ]
            ]
        ]
    ],
    'response_format' => [
        'success' => 'boolean',
        'message' => 'string (optional)',
        'data' => 'object or array (optional)'
    ]
];

echo json_encode($apiInfo, JSON_PRETTY_PRINT);
