<?php
/**
 * Journal Entries API - AJAX endpoint
 * Returns JSON data without page refresh
 */

header('Content-Type: application/json');

session_start();

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AccountingController;
use App\Model\JournalEntry;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$accountingController = new AccountingController();
$journalEntryModel = new JournalEntry();

// Get action
$action = $_GET['action'] ?? 'get_entries';

// Get currency settings
$currency = $_SESSION['currency'] ?? 'PHP';
$currencySymbols = [
    'PHP' => '₱', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥',
    'CNY' => '¥', 'KRW' => '₩', 'MYR' => 'RM', 'SGD' => 'S$', 'THB' => '฿',
    'IDR' => 'Rp', 'VND' => '₫', 'INR' => '₹', 'AUD' => 'A$', 'CAD' => 'C$'
];
$currencySymbol = $currencySymbols[$currency] ?? $currency . ' ';

function formatMoney($amount, $symbol) {
    return $symbol . number_format($amount, 2);
}

try {
    switch ($action) {
        case 'search':
            // Dedicated search action
            $searchQuery = $_GET['search'] ?? '';
            if (!empty($searchQuery)) {
                $entries = $journalEntryModel->search($searchQuery);
                echo json_encode(['success' => true, 'data' => $entries]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Search query required']);
            }
            break;
            
        case 'get_entries':
            // Get filter parameters
            $typeFilter = $_GET['type'] ?? 'all';
            $statusFilter = $_GET['status'] ?? 'all';
            $searchQuery = $_GET['search'] ?? '';
            $startDate = $_GET['start_date'] ?? '';
            $endDate = $_GET['end_date'] ?? '';
            $sortBy = $_GET['sort'] ?? 'entry_date';
            $sortOrder = $_GET['order'] ?? 'desc';
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = intval($_GET['per_page'] ?? 25);
            
            // Get all entries
            $entries = $journalEntryModel->getAllEntries();
            
            // Apply filters
            $filteredEntries = array_filter($entries, function($entry) use ($typeFilter, $statusFilter, $searchQuery, $startDate, $endDate) {
                if ($typeFilter !== 'all' && ($entry['entry_type'] ?? '') !== $typeFilter) return false;
                if ($statusFilter !== 'all' && ($entry['status'] ?? '') !== $statusFilter) return false;
                
                if ($searchQuery) {
                    $search = strtolower($searchQuery);
                    $entryNum = strtolower($entry['entry_number'] ?? '');
                    $desc = strtolower($entry['description'] ?? '');
                    if (strpos($entryNum, $search) === false && strpos($desc, $search) === false) return false;
                }
                
                if ($startDate && isset($entry['entry_date'])) {
                    $entryDate = is_string($entry['entry_date']) ? $entry['entry_date'] : 
                        (is_object($entry['entry_date']) && method_exists($entry['entry_date'], 'toDateTime') ? 
                        $entry['entry_date']->toDateTime()->format('Y-m-d') : '');
                    if ($entryDate < $startDate) return false;
                }
                
                if ($endDate && isset($entry['entry_date'])) {
                    $entryDate = is_string($entry['entry_date']) ? $entry['entry_date'] : 
                        (is_object($entry['entry_date']) && method_exists($entry['entry_date'], 'toDateTime') ? 
                        $entry['entry_date']->toDateTime()->format('Y-m-d') : '');
                    if ($entryDate > $endDate) return false;
                }
                
                return true;
            });
            
            // Sort entries
            usort($filteredEntries, function($a, $b) use ($sortBy, $sortOrder) {
                $aVal = $a[$sortBy] ?? '';
                $bVal = $b[$sortBy] ?? '';
                $comparison = $aVal <=> $bVal;
                return $sortOrder === 'desc' ? -$comparison : $comparison;
            });
            
            // Pagination
            $totalEntries = count($filteredEntries);
            $totalPages = ceil($totalEntries / $perPage);
            $page = min($page, max(1, $totalPages));
            $offset = ($page - 1) * $perPage;
            $entries = array_slice($filteredEntries, $offset, $perPage);
            
            // Format entries
            $formattedEntries = array_map(function($entry) use ($currencySymbol) {
                $totalDebit = 0;
                $totalCredit = 0;
                foreach ($entry['line_items'] ?? [] as $item) {
                    $totalDebit += $item['debit'] ?? 0;
                    $totalCredit += $item['credit'] ?? 0;
                }
                
                return [
                    'id' => (string)($entry['_id'] ?? ''),
                    'entry_number' => $entry['entry_number'] ?? '',
                    'entry_date' => isset($entry['entry_date']) && is_object($entry['entry_date']) ? 
                        $entry['entry_date']->toDateTime()->format('Y-m-d') : ($entry['entry_date'] ?? ''),
                    'entry_type' => $entry['entry_type'] ?? '',
                    'description' => $entry['description'] ?? '',
                    'status' => $entry['status'] ?? 'draft',
                    'total_debit' => formatMoney($totalDebit, $currencySymbol),
                    'total_credit' => formatMoney($totalCredit, $currencySymbol)
                ];
            }, $entries);
            
            echo json_encode([
                'success' => true,
                'entries' => $formattedEntries,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'per_page' => $perPage,
                    'total_items' => $totalEntries,
                    'showing_from' => $totalEntries > 0 ? $offset + 1 : 0,
                    'showing_to' => min($offset + $perPage, $totalEntries)
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
