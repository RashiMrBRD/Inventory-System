<?php
/**
 * Ewallet API - GCash & PayMaya Integration
 * Handles digital wallet payments for Philippine market
 * Ready for production integration with payment gateways
 */

header('Content-Type: application/json');

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../src/Helper/CurrencyHelper.php';

use InventoryDemo\Helper\CurrencyHelper;

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * GCash Configuration
 * In production, integrate with GCash API
 * @link https://www.gcash.com/business/api
 */
class GCashService {
    private $apiKey;
    private $merchantId;
    private $sandbox = true; // Set to false in production
    
    public function __construct() {
        // Load from environment variables in production
        $this->apiKey = $_ENV['GCASH_API_KEY'] ?? 'sandbox_key';
        $this->merchantId = $_ENV['GCASH_MERCHANT_ID'] ?? 'sandbox_merchant';
    }
    
    /**
     * Create GCash payment link
     */
    public function createPayment($data) {
        // Validate data
        if (empty($data['amount']) || empty($data['mobile_number'])) {
            return [
                'success' => false,
                'error' => 'Amount and mobile number are required'
            ];
        }
        
        // In production, call GCash API
        // For now, return mock response
        $referenceNumber = 'GCASH-' . date('YmdHis') . '-' . rand(1000, 9999);
        
        return [
            'success' => true,
            'provider' => 'gcash',
            'reference_number' => $referenceNumber,
            'payment_link' => $this->sandbox ? 
                "https://sandbox.gcash.com/pay/{$referenceNumber}" : 
                "https://www.gcash.com/pay/{$referenceNumber}",
            'qr_code' => "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$referenceNumber}",
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'PHP',
            'mobile_number' => $data['mobile_number'],
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+15 minutes'))
        ];
    }
    
    /**
     * Verify GCash payment status
     */
    public function verifyPayment($referenceNumber) {
        // In production, call GCash API to verify
        // Mock response for development
        return [
            'success' => true,
            'reference_number' => $referenceNumber,
            'status' => 'paid', // pending, paid, failed, expired
            'paid_at' => date('Y-m-d H:i:s'),
            'transaction_id' => 'GCASH-TXN-' . rand(100000, 999999)
        ];
    }
}

/**
 * PayMaya Configuration
 * In production, integrate with PayMaya API
 * @link https://developers.paymaya.com/
 */
class PayMayaService {
    private $publicKey;
    private $secretKey;
    private $sandbox = true;
    
    public function __construct() {
        // Load from environment variables in production
        $this->publicKey = $_ENV['PAYMAYA_PUBLIC_KEY'] ?? 'pk-sandbox';
        $this->secretKey = $_ENV['PAYMAYA_SECRET_KEY'] ?? 'sk-sandbox';
    }
    
    /**
     * Create PayMaya payment checkout
     */
    public function createCheckout($data) {
        // Validate data
        if (empty($data['amount']) || empty($data['description'])) {
            return [
                'success' => false,
                'error' => 'Amount and description are required'
            ];
        }
        
        // In production, call PayMaya API
        $checkoutId = 'PM-' . date('YmdHis') . '-' . rand(1000, 9999);
        
        return [
            'success' => true,
            'provider' => 'paymaya',
            'checkout_id' => $checkoutId,
            'checkout_url' => $this->sandbox ?
                "https://sandbox.paymaya.com/checkout/{$checkoutId}" :
                "https://pay.paymaya.com/checkout/{$checkoutId}",
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'PHP',
            'description' => $data['description'],
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ];
    }
    
    /**
     * Verify PayMaya payment
     */
    public function verifyPayment($checkoutId) {
        // In production, call PayMaya API
        return [
            'success' => true,
            'checkout_id' => $checkoutId,
            'status' => 'completed', // pending, completed, failed
            'paid_at' => date('Y-m-d H:i:s'),
            'payment_id' => 'PM-PAY-' . rand(100000, 999999)
        ];
    }
}

/**
 * Unified Ewallet Service
 */
class EwalletService {
    
    /**
     * Get ewallet provider info
     */
    public static function getProviders() {
        return [
            'gcash' => [
                'name' => 'GCash',
                'icon' => '📱',
                'country' => 'PH',
                'currency' => 'PHP',
                'fee' => 2.0,
                'min_amount' => 1,
                'max_amount' => 50000,
                'features' => ['qr_code', 'payment_link', 'instant']
            ],
            'paymaya' => [
                'name' => 'PayMaya',
                'icon' => '💚',
                'country' => 'PH',
                'currency' => 'PHP',
                'fee' => 2.0,
                'min_amount' => 1,
                'max_amount' => 100000,
                'features' => ['checkout', 'card_tokenization', 'instant']
            ]
        ];
    }
    
    /**
     * Create payment
     */
    public static function createPayment($provider, $data) {
        switch ($provider) {
            case 'gcash':
                $service = new GCashService();
                return $service->createPayment($data);
                
            case 'paymaya':
                $service = new PayMayaService();
                return $service->createCheckout($data);
                
            default:
                return [
                    'success' => false,
                    'error' => 'Invalid ewallet provider'
                ];
        }
    }
    
    /**
     * Verify payment
     */
    public static function verifyPayment($provider, $reference) {
        switch ($provider) {
            case 'gcash':
                $service = new GCashService();
                return $service->verifyPayment($reference);
                
            case 'paymaya':
                $service = new PayMayaService();
                return $service->verifyPayment($reference);
                
            default:
                return [
                    'success' => false,
                    'error' => 'Invalid ewallet provider'
                ];
        }
    }
}

// Handle API requests
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'providers':
            // GET /api/ewallet.php?action=providers
            echo json_encode([
                'success' => true,
                'providers' => EwalletService::getProviders()
            ]);
            break;
            
        case 'create_payment':
            // POST /api/ewallet.php?action=create_payment
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $provider = $input['provider'] ?? '';
            $paymentData = $input['data'] ?? [];
            
            $result = EwalletService::createPayment($provider, $paymentData);
            echo json_encode($result);
            break;
            
        case 'verify_payment':
            // POST /api/ewallet.php?action=verify_payment
            if ($method !== 'POST') {
                throw new Exception('Method not allowed');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $provider = $input['provider'] ?? '';
            $reference = $input['reference'] ?? '';
            
            $result = EwalletService::verifyPayment($provider, $reference);
            echo json_encode($result);
            break;
            
        case 'webhook':
            // POST /api/ewallet.php?action=webhook
            // Handle payment gateway webhooks
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Log webhook for debugging
            error_log('Ewallet Webhook: ' . json_encode($input));
            
            echo json_encode([
                'success' => true,
                'message' => 'Webhook received'
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => true,
                'message' => 'Ewallet API - GCash & PayMaya Integration',
                'endpoints' => [
                    'GET ?action=providers' => 'Get available ewallet providers',
                    'POST ?action=create_payment' => 'Create ewallet payment',
                    'POST ?action=verify_payment' => 'Verify payment status',
                    'POST ?action=webhook' => 'Handle payment gateway webhooks'
                ],
                'integration_notes' => [
                    'gcash' => 'Replace sandbox keys with production API keys',
                    'paymaya' => 'Configure webhook URL in PayMaya dashboard',
                    'security' => 'Implement signature verification for webhooks',
                    'environment' => 'Set sandbox=false for production'
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
