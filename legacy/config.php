<?php
// MongoDB configuration
require_once __DIR__ . '/vendor/autoload.php';

// MongoDB connection
try {
    $mongoClient = new MongoDB\Client("mongodb://localhost:27017");
    $db = $mongoClient->inventory_system;
    
    // Collections
    $inventoryCollection = $db->inventory;
    $usersCollection = $db->users;
    
} catch (Exception $e) {
    die("MongoDB connection error: " . $e->getMessage());
}

// Start session
session_start();

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to get current user
function getCurrentUser() {
    global $usersCollection;
    if (!isLoggedIn()) {
        return null;
    }
    
    $user_id = $_SESSION['user_id'];
    return $usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($user_id)]);
}

// Function to redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}
?>
