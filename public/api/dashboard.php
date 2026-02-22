<?php
/**
 * Dashboard API - AJAX endpoint for dashboard data
 * Returns JSON data without page refresh
 * Uses MongoDB aggregation for efficient large dataset handling
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Inventory;
use App\Model\Invoice;
use App\Model\Quotation;
use App\Model\Order;
use App\Model\Project;
use App\Model\Shipment;
use App\Model\BirForm;
use App\Model\FdaProduct;
use App\Helper\CurrencyHelper;

$authController = new AuthController();
$authController->requireLogin();

$user = $authController->getCurrentUser();
$userId = $user['id'] ?? 'admin';

// Get action and time range
$action = $_GET['action'] ?? 'get_stats';
$timeRange = $_GET['range'] ?? '30d';

// Validate time range
$validRanges = ['7d', '30d', '90d', 'month', 'quarter', 'year'];
if (!in_array($timeRange, $validRanges)) {
    $timeRange = '30d';
}

// Calculate date ranges
$rangeMap = [
    '7d' => 7,
    '30d' => 30,
    '90d' => 90,
    'month' => 30,
    'quarter' => 90,
    'year' => 365
];
$daysBack = $rangeMap[$timeRange];

try {
    switch ($action) {
        case 'get_stats':
            // ============================================
            // INVENTORY MODULE - Use aggregation
            // ============================================
            $inventoryModel = new Inventory();
            $inventoryStats = $inventoryModel->getDashboardAnalytics($daysBack);
            $dailyCounts = $inventoryModel->getDailyAddedCounts($daysBack);
            
            // ============================================
            // SALES MODULE - Use aggregation
            // ============================================
            $invoiceModel = new Invoice();
            $invoiceStats = $invoiceModel->getDashboardAnalytics($daysBack);
            
            $quotationModel = new Quotation();
            $quotationStats = $quotationModel->getDashboardAnalytics($daysBack);
            
            // ============================================
            // ORDERS - Use aggregation (simpler)
            // ============================================
            $orderModel = new Order();
            $orderStats = $orderModel->getDashboardAnalytics($daysBack);
            
            // ============================================
            // OPERATIONS - Use aggregation
            // ============================================
            $projectModel = new Project();
            $projectStats = $projectModel->getDashboardAnalytics($daysBack);
            
            $shipmentModel = new Shipment();
            $shipmentStats = $shipmentModel->getDashboardAnalytics($daysBack);
            
            // ============================================
            // COMPLIANCE (BIR & FDA) - Limited data
            // ============================================
            try {
                $birModel = new BirForm();
                $fdaModel = new FdaProduct();
                
                // Use limited queries
                $birForms = $birModel->getRecentForms(50);
                $activeBir = count($birForms);
                $pendingBir = count(array_filter($birForms, fn($f) => ($f['status'] ?? '') === 'pending'));
                
                $activeFda = $fdaModel->countActive();
                $expiringSoonFda = count($fdaModel->getExpiringProducts(30));
            } catch (Exception $e) {
                $activeBir = 0;
                $pendingBir = 0;
                $activeFda = 0;
                $expiringSoonFda = 0;
            }
            
            // Build inventory trend data
            $inventoryTrendLabels = [];
            $inventoryTrendData = [];
            $dataPoints = min($daysBack, 30);
            $interval = max(1, floor($daysBack / $dataPoints));
            
            for ($i = $daysBack - 1; $i >= 0; $i -= $interval) {
                $d = new \DateTimeImmutable("-$i days");
                $key = $d->format('Y-m-d');
                $inventoryTrendLabels[] = $d->format($timeRange === 'year' ? 'M' : 'M j');
                $inventoryTrendData[] = (int)($dailyCounts[$key] ?? 0);
            }
            
            // Build revenue trend data
            $revenueByPeriod = $invoiceStats['revenue_by_period'] ?? [];
            $revenueTrendLabels = [];
            $revenueTrendData = [];
            
            for ($i = $daysBack - 1; $i >= 0; $i -= $interval) {
                $d = new \DateTimeImmutable("-$i days");
                $key = $d->format('Y-m-d');
                $revenueTrendLabels[] = $d->format($timeRange === 'year' ? 'M' : 'M j');
                $revenueTrendData[] = (float)($revenueByPeriod[$key] ?? 0);
            }
            
            // Range labels
            $rangeLabels = [
                '7d' => '7 Days',
                '30d' => '30 Days',
                '90d' => '90 Days',
                'month' => 'Month',
                'quarter' => 'Quarter',
                'year' => 'Year'
            ];
            
            echo json_encode([
                'success' => true,
                'time_range' => $timeRange,
                'time_range_label' => $rangeLabels[$timeRange] ?? $timeRange,
                'inventory' => [
                    'total_items' => $inventoryStats['total_items'],
                    'total_quantity' => $inventoryStats['total_quantity'],
                    'total_value' => $inventoryStats['total_value'],
                    'total_value_formatted' => CurrencyHelper::format($inventoryStats['total_value']),
                    'low_stock' => $inventoryStats['low_stock_count'],
                    'out_of_stock' => $inventoryStats['out_of_stock_count'],
                    'items_added_this_period' => $inventoryStats['items_added_this_period'],
                    'trend' => $inventoryStats['trend'],
                    'type_distribution' => $inventoryStats['type_distribution'],
                    'stock_levels' => $inventoryStats['stock_levels'],
                    'trend_labels' => $inventoryTrendLabels,
                    'trend_data' => $inventoryTrendData
                ],
                'sales' => [
                    'invoices' => [
                        'total' => $invoiceStats['invoice_count'],
                        'total_revenue' => $invoiceStats['total_revenue'],
                        'total_revenue_formatted' => CurrencyHelper::format($invoiceStats['total_revenue']),
                        'paid_revenue' => $invoiceStats['paid_revenue'],
                        'outstanding_revenue' => $invoiceStats['outstanding_revenue'],
                        'revenue_in_period' => $invoiceStats['revenue_in_period'],
                        'revenue_trend' => $invoiceStats['revenue_trend'],
                        'collection_rate' => $invoiceStats['collection_rate'],
                        'collection_trend' => $invoiceStats['collection_trend']
                    ],
                    'quotations' => [
                        'total' => $quotationStats['total_count'],
                        'approved' => $quotationStats['approved_count'],
                        'pending' => $quotationStats['pending_count'],
                        'converted' => $quotationStats['converted_count'],
                        'total_value' => $quotationStats['total_value'],
                        'conversion_rate' => $quotationStats['conversion_rate'],
                        'conversion_trend' => $quotationStats['conversion_trend']
                    ],
                    'orders' => [
                        'total' => $orderStats['total_count'] ?? 0,
                        'pending' => $orderStats['pending_count'] ?? 0,
                        'by_type' => $orderStats['by_type'] ?? []
                    ],
                    'revenue_trend_labels' => $revenueTrendLabels,
                    'revenue_trend_data' => $revenueTrendData
                ],
                'operations' => [
                    'projects' => [
                        'total' => $projectStats['total_count'] ?? 0,
                        'active' => $projectStats['active_count'] ?? 0,
                        'by_status' => $projectStats['by_status'] ?? [],
                        'total_budget' => $projectStats['total_budget'] ?? 0,
                        'total_spent' => $projectStats['total_spent'] ?? 0,
                        'budget_utilization' => $projectStats['budget_utilization'] ?? 0
                    ],
                    'shipments' => [
                        'total' => $shipmentStats['total_count'] ?? 0,
                        'in_transit' => $shipmentStats['in_transit_count'] ?? 0,
                        'by_status' => $shipmentStats['by_status'] ?? []
                    ]
                ],
                'compliance' => [
                    'bir' => [
                        'active' => $activeBir,
                        'pending' => $pendingBir
                    ],
                    'fda' => [
                        'active' => $activeFda,
                        'expiring_soon' => $expiringSoonFda
                    ]
                ]
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
