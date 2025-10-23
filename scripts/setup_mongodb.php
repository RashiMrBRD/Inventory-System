<?php
// MongoDB Setup Script
require_once __DIR__ . '/../vendor/autoload.php';

try {
    $mongoClient = new MongoDB\Client("mongodb://localhost:27017");
    $db = $mongoClient->inventory_system;
    
    // Drop existing collections if they exist
    $db->dropCollection('inventory');
    $db->dropCollection('users');
    
    // Create collections
    $inventoryCollection = $db->inventory;
    $usersCollection = $db->users;
    
    // Insert sample inventory data
    $inventoryCollection->insertMany([
        [
            'barcode' => '1090912',
            'name' => 'Milo 300g',
            'type' => 'Packed Goods',
            'lifespan' => '2 to 10 years',
            'quantity' => 20,
            'date_added' => new MongoDB\BSON\UTCDateTime(strtotime('2025-10-18') * 1000)
        ],
        [
            'barcode' => '12345678',
            'name' => 'Alaska 200g',
            'type' => 'Packed Goods',
            'lifespan' => '2 to 10 years',
            'quantity' => 1,
            'date_added' => new MongoDB\BSON\UTCDateTime(strtotime('2025-10-08') * 1000)
        ],
        [
            'barcode' => '11223344',
            'name' => 'Oranges',
            'type' => 'Fruits',
            'lifespan' => '1 week',
            'quantity' => 5,
            'date_added' => new MongoDB\BSON\UTCDateTime(strtotime('2025-10-02') * 1000)
        ],
        [
            'barcode' => '55667788',
            'name' => 'Rice Krispies 100g',
            'type' => 'Pastries',
            'lifespan' => '1 week',
            'quantity' => 25,
            'date_added' => new MongoDB\BSON\UTCDateTime(strtotime('2025-10-04') * 1000)
        ],
        [
            'barcode' => '12349876',
            'name' => 'Toast Bread',
            'type' => 'Pastries',
            'lifespan' => '1 week',
            'quantity' => 30,
            'date_added' => new MongoDB\BSON\UTCDateTime(strtotime('2025-10-14') * 1000)
        ],
        [
            'barcode' => '29830821',
            'name' => 'Rebisco',
            'type' => 'Packed Goods',
            'lifespan' => '2 to 10 years',
            'quantity' => 32,
            'date_added' => new MongoDB\BSON\UTCDateTime(strtotime('2025-10-13') * 1000)
        ],
        [
            'barcode' => '8721453',
            'name' => 'Piattos 50g',
            'type' => 'Packed Goods',
            'lifespan' => '2 to 10 years',
            'quantity' => 72,
            'date_added' => new MongoDB\BSON\UTCDateTime(strtotime('2025-10-20') * 1000)
        ],
        [
            'barcode' => '57831235',
            'name' => 'Brownies',
            'type' => 'Pastries',
            'lifespan' => '3 days',
            'quantity' => 92,
            'date_added' => new MongoDB\BSON\UTCDateTime(strtotime('2025-10-21') * 1000)
        ]
    ]);
    
    // Insert default admin user (password: admin123)
    $usersCollection->insertOne([
        'username' => 'admin',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'full_name' => 'Gian Benedict',
        'access_level' => 'Admin',
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ]);
    
    echo "MongoDB setup completed successfully!\n";
    echo "Collections created: inventory, users\n";
    echo "Sample data inserted.\n";
    echo "\nDefault login:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";
    
} catch (Exception $e) {
    die("Error setting up MongoDB: " . $e->getMessage());
}
?>
