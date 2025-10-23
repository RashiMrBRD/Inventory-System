<?php
/**
 * Delete Item Handler
 * This file handles inventory item deletion
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Controller\InventoryController;

$authController = new AuthController();
$authController->requireLogin();

$inventoryController = new InventoryController();

// Get item ID from URL
$itemId = $_GET['id'] ?? '';

if (!empty($itemId)) {
    $result = $inventoryController->deleteItem($itemId);
    
    session_start();
    if ($result['success']) {
        $_SESSION['flash_message'] = 'Item deleted successfully';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = 'Failed to delete item';
        $_SESSION['flash_type'] = 'danger';
    }
}

header("Location: inventory-list.php");
exit();
