<?php
/**
 * Send Invoice Email API Endpoint
 * Sends invoice via email with PDF attachment
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Invoice;

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
    if (empty($input['invoice_id']) || empty($input['recipient_email']) || empty($input['subject']) || empty($input['message'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    $invoiceId = $input['invoice_id'];
    $recipientEmail = trim($input['recipient_email']);
    $ccEmail = isset($input['cc_email']) ? trim($input['cc_email']) : null;
    $subject = trim($input['subject']);
    $message = trim($input['message']);
    $attachPdf = isset($input['attach_pdf']) ? (bool)$input['attach_pdf'] : false;
    
    // Validate email format
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid recipient email format']);
        exit;
    }
    
    if ($ccEmail && !filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid CC email format']);
        exit;
    }
    
    // Get invoice details
    $invoiceModel = new Invoice();
    $invoice = $invoiceModel->getById($invoiceId);
    
    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit;
    }
    
    // Check SMTP configuration
    $smtpHost = getenv('SMTP_HOST') ?: '';
    $smtpPort = getenv('SMTP_PORT') ?: '';
    $smtpUsername = getenv('SMTP_USERNAME') ?: '';
    $smtpPassword = getenv('SMTP_PASSWORD') ?: '';
    $smtpFromEmail = getenv('SMTP_FROM_EMAIL') ?: 'noreply@yourdomain.com';
    $smtpFromName = getenv('SMTP_FROM_NAME') ?: 'Your Company';
    
    // Check for config file
    $configFile = __DIR__ . '/../../config/email.php';
    if (file_exists($configFile)) {
        $emailConfig = require $configFile;
        if (isset($emailConfig['smtp'])) {
            $smtpHost = $emailConfig['smtp']['host'] ?? $smtpHost;
            $smtpPort = $emailConfig['smtp']['port'] ?? $smtpPort;
            $smtpUsername = $emailConfig['smtp']['username'] ?? $smtpUsername;
            $smtpPassword = $emailConfig['smtp']['password'] ?? $smtpPassword;
            $smtpFromEmail = $emailConfig['smtp']['from_email'] ?? $smtpFromEmail;
            $smtpFromName = $emailConfig['smtp']['from_name'] ?? $smtpFromName;
        }
    }
    
    if (empty($smtpHost) || empty($smtpPort)) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'SMTP server not configured. Please configure email settings.']);
        exit;
    }
    
    // Use PHPMailer if available
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtpPort;
            
            // Recipients
            $mail->setFrom($smtpFromEmail, $smtpFromName);
            $mail->addAddress($recipientEmail);
            
            if ($ccEmail) {
                $mail->addCC($ccEmail);
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = nl2br(htmlspecialchars($message));
            $mail->AltBody = $message;
            
            // Attach PDF if requested
            if ($attachPdf) {
                // TODO: Generate PDF and attach
                // $pdfPath = generateInvoicePDF($invoice);
                // $mail->addAttachment($pdfPath, 'invoice_' . $invoice['invoice_number'] . '.pdf');
            }
            
            $mail->send();
            
            // Update invoice status to 'sent'
            $invoiceModel->update($invoiceId, [
                'email_sent' => true,
                'email_sent_at' => date('Y-m-d H:i:s'),
                'email_sent_to' => $recipientEmail,
                'email_sent_by' => $user['email'] ?? 'system'
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Invoice email sent successfully',
                'recipient' => $recipientEmail
            ]);
            
        } catch (\Exception $e) {
            error_log("PHPMailer Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()]);
        }
    } else {
        // Fallback to PHP mail() function (not recommended for production)
        $headers = "From: $smtpFromName <$smtpFromEmail>\r\n";
        $headers .= "Reply-To: $smtpFromEmail\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        if ($ccEmail) {
            $headers .= "Cc: $ccEmail\r\n";
        }
        
        $success = mail($recipientEmail, $subject, $message, $headers);
        
        if ($success) {
            // Update invoice status
            $invoiceModel->update($invoiceId, [
                'email_sent' => true,
                'email_sent_at' => date('Y-m-d H:i:s'),
                'email_sent_to' => $recipientEmail,
                'email_sent_by' => $user['email'] ?? 'system'
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Invoice email sent successfully',
                'recipient' => $recipientEmail
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to send email via mail() function']);
        }
    }
    
} catch (\Exception $e) {
    error_log("Send Invoice Email Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
