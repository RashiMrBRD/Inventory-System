<?php
/**
 * Database Seeding Script
 * Populates MongoDB collections with sample data for testing
 * 
 * Usage: php scripts/seed-sample-data.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\DatabaseService;
use MongoDB\BSON\UTCDateTime;

echo "==========================================\n";
echo "  Database Seeding Script\n";
echo "==========================================\n\n";

$db = DatabaseService::getInstance();

// ============================================
// SEED INVOICES
// ============================================
echo "Seeding invoices collection...\n";
$invoicesCollection = $db->getCollection('invoices');

$sampleInvoices = [
    [
        'customer' => 'ABC Corporation',
        'date' => new UTCDateTime(strtotime('-15 days') * 1000),
        'due' => new UTCDateTime(strtotime('+15 days') * 1000),
        'total' => 25600.00,
        'paid' => 25600.00,
        'status' => 'paid',
        'payment_currency' => 'PHP'
    ],
    [
        'customer' => 'XYZ Trading Co.',
        'date' => new UTCDateTime(strtotime('-10 days') * 1000),
        'due' => new UTCDateTime(strtotime('+20 days') * 1000),
        'total' => 18900.00,
        'paid' => 0.00,
        'status' => 'pending',
        'payment_currency' => 'PHP'
    ],
    [
        'customer' => 'Manila Restaurant Group',
        'date' => new UTCDateTime(strtotime('-30 days') * 1000),
        'due' => new UTCDateTime(strtotime('-5 days') * 1000),
        'total' => 42000.00,
        'paid' => 20000.00,
        'status' => 'overdue',
        'payment_currency' => 'PHP'
    ],
    [
        'customer' => 'Food Hub Inc.',
        'date' => new UTCDateTime(strtotime('-7 days') * 1000),
        'due' => new UTCDateTime(strtotime('+23 days') * 1000),
        'total' => 33500.00,
        'paid' => 33500.00,
        'status' => 'paid',
        'payment_currency' => 'PHP'
    ]
];

try {
    $invoicesCollection->insertMany($sampleInvoices);
    echo "✓ Inserted " . count($sampleInvoices) . " sample invoices\n\n";
} catch (Exception $e) {
    echo "✗ Error seeding invoices: " . $e->getMessage() . "\n\n";
}

// ============================================
// SEED QUOTATIONS
// ============================================
echo "Seeding quotations collection...\n";
$quotationsCollection = $db->getCollection('quotations');

$sampleQuotations = [
    [
        'customer' => 'Cebu Bistro',
        'date' => new UTCDateTime(strtotime('-5 days') * 1000),
        'total' => 15800.00,
        'status' => 'pending'
    ],
    [
        'customer' => 'Davao Catering Services',
        'date' => new UTCDateTime(strtotime('-12 days') * 1000),
        'total' => 28400.00,
        'status' => 'approved'
    ],
    [
        'customer' => 'Makati Hotel Supplies',
        'date' => new UTCDateTime(strtotime('-3 days') * 1000),
        'total' => 52000.00,
        'status' => 'pending'
    ]
];

try {
    $quotationsCollection->insertMany($sampleQuotations);
    echo "✓ Inserted " . count($sampleQuotations) . " sample quotations\n\n";
} catch (Exception $e) {
    echo "✗ Error seeding quotations: " . $e->getMessage() . "\n\n";
}

// ============================================
// SEED ORDERS
// ============================================
echo "Seeding orders collection...\n";
$ordersCollection = $db->getCollection('orders');

$sampleOrders = [
    [
        'type' => 'Sales',
        'customer' => 'QC Restaurant Chain',
        'date' => new UTCDateTime(strtotime('-8 days') * 1000),
        'total' => 45200.00,
        'status' => 'processing'
    ],
    [
        'type' => 'Purchase',
        'customer' => 'Supplier ABC',
        'date' => new UTCDateTime(strtotime('-6 days') * 1000),
        'total' => 22800.00,
        'status' => 'completed'
    ],
    [
        'type' => 'Sales',
        'customer' => 'Pasig Food Hub',
        'date' => new UTCDateTime(strtotime('-2 days') * 1000),
        'total' => 38900.00,
        'status' => 'processing'
    ],
    [
        'type' => 'Purchase',
        'customer' => 'Metro Distributors',
        'date' => new UTCDateTime(strtotime('-14 days') * 1000),
        'total' => 67500.00,
        'status' => 'completed'
    ]
];

try {
    $ordersCollection->insertMany($sampleOrders);
    echo "✓ Inserted " . count($sampleOrders) . " sample orders\n\n";
} catch (Exception $e) {
    echo "✗ Error seeding orders: " . $e->getMessage() . "\n\n";
}

// ============================================
// SEED PROJECTS
// ============================================
echo "Seeding projects collection...\n";
$projectsCollection = $db->getCollection('projects');

$sampleProjects = [
    [
        'name' => 'Restaurant Supply Contract - Q4 2025',
        'client' => 'Manila Bay Resort',
        'start' => new UTCDateTime(strtotime('-60 days') * 1000),
        'end' => new UTCDateTime(strtotime('+30 days') * 1000),
        'budget' => 1250000.00,
        'spent' => 875000.00,
        'status' => 'active'
    ],
    [
        'name' => 'Hotel Kitchen Equipment Setup',
        'client' => 'BGC Grand Hotel',
        'start' => new UTCDateTime(strtotime('-90 days') * 1000),
        'end' => new UTCDateTime(strtotime('-15 days') * 1000),
        'budget' => 850000.00,
        'spent' => 820000.00,
        'status' => 'completed'
    ],
    [
        'name' => 'Catering Services Annual Contract',
        'client' => 'Corporate Tower Events',
        'start' => new UTCDateTime(strtotime('-30 days') * 1000),
        'end' => new UTCDateTime(strtotime('+335 days') * 1000),
        'budget' => 2400000.00,
        'spent' => 450000.00,
        'status' => 'active'
    ]
];

try {
    $projectsCollection->insertMany($sampleProjects);
    echo "✓ Inserted " . count($sampleProjects) . " sample projects\n\n";
} catch (Exception $e) {
    echo "✗ Error seeding projects: " . $e->getMessage() . "\n\n";
}

// ============================================
// SEED SHIPMENTS
// ============================================
echo "Seeding shipments collection...\n";
$shipmentsCollection = $db->getCollection('shipments');

$sampleShipments = [
    [
        'order' => 'ORD-2025-001',
        'customer' => 'Manila Restaurant Group',
        'carrier' => 'LBC Express',
        'tracking' => 'LBC-123456789',
        'date' => new UTCDateTime(strtotime('-5 days') * 1000),
        'status' => 'in_transit'
    ],
    [
        'order' => 'ORD-2025-002',
        'customer' => 'Cebu Bistro',
        'carrier' => 'J&T Express',
        'tracking' => 'JT-987654321',
        'date' => new UTCDateTime(strtotime('-12 days') * 1000),
        'status' => 'delivered'
    ],
    [
        'order' => 'ORD-2025-003',
        'customer' => 'QC Restaurant Chain',
        'carrier' => 'DHL Philippines',
        'tracking' => 'DHL-456789123',
        'date' => new UTCDateTime(strtotime('-1 days') * 1000),
        'status' => 'pending'
    ]
];

try {
    $shipmentsCollection->insertMany($sampleShipments);
    echo "✓ Inserted " . count($sampleShipments) . " sample shipments\n\n";
} catch (Exception $e) {
    echo "✗ Error seeding shipments: " . $e->getMessage() . "\n\n";
}

// ============================================
// SEED BIR FORMS
// ============================================
echo "Seeding bir_forms collection...\n";
$birFormsCollection = $db->getCollection('bir_forms');

$sampleBirForms = [
    [
        'form_type' => '2550M',
        'period' => 'September 2025',
        'status' => 'filed',
        'amount' => 25600.00,
        'date' => new UTCDateTime(strtotime('-20 days') * 1000),
        'due_date' => new UTCDateTime(strtotime('-5 days') * 1000)
    ],
    [
        'form_type' => '1601C',
        'period' => 'September 2025',
        'status' => 'filed',
        'amount' => 12400.00,
        'date' => new UTCDateTime(strtotime('-18 days') * 1000),
        'due_date' => new UTCDateTime(strtotime('-3 days') * 1000)
    ],
    [
        'form_type' => '2550M',
        'period' => 'October 2025',
        'status' => 'pending',
        'amount' => 28900.00,
        'date' => new UTCDateTime(strtotime('-2 days') * 1000),
        'due_date' => new UTCDateTime(strtotime('+10 days') * 1000)
    ]
];

try {
    $birFormsCollection->insertMany($sampleBirForms);
    echo "✓ Inserted " . count($sampleBirForms) . " sample BIR forms\n\n";
} catch (Exception $e) {
    echo "✗ Error seeding BIR forms: " . $e->getMessage() . "\n\n";
}

// ============================================
// SEED FDA PRODUCTS
// ============================================
echo "Seeding fda_products collection...\n";
$fdaProductsCollection = $db->getCollection('fda_products');

$sampleFdaProducts = [
    [
        'name' => 'Olive Oil Premium',
        'batch' => 'LOT-20251020-B8A4',
        'expiry' => new UTCDateTime(strtotime('+75 days') * 1000),
        'quantity' => 150,
        'active' => true
    ],
    [
        'name' => 'Pasta Premium',
        'batch' => 'LOT-20251018-F7C1',
        'expiry' => new UTCDateTime(strtotime('+55 days') * 1000),
        'quantity' => 200,
        'active' => true
    ],
    [
        'name' => 'Mushrooms (Canned)',
        'batch' => 'LOT-20251015-A3B2',
        'expiry' => new UTCDateTime(strtotime('+22 days') * 1000),
        'quantity' => 45,
        'active' => true
    ],
    [
        'name' => 'Tomato Sauce',
        'batch' => 'LOT-20251010-C5D6',
        'expiry' => new UTCDateTime(strtotime('+18 days') * 1000),
        'quantity' => 80,
        'active' => true
    ],
    [
        'name' => 'Canned Tuna',
        'batch' => 'LOT-20251005-E7F8',
        'expiry' => new UTCDateTime(strtotime('+85 days') * 1000),
        'quantity' => 120,
        'active' => true
    ]
];

try {
    $fdaProductsCollection->insertMany($sampleFdaProducts);
    echo "✓ Inserted " . count($sampleFdaProducts) . " sample FDA products\n\n";
} catch (Exception $e) {
    echo "✗ Error seeding FDA products: " . $e->getMessage() . "\n\n";
}

// ============================================
// SUMMARY
// ============================================
echo "==========================================\n";
echo "  Seeding Complete!\n";
echo "==========================================\n\n";

echo "Summary:\n";
echo "- Invoices: " . count($sampleInvoices) . " records\n";
echo "- Quotations: " . count($sampleQuotations) . " records\n";
echo "- Orders: " . count($sampleOrders) . " records\n";
echo "- Projects: " . count($sampleProjects) . " records\n";
echo "- Shipments: " . count($sampleShipments) . " records\n";
echo "- BIR Forms: " . count($sampleBirForms) . " records\n";
echo "- FDA Products: " . count($sampleFdaProducts) . " records\n\n";

echo "You can now navigate to the dashboard and other pages to see real data!\n\n";
