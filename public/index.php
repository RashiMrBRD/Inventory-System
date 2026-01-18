<?php
/**
 * Main Entry Point - Front Controller
 * This file serves as the main entry point for the application
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Controller\InventoryController;

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$route = trim($uri, '/');
$route = preg_replace('/\\.php$/i', '', $route);
$route = ($route === 'index' || $route === 'index.php') ? '' : $route;

$dispatchMap = [
    'account-form' => '/fragments/forms/account-form.php',
    'add_item' => '/fragments/forms/add_item.php',
    'analytics-dashboard' => '/core/pages/analytics-dashboard.php',
    'api/check-updates' => '/core/api/check-updates.php',
    'api/download-update' => '/core/api/download-update.php',
    'api/get-notifications' => '/api/get-notifications.php',
    'api/get-sessions' => '/api/get-sessions.php',
    'api/server-time' => '/core/api/server-time.php',
    'api/update-progress' => '/core/api/update-progress.php',
    'asset' => '/core/utils/asset.php',
    'barcode' => '/core/utils/barcode.php',
    'bir-compliance' => '/core/modules/bir-compliance.php',
    'body-loader' => '/core/loaders/body-loader.php',
    'chart-of-accounts' => '/core/modules/chart-of-accounts.php',
    'conversations' => '/core/pages/conversations.php',
    'css-loader' => '/core/loaders/css-loader.php',
    'dashboard' => '/core/pages/dashboard.php',
    'delete_item' => '/fragments/forms/delete_item.php',
    'docs' => '/core/pages/docs.php',
    'edit_item' => '/fragments/forms/edit_item.php',
    'export-report-pdf' => '/core/utils/export-report-pdf.php',
    'fda-compliance' => '/core/modules/fda-compliance.php',
    'financial-reports' => '/core/pages/financial-reports.php',
    'font-loader' => '/core/loaders/font-loader.php',
    'head-loader' => '/core/loaders/head-loader.php',
    'init_timezone' => '/core/utils/init_timezone.php',
    'inventory-list' => '/core/modules/inventory-list.php',
    'invoicing' => '/core/modules/invoicing.php',
    'journal-entries' => '/core/modules/journal-entries.php',
    'journal-entry-form' => '/fragments/forms/journal-entry-form.php',
    'login' => '/core/auth/login.php',
    'register' => '/core/auth/login.php',
    'logout' => '/core/auth/logout.php',
    'notifications' => '/core/pages/notifications.php',
    'orders' => '/core/modules/orders.php',
    'page-loader' => '/core/loaders/page-loader.php',
    'profile' => '/core/pages/profile.php',
    'projects' => '/core/pages/projects.php',
    'quotations' => '/core/modules/quotations.php',
    'settings' => '/core/pages/settings.php',
    'shipping' => '/core/modules/shipping.php',
    'system-alerts' => '/core/pages/system-alerts.php',
];

$authController = new AuthController();
$isLoggedIn = $authController->isLoggedIn();

$isHome = ($route === '');
$isKnownRoute = $isHome || isset($dispatchMap[$route]);

if (!$isKnownRoute) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');

    $redirectTo = $isLoggedIn ? '/dashboard' : '/login';
    $targetLabel = $isLoggedIn ? 'Dashboard' : 'Login';
    $redirectJs = json_encode($redirectTo, JSON_UNESCAPED_SLASHES);

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>'; 
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>404 Not Found</title>';
    echo '</head>';
    echo '<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; padding: 2rem; color: #0f172a; background: #ffffff;">';
    echo '<div style="max-width: 720px; margin: 0 auto;">';
    echo '<h1 style="margin: 0 0 0.5rem 0; font-size: 1.5rem;">404 - Page Not Found</h1>';
    echo '<p style="margin: 0 0 1rem 0; color: #475569;">The page you requested does not exist.</p>';
    echo '<a href="' . htmlspecialchars($redirectTo, ENT_QUOTES, 'UTF-8') . '" style="display: inline-block; padding: 0.625rem 1rem; border-radius: 10px; background: #0f172a; color: #ffffff; text-decoration: none;">Go to ' . htmlspecialchars($targetLabel, ENT_QUOTES, 'UTF-8') . '</a>';
    echo '<p style="margin: 1rem 0 0 0; color: #64748b; font-size: 0.875rem;">Redirecting…</p>';
    echo '</div>';
    echo '<script>setTimeout(function(){window.location.href=' . $redirectJs . ';}, 1400);</script>';
    echo '</body></html>';
    return;
}

$publicRoutes = [
    'login',
    'register',
    'logout',
    'page-loader',
    'css-loader',
    'font-loader',
    'head-loader',
    'body-loader',
    'asset',
    'init_timezone',
];

if (!$isLoggedIn && !$isHome && !in_array($route, $publicRoutes, true)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');

    $redirectTo = '/login';
    $targetLabel = 'Login';
    $redirectJs = json_encode($redirectTo, JSON_UNESCAPED_SLASHES);

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>404 Not Found</title>';
    echo '</head>';
    echo '<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; padding: 2rem; color: #0f172a; background: #ffffff;">';
    echo '<div style="max-width: 720px; margin: 0 auto;">';
    echo '<h1 style="margin: 0 0 0.5rem 0; font-size: 1.5rem;">404 - Page Not Found</h1>';
    echo '<p style="margin: 0 0 1rem 0; color: #475569;">The page you requested does not exist.</p>';
    echo '<a href="' . htmlspecialchars($redirectTo, ENT_QUOTES, 'UTF-8') . '" style="display: inline-block; padding: 0.625rem 1rem; border-radius: 10px; background: #0f172a; color: #ffffff; text-decoration: none;">Go to ' . htmlspecialchars($targetLabel, ENT_QUOTES, 'UTF-8') . '</a>';
    echo '<p style="margin: 1rem 0 0 0; color: #64748b; font-size: 0.875rem;">Redirecting…</p>';
    echo '</div>';
    echo '<script>setTimeout(function(){window.location.href=' . $redirectJs . ';}, 1400);</script>';
    echo '</body></html>';
    return;
}

if (!$isLoggedIn && $isHome) {
    header('Location: /login');
    exit;
}

if (!$isHome) {
    require __DIR__ . $dispatchMap[$route];
    return;
}

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
