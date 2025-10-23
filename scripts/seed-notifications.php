<?php
/**
 * Notification Seeder
 * Creates thousands of sample notifications for testing
 */

require_once __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;

$config = require __DIR__ . '/../config/database.php';

$client = new Client(
    sprintf(
        'mongodb://%s:%d',
        $config['mongodb']['host'],
        $config['mongodb']['port']
    )
);

$db = $client->selectDatabase($config['mongodb']['database']);
$collection = $db->selectCollection('notifications');

// Clear existing notifications
$collection->deleteMany([]);

echo "🌱 Seeding notifications database...\n";

$types = ['inventory', 'expiry', 'financial', 'bir', 'fda', 'announcement', 'success'];
$priorities = ['high', 'medium', 'normal'];
$userId = 'admin'; // Default user

$notifications = [];
$now = time();

// Generate 2000 notifications
for ($i = 1; $i <= 2000; $i++) {
    $type = $types[array_rand($types)];
    $priority = $priorities[array_rand($priorities)];
    $daysAgo = rand(0, 90);
    $timestamp = $now - ($daysAgo * 86400) - rand(0, 86400);
    
    $titles = [
        'inventory' => [
            'Low Stock Alert',
            'Stock Running Out',
            'Inventory Warning',
            'Reorder Required',
            'Stock Level Critical'
        ],
        'expiry' => [
            'Products Expiring Soon',
            'Expiry Alert',
            'Items Near Expiration',
            'Stock Expiring',
            'Expiration Warning'
        ],
        'financial' => [
            'Invoice Overdue',
            'Payment Due',
            'Outstanding Invoice',
            'Payment Reminder',
            'Invoice Pending'
        ],
        'bir' => [
            'BIR Filing Deadline',
            'Tax Return Due',
            'BIR Compliance Alert',
            'Tax Filing Reminder',
            'BIR Submission Required'
        ],
        'fda' => [
            'LTO Renewal Reminder',
            'FDA Compliance Alert',
            'License Expiring',
            'Permit Renewal Due',
            'Certification Expiring'
        ],
        'announcement' => [
            'System Maintenance',
            'Important Announcement',
            'System Update',
            'Scheduled Maintenance',
            'New Feature Available'
        ],
        'success' => [
            'Payment Received',
            'Order Completed',
            'Stock Replenished',
            'Invoice Paid',
            'Task Completed'
        ]
    ];
    
    $messages = [
        'inventory' => [
            'Olive Oil Premium - only 45 units remaining',
            'Canned Mushrooms - stock level at 12 units',
            'Fresh Tomatoes - 8 units left in warehouse',
            'Pasta Supplies - urgent reorder needed',
            'Cooking Oil - critical stock level'
        ],
        'expiry' => [
            '4 products expire within 30 days - immediate action required',
            'Mushrooms (Canned) expires in 22 days',
            'Dairy products expiring this week',
            'Frozen items expiring in 15 days',
            'Perishables expiring soon'
        ],
        'financial' => [
            'Invoice #INV-2025-1045 from Landers - overdue (15 days)',
            'Payment of ₱185,000 due from Hotel Manila',
            'Outstanding balance: ₱45,000 from supplier',
            'Invoice #INV-2025-1046 - 30 days overdue',
            'Payment reminder: ₱125,000 due tomorrow'
        ],
        'bir' => [
            'VAT Return (2550M) due on November 15, 2025',
            'Monthly tax filing deadline approaching',
            'Quarterly BIR report due this month',
            'Annual compliance filing required',
            'Tax documentation needed for audit'
        ],
        'fda' => [
            'License to Operate expires in 73 days',
            'FDA permit renewal required',
            'Health certification expiring next month',
            'Sanitation permit needs renewal',
            'Food handling license expiring'
        ],
        'announcement' => [
            'Scheduled maintenance on Sunday 2AM-4AM',
            'New inventory management features available',
            'System upgrade completed successfully',
            'Database optimization in progress',
            'New security features implemented'
        ],
        'success' => [
            '₱250,000 payment received from Hotel Manila',
            'Stock replenishment completed successfully',
            'Invoice #INV-2025-1040 marked as paid',
            'Inventory count verification completed',
            'System backup completed successfully'
        ]
    ];
    
    $title = $titles[$type][array_rand($titles[$type])];
    $message = $messages[$type][array_rand($messages[$type])];
    
    $notification = [
        'user_id' => $userId,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'priority' => $priority,
        'read' => rand(0, 1) === 1,
        'dismissed' => rand(0, 100) < 10, // 10% dismissed
        'deleted' => false,
        'created_at' => new UTCDateTime($timestamp * 1000),
        'read_at' => rand(0, 1) === 1 ? new UTCDateTime(($timestamp + rand(3600, 86400)) * 1000) : null,
        'dismissed_at' => rand(0, 100) < 10 ? new UTCDateTime(($timestamp + rand(3600, 86400)) * 1000) : null,
    ];
    
    $notifications[] = $notification;
    
    if ($i % 100 === 0) {
        echo "  ✓ Generated $i notifications\n";
    }
}

// Insert all notifications
$collection->insertMany($notifications);

// Create indexes
$collection->createIndex(['user_id' => 1, 'created_at' => -1]);
$collection->createIndex(['user_id' => 1, 'read' => 1]);
$collection->createIndex(['user_id' => 1, 'dismissed' => 1]);
$collection->createIndex(['user_id' => 1, 'type' => 1]);
$collection->createIndex(['user_id' => 1, 'priority' => 1]);

echo "\n✅ Seeding complete!\n";
echo "📊 Statistics:\n";
echo "  • Total notifications: 2000\n";
echo "  • User: $userId\n";
echo "  • Types: " . implode(', ', $types) . "\n";
echo "  • Priorities: " . implode(', ', $priorities) . "\n";
echo "\n💡 To run this seeder:\n";
echo "  php scripts/seed-notifications.php\n";
