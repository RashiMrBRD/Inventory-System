<?php
/**
 * Chart of Accounts API - AJAX endpoint
 * Returns JSON data without page refresh
 */

header('Content-Type: application/json');

session_start();

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AccountingController;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$accountingController = new AccountingController();
$user = $_SESSION['user_id'];

// Get action
$action = $_GET['action'] ?? 'get_accounts';

// Get currency settings
$currency = $_SESSION['currency'] ?? 'PHP';
$currencySymbols = [
    'PHP' => '₱',
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'JPY' => '¥',
    'CNY' => '¥',
    'KRW' => '₩',
    'MYR' => 'RM',
    'SGD' => 'S$',
    'THB' => '฿',
    'IDR' => 'Rp',
    'VND' => '₫',
    'INR' => '₹',
    'AUD' => 'A$',
    'CAD' => 'C$'
];
$currencySymbol = $currencySymbols[$currency] ?? $currency . ' ';

// Helper function
function formatMoney($amount, $symbol) {
    return $symbol . number_format($amount, 2);
}

try {
    switch ($action) {
        case 'search':
            // Dedicated search action
            $searchQuery = $_GET['search'] ?? '';
            if (!empty($searchQuery)) {
                $accounts = $chartOfAccountsModel->search($searchQuery);
                echo json_encode(['success' => true, 'data' => $accounts]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Search query required']);
            }
            break;
            
        case 'get_accounts':
            // Get filter parameters
            $viewMode = $_GET['view'] ?? 'grouped';
            $typeFilter = $_GET['type'] ?? 'all';
            $subtypeFilter = $_GET['subtype'] ?? 'all';
            $statusFilter = $_GET['status'] ?? 'all';
            $searchQuery = $_GET['search'] ?? '';
            $sortBy = $_GET['sort'] ?? 'account_code';
            $sortOrder = $_GET['order'] ?? 'asc';
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = intval($_GET['per_page'] ?? 25);
            
            // Get all accounts
            $result = $accountingController->getAccountHierarchy();
            $accountHierarchy = $result['success'] ? $result['data'] : [];
            
            // Flatten accounts for filtering
            $allAccounts = [];
            foreach ($accountHierarchy as $type => $accounts) {
                foreach ($accounts as $account) {
                    $account['account_type'] = $type;
                    $allAccounts[] = $account;
                }
            }
            
            // Apply filters
            $filteredAccounts = array_filter($allAccounts, function($account) use ($typeFilter, $subtypeFilter, $statusFilter, $searchQuery) {
                // Type filter
                if ($typeFilter !== 'all' && $account['account_type'] !== $typeFilter) {
                    return false;
                }
                
                // Subtype filter
                if ($subtypeFilter !== 'all' && ($account['account_subtype'] ?? '') !== $subtypeFilter) {
                    return false;
                }
                
                // Status filter
                if ($statusFilter !== 'all') {
                    $isActive = $account['is_active'] ?? true;
                    if ($statusFilter === 'active' && !$isActive) return false;
                    if ($statusFilter === 'inactive' && $isActive) return false;
                }
                
                // Search filter
                if ($searchQuery) {
                    $searchLower = strtolower($searchQuery);
                    $codeLower = strtolower($account['account_code']);
                    $nameLower = strtolower($account['account_name']);
                    if (strpos($codeLower, $searchLower) === false && strpos($nameLower, $searchLower) === false) {
                        return false;
                    }
                }
                
                return true;
            });
            
            // Sort accounts
            usort($filteredAccounts, function($a, $b) use ($sortBy, $sortOrder) {
                if ($sortBy === 'account_code') {
                    $aVal = $a['account_code'];
                    $bVal = $b['account_code'];
                } elseif ($sortBy === 'account_name') {
                    $aVal = $a['account_name'];
                    $bVal = $b['account_name'];
                } elseif ($sortBy === 'account_type') {
                    $aVal = $a['account_type'] ?? '';
                    $bVal = $b['account_type'] ?? '';
                } elseif ($sortBy === 'account_subtype') {
                    $aVal = $a['account_subtype'] ?? '';
                    $bVal = $b['account_subtype'] ?? '';
                } elseif ($sortBy === 'balance') {
                    $aVal = $a['balance'] ?? 0;
                    $bVal = $b['balance'] ?? 0;
                } elseif ($sortBy === 'is_active') {
                    $aVal = $a['is_active'] ?? true ? 1 : 0;
                    $bVal = $b['is_active'] ?? true ? 1 : 0;
                } else {
                    $aVal = $a[$sortBy] ?? '';
                    $bVal = $b[$sortBy] ?? '';
                }
                
                $comparison = $aVal <=> $bVal;
                return $sortOrder === 'desc' ? -$comparison : $comparison;
            });
            
            // Pagination
            $totalAccounts = count($filteredAccounts);
            $totalPages = ceil($totalAccounts / $perPage);
            $page = min($page, max(1, $totalPages));
            $offset = ($page - 1) * $perPage;
            $accounts = array_slice($filteredAccounts, $offset, $perPage);
            
            // Calculate stats
            $stats = [
                'total' => $totalAccounts,
                'active' => count(array_filter($allAccounts, fn($a) => $a['is_active'] ?? true)),
                'inactive' => count(array_filter($allAccounts, fn($a) => !($a['is_active'] ?? true))),
                'assets' => count($accountHierarchy['asset'] ?? []),
                'liabilities' => count($accountHierarchy['liability'] ?? []),
                'equity' => count($accountHierarchy['equity'] ?? []),
                'total_asset_balance' => array_sum(array_column($accountHierarchy['asset'] ?? [], 'balance')),
                'total_liability_balance' => array_sum(array_column($accountHierarchy['liability'] ?? [], 'balance')),
                'total_equity_balance' => array_sum(array_column($accountHierarchy['equity'] ?? [], 'balance')),
            ];
            
            // Format accounts for display
            $formattedAccounts = array_map(function($account) use ($currencySymbol) {
                return [
                    'account_code' => $account['account_code'],
                    'account_name' => $account['account_name'],
                    'account_type' => $account['account_type'],
                    'account_subtype' => $account['account_subtype'] ?? '',
                    'balance' => $account['balance'] ?? 0,
                    'balance_formatted' => formatMoney($account['balance'] ?? 0, $currencySymbol),
                    'is_active' => $account['is_active'] ?? true,
                    'description' => $account['description'] ?? ''
                ];
            }, $accounts);
            
            // Format stats
            $formattedStats = [
                'total' => $stats['total'],
                'active' => $stats['active'],
                'inactive' => $stats['inactive'],
                'assets' => $stats['assets'],
                'liabilities' => $stats['liabilities'],
                'equity' => $stats['equity'],
                'total_asset_balance' => formatMoney($stats['total_asset_balance'], $currencySymbol),
                'total_liability_balance' => formatMoney($stats['total_liability_balance'], $currencySymbol),
                'total_equity_balance' => formatMoney($stats['total_equity_balance'], $currencySymbol),
            ];
            
            echo json_encode([
                'success' => true,
                'accounts' => $formattedAccounts,
                'hierarchy' => $accountHierarchy,
                'stats' => $formattedStats,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'per_page' => $perPage,
                    'total_items' => $totalAccounts,
                    'showing_from' => $totalAccounts > 0 ? $offset + 1 : 0,
                    'showing_to' => min($offset + $perPage, $totalAccounts)
                ],
                'filters' => [
                    'view' => $viewMode,
                    'type' => $typeFilter,
                    'subtype' => $subtypeFilter,
                    'status' => $statusFilter,
                    'search' => $searchQuery,
                    'sort' => $sortBy,
                    'order' => $sortOrder
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
