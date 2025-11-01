<?php
/**
 * Save Invoice API Endpoint
 * Enterprise Features: Xero + QuickBooks + LedgerSMB
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Invoice;
use App\Helper\CurrencyHelper;

// Authenticate
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = $authController->getCurrentUser();

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    // Validate required fields
    if (empty($input['invoice_number']) || empty($input['customer']) || empty($input['date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Parse line items
    $items = [];
    $subtotal = 0;
    
    if (isset($input['items']) && is_array($input['items'])) {
        foreach ($input['items'] as $key => $item) {
            if (isset($item['description']) && isset($item['quantity']) && isset($item['unit_price'])) {
                $qty = (float)$item['quantity'];
                $price = (float)$item['unit_price'];
                $lineTotal = $qty * $price;
                
                $items[] = [
                    'description' => trim($item['description']),
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'total' => $lineTotal,
                    'tax_rate' => isset($item['tax_rate']) ? (float)$item['tax_rate'] : 12,
                    'discount' => isset($item['discount']) ? (float)$item['discount'] : 0
                ];
                
                $subtotal += $lineTotal;
            }
        }
    }
    
    // Calculate totals
    $discountPercent = isset($input['discount_percent']) ? (float)$input['discount_percent'] : 0;
    $discountAmount = $subtotal * ($discountPercent / 100);
    $subtotalAfterDiscount = $subtotal - $discountAmount;
    
    $taxRate = isset($input['tax_rate']) ? (float)$input['tax_rate'] : 12;
    $tax = $subtotalAfterDiscount * ($taxRate / 100);
    
    $shippingCost = isset($input['shipping_cost']) ? (float)$input['shipping_cost'] : 0;
    $total = $subtotalAfterDiscount + $tax + $shippingCost;
    
    // Prepare invoice data
    $invoiceData = [
        'invoice_number' => trim($input['invoice_number']),
        'customer' => trim($input['customer']),
        'customer_email' => isset($input['customer_email']) ? trim($input['customer_email']) : '',
        'customer_phone' => isset($input['customer_phone']) ? trim($input['customer_phone']) : '',
        'customer_address' => isset($input['customer_address']) ? trim($input['customer_address']) : '',
        'date' => $input['date'],
        'due_date' => isset($input['due_date']) ? $input['due_date'] : date('Y-m-d', strtotime($input['date'] . ' +30 days')),
        'items' => $items,
        'subtotal' => $subtotal,
        'discount_percent' => $discountPercent,
        'discount_amount' => $discountAmount,
        'tax_rate' => $taxRate,
        'tax' => $tax,
        'shipping_cost' => $shippingCost,
        'total' => $total,
        'paid' => 0, // Initial paid amount
        'notes' => isset($input['notes']) ? trim($input['notes']) : '',
        'internal_notes' => isset($input['internal_notes']) ? trim($input['internal_notes']) : '',
        'status' => 'unpaid',
        'created_by' => $user['email'] ?? 'system',
        
        // Enterprise features - Xero
        'reference' => isset($input['reference']) ? trim($input['reference']) : '',
        'payment_terms' => isset($input['payment_terms']) ? trim($input['payment_terms']) : 'Net 30',
        'currency' => isset($input['currency']) ? $input['currency'] : CurrencyHelper::getCurrentCurrency(),
        
        // QuickBooks features
        'tracking_categories' => isset($input['tracking_categories']) && is_array($input['tracking_categories']) 
            ? $input['tracking_categories'] : [],
        'custom_fields' => isset($input['custom_fields']) && is_array($input['custom_fields']) 
            ? $input['custom_fields'] : [],
        
        // LedgerSMB features
        'department' => isset($input['department']) ? trim($input['department']) : '',
        'project' => isset($input['project']) ? trim($input['project']) : '',
        'tags' => isset($input['tags']) ? (is_array($input['tags']) ? $input['tags'] : explode(',', $input['tags'])) : [],
        
        // Payment tracking
        'payment_method' => '',
        'payment_date' => null,
        'payment_currency' => null,
        'payment_methods' => isset($input['payment_methods']) && is_array($input['payment_methods']) 
            ? $input['payment_methods'] : [],
        'payment_instructions' => isset($input['payment_instructions']) ? trim($input['payment_instructions']) : '',
        
        // Bank details
        'bank_name' => isset($input['bank_name']) ? trim($input['bank_name']) : '',
        'bank_account' => isset($input['bank_account']) ? trim($input['bank_account']) : '',
        'bank_account_name' => isset($input['bank_account_name']) ? trim($input['bank_account_name']) : '',
        'bank_swift' => isset($input['bank_swift']) ? trim($input['bank_swift']) : '',
        
        // Ewallet information
        'gcash_number' => isset($input['gcash_number']) ? trim($input['gcash_number']) : '',
        'gcash_name' => isset($input['gcash_name']) ? trim($input['gcash_name']) : '',
        'paymaya_number' => isset($input['paymaya_number']) ? trim($input['paymaya_number']) : '',
        'paymaya_name' => isset($input['paymaya_name']) ? trim($input['paymaya_name']) : '',
        
        // Recurring invoice (QuickBooks)
        'is_recurring' => isset($input['is_recurring']) ? (bool)$input['is_recurring'] : false,
        'recurring_frequency' => isset($input['recurring_frequency']) ? $input['recurring_frequency'] : '',
        
        // Late fees (Xero)
        'late_fee_enabled' => isset($input['late_fee_enabled']) ? (bool)$input['late_fee_enabled'] : false,
        'late_fee_percentage' => isset($input['late_fee_percentage']) ? (float)$input['late_fee_percentage'] : 0,
        
        // Document attachments (LedgerSMB)
        'attachments' => isset($input['attachments']) && is_array($input['attachments']) ? $input['attachments'] : [],
        
        // Approval workflow (Enterprise)
        'requires_approval' => isset($input['requires_approval']) ? (bool)$input['requires_approval'] : false,
        'approved_by' => null,
        'approval_date' => null
    ];
    
    // Save to database
    $invoiceModel = new Invoice();
    $id = $invoiceModel->create($invoiceData);
    
    if ($id) {
        echo json_encode([
            'success' => true,
            'message' => 'Invoice created successfully',
            'id' => $id,
            'invoice_number' => $invoiceData['invoice_number'],
            'total' => $total
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create invoice']);
    }
    
} catch (\Exception $e) {
    error_log("Save Invoice Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
