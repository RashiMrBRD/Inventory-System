<?php
/**
 * BIR Forms API
 * Handles BIR form generation and management
 */

// Prevent any HTML output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set content type to JSON
header('Content-Type: application/json');

// Custom error handler to prevent HTML output
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Custom exception handler
set_exception_handler(function($exception) {
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage()
    ]);
    exit;
});

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Include required files
require_once __DIR__ . '/../../vendor/autoload.php';
use App\Service\BIRService;
use App\Helper\CurrencyHelper;
use App\Controller\AuthController;

// Authenticate
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'generate_ramsay307':
            $containerData = json_decode($_POST['container_data'], true) ?? [];
            
            if (empty($containerData)) {
                echo json_encode(['success' => false, 'message' => 'No container data provided']);
                break;
            }
            
            $result = BIRService::generateRAMSAY307($containerData);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'generate_vat_monthly':
            $month = (int)($_POST['month'] ?? date('n'));
            $year = (int)($_POST['year'] ?? date('Y'));
            $result = BIRService::generateVATMonthly($month, $year);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'generate_vat_quarterly':
            $quarter = (int)($_POST['quarter'] ?? 1);
            $year = (int)($_POST['year'] ?? date('Y'));
            $result = BIRService::generateVATQuarterly($quarter, $year);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'generate_withholding_tax':
            $month = (int)($_POST['month'] ?? date('n'));
            $year = (int)($_POST['year'] ?? date('Y'));
            $result = BIRService::generateWithholdingTax($month, $year);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'generate_2307':
            $paymentId = (int)($_POST['payment_id'] ?? 0);
            $result = BIRService::generate2307($paymentId);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'generate_itr':
            $year = (int)($_POST['year'] ?? date('Y') - 1);
            $result = BIRService::generateITR($year);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'generate_percentage_tax':
            $quarter = (int)($_POST['quarter'] ?? 1);
            $year = (int)($_POST['year'] ?? date('Y'));
            $result = BIRService::generatePercentageTax($quarter, $year);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'submit_efps':
            $formId = (int)($_POST['form_id'] ?? 0);
            $formData = json_decode($_POST['form_data'], true) ?? [];
            $formData['id'] = $formId;
            $result = BIRService::submitToEFPS($formData);
            echo json_encode($result);
            break;
            
        case 'list_forms':
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-t');
            $forms = BIRService::getFormsByPeriod($startDate, $endDate);
            echo json_encode(['success' => true, 'data' => $forms]);
            break;
            
        case 'validate_form':
            $formType = $_POST['form_type'] ?? '';
            $formData = json_decode($_POST['form_data'], true) ?? [];
            $validation = BIRService::validateForm($formType, $formData);
            echo json_encode(['success' => true, 'data' => $validation]);
            break;
            
        case 'update_status':
            $formId = $_POST['form_id'] ?? '';
            $newStatus = $_POST['status'] ?? '';
            
            if (empty($formId) || empty($newStatus)) {
                echo json_encode(['success' => false, 'message' => 'Form ID and status are required']);
                break;
            }
            
            $result = BIRService::updateFormStatus($formId, $newStatus);
            echo json_encode($result);
            break;
            
        case 'delete_form':
            $formId = $_POST['form_id'] ?? '';
            
            if (empty($formId)) {
                echo json_encode(['success' => false, 'message' => 'Form ID is required']);
                break;
            }
            
            $result = BIRService::deleteForm($formId);
            echo json_encode($result);
            break;
            
        case 'view_form':
            $formId = $_GET['form_id'] ?? '';
            
            if (empty($formId)) {
                echo json_encode(['success' => false, 'message' => 'Form ID is required']);
                exit;
            }
            
            $result = BIRService::getForm($formId);
            
            // Always return JSON for modal
            echo json_encode($result);
            break;
            
        case 'download_pdf':
            $formId = $_GET['form_id'] ?? '';
            
            if (empty($formId)) {
                echo json_encode(['success' => false, 'message' => 'Form ID is required']);
                exit;
            }
            
            // TODO: Implement PDF generation
            echo json_encode(['success' => false, 'message' => 'PDF download not yet implemented']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
