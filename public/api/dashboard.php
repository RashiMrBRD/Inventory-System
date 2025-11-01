<?php
/**
 * Dashboard API - AJAX endpoint for dashboard data
 * Returns JSON data without page refresh
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
$startDate = new \DateTimeImmutable("-$daysBack days");
$endDate = new \DateTimeImmutable();

// Helper function
function isDashboardInRange($dateValue, $start, $end) {
    if (!$dateValue) return false;
    $ts = is_string($dateValue) ? strtotime($dateValue) : 
          (is_object($dateValue) && method_exists($dateValue, 'toDateTime') ? 
           $dateValue->toDateTime()->getTimestamp() : null);
    if (!$ts) return false;
    return $ts >= $start->getTimestamp() && $ts <= $end->getTimestamp();
}

try {
    switch ($action) {
        case 'get_stats':
            // ============================================
            // INVENTORY MODULE
            // ============================================
            $inventoryModel = new Inventory();
            $totalItems = $inventoryModel->count();
            $lowStockItems = $inventoryModel->getLowStockCount();
            $outOfStockItems = $inventoryModel->getOutOfStockCount();
            $recentItems = $inventoryModel->getRecentItems(5);
            $addedToday = $inventoryModel->countAddedSince(new \DateTimeImmutable('-24 hours'));
            
            // ============================================
            // SALES & OPERATIONS (Time-filtered)
            // ============================================
            $invoiceModel = new Invoice();
            $quotationModel = new Quotation();
            $orderModel = new Order();
            $projectModel = new Project();
            $shipmentModel = new Shipment();
            
            // Quotations - Show ALL quotations (not time-filtered)
            $quotations = $quotationModel->getAll();
            $totalQuotations = count($quotations);
            $pendingQuotations = 0;
            $quotationsInPeriod = 0;
            foreach ($quotations as $q) {
                if (($q['status'] ?? '') === 'pending') {
                    $pendingQuotations++;
                }
                // Count in period for trends
                $date = $q['created_at'] ?? $q['date'] ?? null;
                if (isDashboardInRange($date, $startDate, $endDate)) {
                    $quotationsInPeriod++;
                }
            }
            
            // Invoices - Show ALL invoices (not time-filtered)
            $invoices = $invoiceModel->getAll();
            $totalInvoices = count($invoices);
            $pendingInvoices = 0;
            $totalRevenue = 0;
            $revenueInPeriod = 0;
            foreach ($invoices as $inv) {
                $total = (float)($inv['total'] ?? 0);
                $totalRevenue += $total;
                
                if (($inv['status'] ?? '') === 'pending') {
                    $pendingInvoices++;
                }
                
                // Count in period for trends
                $date = $inv['created_at'] ?? $inv['date'] ?? null;
                if (isDashboardInRange($date, $startDate, $endDate)) {
                    $revenueInPeriod += $total;
                }
            }
            
            // Orders - Show ALL orders (not time-filtered)
            $orders = $orderModel->getAll();
            $totalOrders = count($orders);
            $pendingOrders = 0;
            $ordersInPeriod = 0;
            foreach ($orders as $ord) {
                if (($ord['status'] ?? '') === 'pending') {
                    $pendingOrders++;
                }
                // Count in period for trends
                $date = $ord['created_at'] ?? $ord['date'] ?? null;
                if (isDashboardInRange($date, $startDate, $endDate)) {
                    $ordersInPeriod++;
                }
            }
            
            // Projects
            $projects = $projectModel->getAll();
            $activeProjects = 0;
            foreach ($projects as $proj) {
                if (($proj['status'] ?? '') === 'active') $activeProjects++;
            }
            
            // Shipments
            $shipments = $shipmentModel->getAll();
            $inTransitShipments = 0;
            foreach ($shipments as $ship) {
                if (($ship['status'] ?? '') === 'in_transit') $inTransitShipments++;
            }
            
            // ============================================
            // COMPLIANCE (BIR & FDA)
            // ============================================
            try {
                $birModel = new BirForm();
                $fdaModel = new FdaProduct();
                
                // BirForm: getRecentForms() returns recent forms
                $birForms = $birModel->getRecentForms(50);
                $activeBir = count($birForms);
                $pendingBir = count(array_filter($birForms, fn($f) => ($f['status'] ?? '') === 'pending'));
                
                // FdaProduct: countActive() for active, getExpiringProducts() for expiring
                $activeFda = $fdaModel->countActive();
                $expiringFdaProducts = $fdaModel->getExpiringProducts(30);
                $expiringSoonFda = count($expiringFdaProducts);
            } catch (Exception $e) {
                $activeBir = 0;
                $pendingBir = 0;
                $activeFda = 0;
                $expiringSoonFda = 0;
            }
            
            // Format recent items
            $formattedRecentItems = array_map(function($item) {
                return [
                    'id' => (string)($item['_id'] ?? ''),
                    'name' => $item['name'] ?? 'Unnamed',
                    'quantity' => $item['quantity'] ?? 0,
                    'price' => CurrencyHelper::format((float)($item['price'] ?? 0)),
                    'date_added' => isset($item['date_added']) ? 
                        $item['date_added']->toDateTime()->format('M d, Y') : 'N/A'
                ];
            }, $recentItems);
            
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
                    'total_items' => $totalItems,
                    'low_stock' => $lowStockItems,
                    'out_of_stock' => $outOfStockItems,
                    'added_today' => $addedToday,
                    'recent_items' => $formattedRecentItems
                ],
                'sales' => [
                    'quotations' => [
                        'total' => $totalQuotations,
                        'pending' => $pendingQuotations
                    ],
                    'invoices' => [
                        'total' => $totalInvoices,
                        'pending' => $pendingInvoices,
                        'revenue' => $totalRevenue,
                        'revenue_formatted' => CurrencyHelper::format($totalRevenue)
                    ],
                    'orders' => [
                        'total' => $totalOrders,
                        'pending' => $pendingOrders
                    ]
                ],
                'operations' => [
                    'projects' => [
                        'active' => $activeProjects
                    ],
                    'shipments' => [
                        'in_transit' => $inTransitShipments
                    ]
                ],
                'compliance' => [
                    'bir' => [
                        'active' => $activeBir,
                        'pending' => $pendingBir ?? 0
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
