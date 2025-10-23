<?php
/**
 * Main Entry Point - Front Controller
 * This file serves as the main entry point for the application
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Controller\InventoryController;

$authController = new AuthController();
$authController->requireLogin();

$user = $authController->getCurrentUser();
$inventoryController = new InventoryController();
$result = $inventoryController->getAllItems();

$items = $result['success'] ? $result['data'] : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Sheet - Inventory Management System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>INVENTORY SHEET</h1>
            
            <div class="header-info">
                <div class="user-info">
                    <div class="info-item">
                        <label>Status:</label>
                        <div class="value">
                            <span class="status-indicator"></span>
                            Online
                        </div>
                    </div>
                    <div class="info-item">
                        <label>User:</label>
                        <div class="value"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Access Level:</label>
                        <div class="value">
                            <span class="access-badge"><?php echo htmlspecialchars($user['access_level'] ?? 'user'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="add_item.php" class="btn btn-primary">+ Add Item</a>
                    <a href="logout.php" class="btn btn-secondary">Logout</a>
                </div>
            </div>
        </div>
        
        <div class="content">
            <?php if (empty($items)): ?>
                <div class="empty-state">
                    <p>No inventory items found. Click "Add Item" to get started.</p>
                </div>
            <?php else: ?>
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Item#</th>
                            <th>Barcode</th>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th>Lifespan</th>
                            <th>Quantity</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach ($items as $item): 
                            $quantity = $item['quantity'] ?? 0;
                            $lowStock = $quantity <= 5;
                            $rowClass = $lowStock ? 'low-stock' : '';
                            $dateAdded = isset($item['date_added']) ? $item['date_added']->toDateTime()->format('Y-m-d') : 'N/A';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td><?php echo $counter++; ?></td>
                            <td><?php echo htmlspecialchars($item['barcode'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($item['name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($item['type'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($item['lifespan'] ?? ''); ?></td>
                            <td class="quantity"><?php echo htmlspecialchars($quantity); ?></td>
                            <td><?php echo htmlspecialchars($dateAdded); ?></td>
                            <td class="actions">
                                <a href="edit_item.php?id=<?php echo (string)$item['_id']; ?>" class="btn-action btn-edit">Edit</a>
                                <a href="delete_item.php?id=<?php echo (string)$item['_id']; ?>" 
                                   class="btn-action btn-delete" 
                                   onclick="return confirm('Are you sure you want to delete this item?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
