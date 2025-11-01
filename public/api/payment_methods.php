<?php
/**
 * Payment Methods API
 * Handles payment method configurations and validations
 * For easy integration with third-party payment gateways
 */

header('Content-Type: application/json');

// Enable CORS for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../src/Helper/CurrencyHelper.php';

use InventoryDemo\Helper\CurrencyHelper;

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Get available payment methods
 */
function getPaymentMethods() {
    return [
        'success' => true,
        'payment_methods' => [
            [
                'code' => 'bank_transfer',
                'name' => 'Bank Transfer',
                'icon' => '🏦',
                'type' => 'bank',
                'enabled' => true,
                'fees' => 0,
                'processing_time' => '1-3 business days'
            ],
            [
                'code' => 'credit_card',
                'name' => 'Credit Card',
                'icon' => '💳',
                'type' => 'card',
                'enabled' => true,
                'fees' => 3.5,
                'processing_time' => 'Instant'
            ],
            [
                'code' => 'cash',
                'name' => 'Cash',
                'icon' => '💵',
                'type' => 'offline',
                'enabled' => true,
                'fees' => 0,
                'processing_time' => 'Instant'
            ],
            [
                'code' => 'paypal',
                'name' => 'PayPal',
                'icon' => '🅿️',
                'type' => 'ewallet',
                'enabled' => true,
                'fees' => 3.9,
                'processing_time' => 'Instant'
            ],
            [
                'code' => 'gcash',
                'name' => 'GCash',
                'icon' => '📱',
                'type' => 'ewallet',
                'enabled' => true,
                'fees' => 2.0,
                'processing_time' => 'Instant',
                'country' => 'PH'
            ],
            [
                'code' => 'paymaya',
                'name' => 'PayMaya',
                'icon' => '💚',
                'type' => 'ewallet',
                'enabled' => true,
                'fees' => 2.0,
                'processing_time' => 'Instant',
                'country' => 'PH'
            ]
        ]
    ];
}

/**
 * Validate payment method data
 */
function validatePaymentMethod($method, $data) {
    $errors = [];
    
    switch ($method) {
        case 'gcash':
        case 'paymaya':
            if (empty($data['mobile_number'])) {
                $errors[] = 'Mobile number is required';
            } elseif (!preg_match('/^09\d{9}$/', $data['mobile_number'])) {
                $errors[] = 'Invalid Philippine mobile number format';
            }
            
            if (empty($data['account_name'])) {
                $errors[] = 'Account name is required';
            }
            break;
            
        case 'bank_transfer':
            if (empty($data['bank_name'])) {
                $errors[] = 'Bank name is required';
            }
            if (empty($data['account_number'])) {
                $errors[] = 'Account number is required';
            }
            break;
            
        case 'credit_card':
            if (empty($data['card_number'])) {
                $errors[] = 'Card number is required';
            }
            if (empty($data['expiry'])) {
                $errors[] = 'Expiry date is required';
            }
            if (empty($data['cvv'])) {
                $errors[] = 'CVV is required';
            }
            break;
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Calculate payment fees
 */
function calculatePaymentFees($amount, $method, $currency = 'PHP') {
    $methods = getPaymentMethods()['payment_methods'];
    $selectedMethod = array_filter($methods, function($m) use ($method) {
        return $m['code'] === $method;
    });
    
    if (empty($selectedMethod)) {
        return [
            'success' => false,
            'error' => 'Invalid payment method'
        ];
    }
    
    $methodData = reset($selectedMethod);
    $feePercentage = $methodData['fees'] ?? 0;
    $feeAmount = $amount * ($feePercentage / 100);
    $totalAmount = $amount + $feeAmount;
    
    return [
        'success' => true,
        'subtotal' => $amount,
        'fee_percentage' => $feePercentage,
        'fee_amount' => round($feeAmount, 2),
        'total_amount' => round($totalAmount, 2),
        'currency' => $currency,
        'formatted' => [
            'subtotal' => CurrencyHelper::format($amount, $currency),
            'fee' => CurrencyHelper::format($feeAmount, $currency),
            'total' => CurrencyHelper::format($totalAmount, $currency)
        ]
    ];
}

// Handle API requests
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            // GET /api/payment_methods.php?action=list
            echo json_encode(getPaymentMethods());
            break;
            
        case 'validate':
            // POST /api/payment_methods.php?action=validate
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $paymentMethod = $input['method'] ?? '';
            $paymentData = $input['data'] ?? [];
            
            $validation = validatePaymentMethod($paymentMethod, $paymentData);
            echo json_encode($validation);
            break;
            
        case 'calculate_fees':
            // POST /api/payment_methods.php?action=calculate_fees
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $amount = floatval($input['amount'] ?? 0);
            $paymentMethod = $input['method'] ?? '';
            $currency = $input['currency'] ?? 'PHP';
            
            $result = calculatePaymentFees($amount, $paymentMethod, $currency);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode([
                'success' => true,
                'message' => 'Payment Methods API',
                'endpoints' => [
                    'GET ?action=list' => 'Get all available payment methods',
                    'POST ?action=validate' => 'Validate payment method data',
                    'POST ?action=calculate_fees' => 'Calculate payment processing fees'
                ]
            ]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
