<?php
/**
 * Modern ERP Dashboard - LedgerSMB Features
 * Mind-blowing UI with shadcn/ui design
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Inventory;
use App\Helper\CurrencyHelper;

$authController = new AuthController();
$authController->requireLogin();

$user = $authController->getCurrentUser();
$inventoryModel = new Inventory();

// Get statistics
try {
    $totalItems = $inventoryModel->count();
    $lowStockItems = $inventoryModel->getLowStockCount();
    $outOfStockItems = $inventoryModel->getOutOfStockCount();
    $recentItems = $inventoryModel->getRecentItems(10);
} catch (Exception $e) {
    $totalItems = 0;
    $lowStockItems = 0;
    $outOfStockItems = 0;
    $recentItems = [];
}

$pageTitle = 'ERP Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Modern ERP</title>
    <link rel="stylesheet" href="assets/css/modern.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; }
        
        /* Layout */
        .app-layout {
            display: flex;
            min-height: 100vh;
            background: hsl(var(--background));
        }
        
        /* Modern Sidebar */
        .modern-sidebar {
            width: 16rem;
            background: hsl(var(--sidebar-background));
            border-right: 1px solid hsl(var(--sidebar-border));
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 50;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid hsl(var(--sidebar-border));
        }
        
        .sidebar-logo {
            font-size: 1.25rem;
            font-weight: 700;
            color: hsl(var(--sidebar-primary));
        }
        
        .sidebar-subtitle {
            font-size: 0.75rem;
            color: hsl(var(--muted-foreground));
        }
        
        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu-item {
            margin-bottom: 0.25rem;
        }
        
        .sidebar-menu-button {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            width: 100%;
            padding: 0.625rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: hsl(var(--sidebar-foreground));
            transition: all 150ms;
            border: none;
            background: transparent;
            cursor: pointer;
            text-align: left;
        }
        
        .sidebar-menu-button:hover {
            background: hsl(var(--sidebar-accent));
            color: hsl(var(--sidebar-accent-foreground));
        }
        
        .sidebar-menu-button.active {
            background: hsl(var(--sidebar-accent));
            color: hsl(var(--sidebar-accent-foreground));
            font-weight: 600;
        }
        
        .sidebar-icon {
            width: 1rem;
            height: 1rem;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
        }
        
        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid hsl(var(--sidebar-border));
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 16rem;
            display: flex;
            flex-direction: column;
        }
        
        /* Top Header */
        .top-header {
            background: hsl(var(--background));
            border-bottom: 1px solid hsl(var(--border));
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 40;
        }
        
        .header-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: hsl(var(--foreground));
        }
        
        .header-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        
        .user-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: hsl(var(--muted));
            border-radius: 9999px;
            font-size: 0.875rem;
        }
        
        .user-avatar {
            width: 2rem;
            height: 2rem;
            border-radius: 9999px;
            background: hsl(var(--primary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        /* Content Area */
        .content-area {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: hsl(var(--card));
            border: 1px solid hsl(var(--border));
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: hsl(var(--muted-foreground));
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: hsl(var(--foreground));
        }
        
        .stat-change {
            font-size: 0.75rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .stat-change.positive { color: #22c55e; }
        .stat-change.negative { color: #ef4444; }
        
        /* Section */
        .section {
            margin-bottom: 2rem;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            justify-content: between;
            margin-bottom: 1rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: hsl(var(--foreground));
        }
        
        /* Chart Placeholder */
        .chart-container {
            background: hsl(var(--card));
            border: 1px solid hsl(var(--border));
            border-radius: 0.75rem;
            padding: 1.5rem;
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: hsl(var(--muted-foreground));
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Modern Sidebar -->
        <aside class="modern-sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">LedgerSMB ERP</div>
                <div class="sidebar-subtitle">Enterprise Management</div>
            </div>
            
            <div class="sidebar-content">
                <ul class="sidebar-menu">
                    <li class="sidebar-menu-item">
                        <button class="sidebar-menu-button active">
                            <svg class="sidebar-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            Dashboard
                        </button>
                    </li>
                    <li class="sidebar-menu-item">
                        <button class="sidebar-menu-button">
                            <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
                            Inventory
                        </button>
                    </li>
                    <li class="sidebar-menu-item">
                        <button class="sidebar-menu-button">
                            <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z"/><polyline points="14 2 14 8 20 8"/></svg>
                            Quotations
                        </button>
                    </li>
                    <li class="sidebar-menu-item">
                        <button class="sidebar-menu-button">
                            <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
                            Invoicing
                        </button>
                    </li>
                    <li class="sidebar-menu-item">
                        <button class="sidebar-menu-button">
                            <svg class="sidebar-icon" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5C16 3.89543 15.1046 3 14 3H10C8.89543 3 8 3.89543 8 5V21"/></svg>
                            Projects
                        </button>
                    </li>
                    <li class="sidebar-menu-item">
                        <button class="sidebar-menu-button">
                            <svg class="sidebar-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            Timecards
                        </button>
                    </li>
                    <li class="sidebar-menu-item">
                        <button class="sidebar-menu-button">
                            <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M20 7H4C2.89543 7 2 7.89543 2 9V19C2 20.1046 2.89543 21 4 21H20C21.1046 21 22 20.1046 22 19V9C22 7.89543 21.1046 7 20 7Z"/><path d="M16 7V5C16 3.89543 15.1046 3 14 3H10C8.89543 3 8 3.89543 8 5V7"/></svg>
                            Orders
                        </button>
                    </li>
                    <li class="sidebar-menu-item">
                        <button class="sidebar-menu-button">
                            <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Shipping
                        </button>
                    </li>
                    <li class="sidebar-menu-item">
                        <button class="sidebar-menu-button">
                            <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M12 20H21M3 20H5M5 20V4M5 20H12M5 4H3M5 4H12M12 4H21M12 4V20M12 12H21M5 12H3"/></svg>
                            Accounting
                        </button>
                    </li>
                    <li class="sidebar-menu-item">
                        <button class="sidebar-menu-button">
                            <svg class="sidebar-icon" viewBox="0 0 24 24"><path d="M9 17H7V12H9V17ZM13 17H11V7H13V17ZM17 17H15V14H17V17ZM19 19H5V5H19V19ZM19 3H5C3.89543 3 3 3.89543 3 5V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V5C21 3.89543 20.1046 3 19 3Z"/></svg>
                            Reports
                        </button>
                    </li>
                </ul>
            </div>
            
            <div class="sidebar-footer">
                <button class="sidebar-menu-button">
                    <svg class="sidebar-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15C19.2 15.7 19.1 16.4 19.1 17.1C19.1 17.8 19.2 18.5 19.4 19.2L21.5 20.8C21.7 21 21.7 21.3 21.6 21.5L19.6 24.9C19.5 25.1 19.3 25.2 19 25.1L16.6 24.2C16 24.7 15.4 25.1 14.7 25.4L14.4 28C14.3 28.2 14.1 28.4 13.9 28.4H10C9.8 28.4 9.6 28.2 9.5 28L9.2 25.3C8.5 25 7.9 24.6 7.3 24.1L4.9 25C4.7 25.1 4.4 25 4.3 24.8L2.3 21.4C2.2 21.2 2.2 20.9 2.4 20.7L4.5 19.1C4.4 18.4 4.3 17.7 4.3 17C4.3 16.3 4.4 15.6 4.5 14.9L2.4 13.3C2.2 13.1 2.2 12.8 2.3 12.6L4.3 9.2C4.4 9 4.6 8.9 4.9 9L7.3 9.9C7.9 9.4 8.5 9 9.2 8.7L9.5 6C9.6 5.8 9.8 5.6 10 5.6H14C14.2 5.6 14.4 5.8 14.5 6L14.8 8.7C15.5 9 16.1 9.4 16.7 9.9L19.1 9C19.3 8.9 19.6 9 19.7 9.2L21.7 12.6C21.8 12.8 21.8 13.1 21.6 13.3L19.5 14.9C19.6 15.3 19.7 15.7 19.7 16.1C19.7 16.4 19.6 16.8 19.5 17.2L21.6 18.8Z"/></svg>
                    Settings
                </button>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <h1 class="header-title">Dashboard</h1>
                <div class="header-actions">
                    <div class="user-badge">
                        <div class="user-avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></div>
                        <span><?php echo htmlspecialchars($user['username']); ?></span>
                        <span class="badge-modern badge-modern-secondary"><?php echo ucfirst($user['role']); ?></span>
                    </div>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Inventory</div>
                        <div class="stat-value"><?php echo number_format($totalItems); ?></div>
                        <div class="stat-change positive">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <polyline points="18 15 12 9 6 15"/>
                            </svg>
                            <span>12% from last month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-label">Low Stock Items</div>
                        <div class="stat-value" style="color: #f59e0b;"><?php echo number_format($lowStockItems); ?></div>
                        <div class="stat-change negative">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                            <span>Needs attention</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-label">Active Orders</div>
                        <div class="stat-value">24</div>
                        <div class="stat-change positive">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <polyline points="18 15 12 9 6 15"/>
                            </svg>
                            <span>8% increase</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-label">Revenue (Month)</div>
                        <div class="stat-value"><?php echo CurrencyHelper::format(45231, 0); ?></div>
                        <div class="stat-change positive">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <polyline points="18 15 12 9 6 15"/>
                            </svg>
                            <span>23% from last month</span>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">Recent Inventory Activity</h2>
                    </div>
                    
                    <div class="modern-card">
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Barcode</th>
                                    <th>Type</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($recentItems, 0, 5) as $item): 
                                    $quantity = $item['quantity'] ?? 0;
                                    $isLow = $quantity > 0 && $quantity <= 5;
                                    $isOut = $quantity == 0;
                                ?>
                                <tr>
                                    <td class="font-medium"><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><code><?php echo htmlspecialchars($item['barcode'] ?? 'N/A'); ?></code></td>
                                    <td><?php echo htmlspecialchars($item['type'] ?? '-'); ?></td>
                                    <td class="font-semibold"><?php echo $quantity; ?></td>
                                    <td>
                                        <?php if ($isOut): ?>
                                            <span class="badge-modern badge-modern-destructive">Out of Stock</span>
                                        <?php elseif ($isLow): ?>
                                            <span class="badge-modern badge-modern-secondary">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge-modern badge-modern-default">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted-foreground">
                                        <?php echo isset($item['date_added']) ? $item['date_added']->toDateTime()->format('M d, Y') : 'N/A'; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- LedgerSMB Features Overview -->
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">LedgerSMB ERP Features</h2>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-6">
                        <div class="modern-card">
                            <div class="modern-card-header">
                                <h3 class="font-semibold">Quotations & Orders</h3>
                            </div>
                            <div class="modern-card-content">
                                <p class="text-sm text-muted-foreground mb-4">Create quotes, convert to orders, track fulfillment.</p>
                                <button class="btn-modern btn-modern-primary w-full">Create Quote</button>
                            </div>
                        </div>
                        
                        <div class="modern-card">
                            <div class="modern-card-header">
                                <h3 class="font-semibold">Invoicing & Billing</h3>
                            </div>
                            <div class="modern-card-content">
                                <p class="text-sm text-muted-foreground mb-4">Generate invoices, track payments, send via email.</p>
                                <button class="btn-modern btn-modern-primary w-full">New Invoice</button>
                            </div>
                        </div>
                        
                        <div class="modern-card">
                            <div class="modern-card-header">
                                <h3 class="font-semibold">Project Management</h3>
                            </div>
                            <div class="modern-card-content">
                                <p class="text-sm text-muted-foreground mb-4">Track projects, timecards, and profitability.</p>
                                <button class="btn-modern btn-modern-primary w-full">View Projects</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
