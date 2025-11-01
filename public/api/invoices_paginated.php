<?php
/**
 * Get Paginated Invoices API
 * Returns paginated invoices with HTML for AJAX updates
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Invoice;
use App\Helper\CurrencyHelper;
use App\Service\CurrencyService;

$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = isset($_GET['perPage']) ? max(1, min(100, (int)$_GET['perPage'])) : 6;
    $status = isset($_GET['status']) && $_GET['status'] !== 'all' ? $_GET['status'] : null;
    
    $filter = [];
    if ($status) {
        $filter['status'] = $status;
    }
    
    $invoiceModel = new Invoice();
    $result = $invoiceModel->getPaginated($page, $perPage, $filter);
    
    // Generate HTML for table rows
    $rowsHtml = '';
    foreach ($result['items'] as $invoice) {
        $balance = ($invoice['total'] ?? 0) - ($invoice['paid'] ?? 0);
        $invId = $invoice['id'] ?? ((isset($invoice['_id']) && is_object($invoice['_id'])) ? (string)$invoice['_id'] : ($invoice['_id'] ?? ''));
        
        // Parse dates
        $dateTs = null;
        if (isset($invoice['date'])) {
            if (is_string($invoice['date'])) {
                $dateTs = strtotime($invoice['date']);
            } elseif (is_object($invoice['date']) && method_exists($invoice['date'], 'toDateTime')) {
                $dateTs = $invoice['date']->toDateTime()->getTimestamp();
            }
        }
        
        $dueTs = null;
        $dueField = $invoice['due_date'] ?? $invoice['due'] ?? null;
        if (isset($dueField)) {
            if (is_string($dueField)) {
                $dueTs = strtotime($dueField);
            } elseif (is_object($dueField) && method_exists($dueField, 'toDateTime')) {
                $dueTs = $dueField->toDateTime()->getTimestamp();
            }
        }
        
        // Status badge
        $statusBadges = [
            'paid' => 'badge-success',
            'unpaid' => 'badge-default',
            'partial' => 'badge-warning',
            'overdue' => 'badge-danger'
        ];
        $badgeClass = $statusBadges[$invoice['status']] ?? 'badge-default';
        
        $rowsHtml .= '<tr data-invoice-id="' . htmlspecialchars($invId) . '" '
            . 'data-customer="' . htmlspecialchars($invoice['customer'] ?? '') . '" '
            . 'data-date="' . ($dateTs ?? 0) . '" '
            . 'data-due="' . ($dueTs ?? 0) . '" '
            . 'data-total="' . ($invoice['total'] ?? 0) . '" '
            . 'data-paid="' . ($invoice['paid'] ?? 0) . '" '
            . 'data-balance="' . $balance . '" '
            . 'data-status="' . htmlspecialchars($invoice['status'] ?? '') . '">'
            . '<td class="checkbox-column" style="display: none;">'
            .   '<input type="checkbox" class="invoice-checkbox" value="' . htmlspecialchars($invId) . '" style="cursor: pointer;">'
            . '</td>'
            . '<td class="font-mono font-medium">' . htmlspecialchars($invoice['invoice_number'] ?? 'N/A') . '</td>'
            . '<td class="font-medium">' . htmlspecialchars($invoice['customer']) . '</td>'
            . '<td>' . ($dateTs ? date('M d, Y', $dateTs) : '-') . '</td>'
            . '<td>' . ($dueTs ? date('M d, Y', $dueTs) : '-') . '</td>'
            . '<td class="font-semibold">' . CurrencyHelper::format($invoice['total']) . '</td>'
            . '<td class="text-success">';
        
        // Payment display
        if ($invoice['paid'] > 0 && isset($invoice['payment_currency']) && $invoice['payment_currency'] !== CurrencyHelper::getCurrentCurrency()) {
            $rowsHtml .= CurrencyService::format($invoice['paid'], $invoice['payment_currency'])
                . ' <span class="text-muted" style="font-size: 0.75rem;">(' . htmlspecialchars($invoice['payment_currency']) . ')</span>';
        } else {
            $rowsHtml .= CurrencyHelper::format($invoice['paid']);
        }
        
        $rowsHtml .= '</td>'
            . '<td class="font-semibold ' . ($balance > 0 ? 'text-warning' : 'text-success') . '">' . CurrencyHelper::format($balance) . '</td>'
            . '<td><span class="badge ' . $badgeClass . '">' . ucfirst($invoice['status']) . '</span></td>'
            . '<td><div class="flex gap-1">'
            .   '<button class="btn btn-ghost btn-sm" onclick="viewInvoice(\'' . htmlspecialchars($invId) . '\')" title="View Details">'
            .     '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>'
            .   '</button>';
        
        if ($balance > 0) {
            $rowsHtml .= '<button class="btn btn-ghost btn-sm text-success" onclick="recordPayment(\'' . htmlspecialchars($invId) . '\')" title="Record Payment">'
                .   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="1" y="4" width="22" height="16" rx="2" stroke="currentColor" stroke-width="2"/><line x1="1" y1="10" x2="23" y2="10" stroke="currentColor" stroke-width="2"/></svg>'
                . '</button>';
        }
        
        $rowsHtml .= '<button class="btn btn-ghost btn-sm text-primary" onclick="emailInvoice(\'' . htmlspecialchars($invId) . '\')" title="Send Email">'
            .     '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M3 8L10.89 13.26C11.2187 13.4793 11.6049 13.5963 12 13.5963C12.3951 13.5963 12.7813 13.4793 13.11 13.26L21 8M5 19H19C19.5304 19 20.0391 18.7893 20.4142 18.4142C20.7893 18.0391 21 17.5304 21 17V7C21 6.46957 20.7893 5.96086 20.4142 5.58579C20.0391 5.21071 19.5304 5 19 5H5C4.46957 5 3.96086 5.21071 3.58579 5.58579C3.21071 5.96086 3 6.46957 3 7V17C3 17.5304 3.21071 18.0391 3.58579 18.4142C3.96086 18.7893 4.46957 19 5 19Z" stroke="currentColor" stroke-width="2"/></svg>'
            .   '</button>'
            .   '<button class="btn btn-ghost btn-sm" onclick="downloadPDF(\'' . htmlspecialchars($invId) . '\')" title="Download PDF">'
            .     '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M21 15V19C21 20.1046 20.1046 21 19 21H5C3.89543 21 3 20.1046 3 19V15M7 10L12 15M12 15L17 10M12 15V3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
            .   '</button>'
            . '</div></td>'
            . '</tr>';
    }
    
    echo json_encode([
        'success' => true,
        'html' => $rowsHtml,
        'pagination' => [
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'total' => $result['total'],
            'totalPages' => $result['totalPages']
        ]
    ]);
    
} catch (\Exception $e) {
    error_log("Paginated Invoices Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
