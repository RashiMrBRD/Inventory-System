<?php
/**
 * Delete Item Handler
 * This file handles inventory item deletion
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Controller\InventoryController;
use App\Helper\SessionHelper;

$authController = new AuthController();
$authController->requireLogin();

$inventoryController = new InventoryController();

// Get item ID from URL
$itemId = $_GET['id'] ?? '';

if (!empty($itemId)) {
    $result = $inventoryController->deleteItem($itemId);
    
    if ($result['success']) {
        SessionHelper::setFlash('Item deleted successfully', 'success');
    } else {
        SessionHelper::setFlash('Failed to delete item', 'danger');
    }
}

header("Location: inventory-list.php");
exit();
