<?php
/**
 * Analytics API - AJAX endpoint for analytics dashboard data
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
use App\Helper\CurrencyHelper;

$authController = new AuthController();
$authController->requireLogin();

$user = $authController->getCurrentUser();
$userId = $user['id'] ?? 'admin';

// Get action and time range
$action = $_GET['action'] ?? 'get_analytics';
$timeRange = $_GET['range'] ?? '30d';

// Validate time range
$validRanges = ['7d', '30d', '90d', '1y'];
if (!in_array($timeRange, $validRanges)) {
    $timeRange = '30d';
}

// Calculate date ranges
$rangeMap = [
    '7d' => 7,
    '30d' => 30,
    '90d' => 90,
    '1y' => 365
];
$daysBack = $rangeMap[$timeRange];
$startDate = new \DateTimeImmutable("-$daysBack days");
$endDate = new \DateTimeImmutable();

// Previous period for comparison
$prevStartDate = new \DateTimeImmutable("-" . ($daysBack * 2) . " days");
$prevEndDate = $startDate;

// Helper function
function isInDateRange($dateValue, $start, $end) {
    if (!$dateValue) return false;
    $ts = is_string($dateValue) ? strtotime($dateValue) : 
          (is_object($dateValue) && method_exists($dateValue, 'toDateTime') ? 
           $dateValue->toDateTime()->getTimestamp() : null);
    if (!$ts) return false;
    return $ts >= $start->getTimestamp() && $ts <= $end->getTimestamp();
}

try {
    switch ($action) {
        case 'get_analytics':
            // ============================================
            // INVENTORY ANALYTICS
            // ============================================
            $inventoryModel = new Inventory();
            $allItems = $inventoryModel->getAll();
            
            $totalItems = 0;
            $lowStockItems = 0;
            $outOfStockItems = 0;
            $inventoryValue = 0;
            $prevTotalItems = 0;
            
            foreach ($allItems as $item) {
                $createdDate = $item['created_at'] ?? $item['date'] ?? null;
                
                if (isInDateRange($createdDate, $startDate, $endDate)) {
                    $totalItems++;
                    $quantity = $item['quantity'] ?? 0;
                    $price = (float)($item['price'] ?? 0);
                    $inventoryValue += $quantity * $price;
                    
                    if ($quantity == 0) $outOfStockItems++;
                    elseif ($quantity <= 5) $lowStockItems++;
                }
                
                if (isInDateRange($createdDate, $prevStartDate, $prevEndDate)) {
                    $prevTotalItems++;
                }
            }
            
            $inventoryTrend = $prevTotalItems > 0 ? 
                round((($totalItems - $prevTotalItems) / $prevTotalItems) * 100, 1) : 0;
            
            // ============================================
            // SALES ANALYTICS
            // ============================================
            $invoiceModel = new Invoice();
            $quotationModel = new Quotation();
            $orderModel = new Order();
            
            $invoices = $invoiceModel->getAll();
            $totalRevenue = 0;
            $totalInvoices = 0;
            $prevRevenue = 0;
            
            foreach ($invoices as $inv) {
                $date = $inv['created_at'] ?? $inv['date'] ?? null;
                $amount = (float)($inv['total'] ?? 0);
                
                if (isInDateRange($date, $startDate, $endDate)) {
                    $totalRevenue += $amount;
                    $totalInvoices++;
                }
                
                if (isInDateRange($date, $prevStartDate, $prevEndDate)) {
                    $prevRevenue += $amount;
                }
            }
            
            $revenueTrend = $prevRevenue > 0 ? 
                round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : 0;
            
            // Orders
            $orders = $orderModel->getAll();
            $totalOrders = 0;
            $prevOrders = 0;
            
            foreach ($orders as $ord) {
                $date = $ord['created_at'] ?? $ord['date'] ?? null;
                
                if (isInDateRange($date, $startDate, $endDate)) {
                    $totalOrders++;
                }
                
                if (isInDateRange($date, $prevStartDate, $prevEndDate)) {
                    $prevOrders++;
                }
            }
            
            $ordersTrend = $prevOrders > 0 ? 
                round((($totalOrders - $prevOrders) / $prevOrders) * 100, 1) : 0;
            
            // Average order value
            $avgOrderValue = $totalInvoices > 0 ? $totalRevenue / $totalInvoices : 0;
            
            // ============================================
            // CHART DATA
            // ============================================
            
            // Inventory trend (last 12 data points)
            $inventoryTrendData = [];
            $dataPoints = $timeRange === '7d' ? 7 : ($timeRange === '30d' ? 15 : ($timeRange === '90d' ? 15 : 12));
            $interval = $daysBack / $dataPoints;
            
            for ($i = 0; $i < $dataPoints; $i++) {
                $pointDate = new \DateTimeImmutable("-" . round($daysBack - ($i * $interval)) . " days");
                $count = 0;
                
                foreach ($allItems as $item) {
                    $createdDate = $item['created_at'] ?? $item['date'] ?? null;
                    if ($createdDate && isInDateRange($createdDate, $pointDate, $endDate)) {
                        $count++;
                    }
                }
                
                $inventoryTrendData[] = [
                    'label' => $pointDate->format('M d'),
                    'value' => $count
                ];
            }
            
            // Revenue trend
            $revenueTrendData = [];
            for ($i = 0; $i < $dataPoints; $i++) {
                $pointDate = new \DateTimeImmutable("-" . round($daysBack - ($i * $interval)) . " days");
                $revenue = 0;
                
                foreach ($invoices as $inv) {
                    $date = $inv['created_at'] ?? $inv['date'] ?? null;
                    if ($date && isInDateRange($date, $pointDate, $endDate)) {
                        $revenue += (float)($inv['total'] ?? 0);
                    }
                }
                
                $revenueTrendData[] = [
                    'label' => $pointDate->format('M d'),
                    'value' => round($revenue, 2)
                ];
            }
            
            // Category distribution
            $categoryData = [];
            $categoryMap = [];
            foreach ($allItems as $item) {
                $category = $item['type'] ?? 'General';
                $categoryMap[$category] = ($categoryMap[$category] ?? 0) + 1;
            }
            
            foreach ($categoryMap as $cat => $count) {
                $categoryData[] = [
                    'category' => $cat,
                    'count' => $count
                ];
            }
            
            // Range labels
            $rangeLabels = [
                '7d' => '7 Days',
                '30d' => '30 Days',
                '90d' => '90 Days',
                '1y' => 'Year'
            ];
            
            echo json_encode([
                'success' => true,
                'time_range' => $timeRange,
                'time_range_label' => $rangeLabels[$timeRange] ?? $timeRange,
                'kpis' => [
                    'inventory' => [
                        'total' => $totalItems,
                        'trend' => $inventoryTrend,
                        'value' => $inventoryValue,
                        'value_formatted' => CurrencyHelper::format($inventoryValue),
                        'low_stock' => $lowStockItems,
                        'out_of_stock' => $outOfStockItems
                    ],
                    'revenue' => [
                        'total' => $totalRevenue,
                        'total_formatted' => CurrencyHelper::format($totalRevenue),
                        'trend' => $revenueTrend
                    ],
                    'orders' => [
                        'total' => $totalOrders,
                        'trend' => $ordersTrend,
                        'avg_value' => $avgOrderValue,
                        'avg_value_formatted' => CurrencyHelper::format($avgOrderValue)
                    ],
                    'invoices' => [
                        'total' => $totalInvoices
                    ]
                ],
                'charts' => [
                    'inventory_trend' => $inventoryTrendData,
                    'revenue_trend' => $revenueTrendData,
                    'category_distribution' => $categoryData
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
