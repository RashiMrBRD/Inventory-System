<?php
/**
 * Record Payment API Endpoint
 * Professional invoicing system with payment tracking
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
    if (empty($input['invoice_id']) || !isset($input['amount']) || empty($input['payment_date']) || empty($input['payment_method'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields: invoice_id, amount, payment_date, payment_method']);
        exit;
    }
    
    $invoiceId = $input['invoice_id'];
    $paymentAmount = (float)$input['amount'];
    $paymentDate = $input['payment_date'];
    $paymentMethod = $input['payment_method'];
    $reference = isset($input['reference']) ? trim($input['reference']) : '';
    $notes = isset($input['notes']) ? trim($input['notes']) : '';
    
    // Validate payment amount
    if ($paymentAmount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment amount must be greater than zero']);
        exit;
    }
    
    // Get existing invoice
    $invoiceModel = new Invoice();
    $invoice = $invoiceModel->getById($invoiceId);
    
    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit;
    }
    
    // Calculate new paid amount
    $currentPaid = isset($invoice['paid']) ? (float)$invoice['paid'] : 0;
    $newPaidAmount = $currentPaid + $paymentAmount;
    $total = isset($invoice['total']) ? (float)$invoice['total'] : 0;
    $newBalance = $total - $newPaidAmount;
    
    // Determine new status
    $newStatus = 'unpaid';
    if ($newPaidAmount >= $total) {
        $newStatus = 'paid';
    } elseif ($newPaidAmount > 0) {
        $newStatus = 'partial';
    }
    
    // Check for overdue status
    if ($newStatus !== 'paid' && isset($invoice['due_date'])) {
        $dueDate = is_string($invoice['due_date']) ? strtotime($invoice['due_date']) : null;
        if ($dueDate && $dueDate < time()) {
            $newStatus = 'overdue';
        }
    }
    
    // Get existing payment history
    $paymentHistory = isset($invoice['payment_history']) && is_array($invoice['payment_history']) 
        ? $invoice['payment_history'] 
        : [];
    
    // Add new payment record
    $paymentHistory[] = [
        'amount' => $paymentAmount,
        'payment_date' => $paymentDate,
        'payment_method' => $paymentMethod,
        'reference' => $reference,
        'notes' => $notes,
        'recorded_by' => $user['email'] ?? 'system',
        'recorded_at' => date('Y-m-d H:i:s')
    ];
    
    // Prepare update data
    $updateData = [
        'paid' => $newPaidAmount,
        'status' => $newStatus,
        'payment_history' => $paymentHistory,
        'payment_method' => $paymentMethod, // Latest payment method
        'payment_date' => $paymentDate, // Latest payment date
        'last_payment_amount' => $paymentAmount,
        'last_payment_reference' => $reference,
        'updated_by' => $user['email'] ?? 'system'
    ];
    
    // Update invoice
    $success = $invoiceModel->update($invoiceId, $updateData);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment recorded successfully',
            'invoice_id' => $invoiceId,
            'payment_amount' => $paymentAmount,
            'total_paid' => $newPaidAmount,
            'balance' => $newBalance,
            'status' => $newStatus
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to record payment']);
    }
    
} catch (\Exception $e) {
    error_log("Record Payment Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
