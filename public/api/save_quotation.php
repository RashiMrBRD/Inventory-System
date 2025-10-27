<?php
/**
 * Save Quotation API Endpoint
 * Enterprise Features: Xero + QuickBooks + LedgerSMB
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Quotation;
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
    if (empty($input['quote_number']) || empty($input['customer']) || empty($input['date'])) {
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
    $total = $subtotalAfterDiscount + $tax;
    
    // Prepare quotation data
    $quotationData = [
        'quote_number' => trim($input['quote_number']),
        'customer' => trim($input['customer']),
        'customer_email' => isset($input['customer_email']) ? trim($input['customer_email']) : '',
        'customer_phone' => isset($input['customer_phone']) ? trim($input['customer_phone']) : '',
        'customer_company' => isset($input['customer_company']) ? trim($input['customer_company']) : '',
        'date' => $input['date'],
        'validity_days' => isset($input['validity_days']) ? (int)$input['validity_days'] : 30,
        'items' => $items,
        'subtotal' => $subtotal,
        'discount_percent' => $discountPercent,
        'discount_amount' => $discountAmount,
        'tax_rate' => $taxRate,
        'tax' => $tax,
        'total' => $total,
        'notes' => isset($input['notes']) ? trim($input['notes']) : '',
        'footer_text' => isset($input['footer_text']) ? trim($input['footer_text']) : '',
        'payment_terms' => isset($input['payment_terms']) ? trim($input['payment_terms']) : 'Net 30',
        'status' => 'pending',
        'created_by' => $user['email'] ?? 'system',
        'version' => 1,
        
        // Enterprise features - Basic
        'reference' => isset($input['reference']) ? trim($input['reference']) : '',
        'department' => isset($input['department']) ? trim($input['department']) : '',
        'project' => isset($input['project']) ? trim($input['project']) : '',
        'tags' => isset($input['tags']) ? (is_array($input['tags']) ? $input['tags'] : explode(',', $input['tags'])) : [],
        'currency' => isset($input['currency']) ? $input['currency'] : CurrencyHelper::getCurrentCurrency(),
        'shipping_cost' => isset($input['shipping_cost']) ? (float)$input['shipping_cost'] : 0,
        'handling_fee' => isset($input['handling_fee']) ? (float)$input['handling_fee'] : 0,
        
        // Payment methods (QuickBooks)
        'payment_methods' => isset($input['payment_methods']) && is_array($input['payment_methods']) ? $input['payment_methods'] : [],
        
        // Bank details (Xero)
        'bank_details' => [
            'bank_name' => isset($input['bank_name']) ? trim($input['bank_name']) : '',
            'account_number' => isset($input['bank_account']) ? trim($input['bank_account']) : '',
            'routing_number' => isset($input['bank_routing']) ? trim($input['bank_routing']) : '',
            'swift_code' => isset($input['bank_swift']) ? trim($input['bank_swift']) : ''
        ],
        
        // Late fees (QuickBooks)
        'late_fees' => [
            'enabled' => isset($input['enable_late_fees']) ? (bool)$input['enable_late_fees'] : false,
            'type' => isset($input['late_fee_type']) ? $input['late_fee_type'] : 'percentage',
            'amount' => isset($input['late_fee_amount']) ? (float)$input['late_fee_amount'] : 0,
            'days' => isset($input['late_fee_days']) ? (int)$input['late_fee_days'] : 30
        ]
    ];
    
    // Save to database
    $quotationModel = new Quotation();
    $id = $quotationModel->create($quotationData);
    
    if ($id) {
        echo json_encode([
            'success' => true,
            'message' => 'Quotation created successfully',
            'id' => $id,
            'quote_number' => $quotationData['quote_number'],
            'total' => $total
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create quotation']);
    }
    
} catch (\Exception $e) {
    error_log("Save Quotation Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
