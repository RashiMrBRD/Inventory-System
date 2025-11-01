<?php
/**
 * Check SMTP Configuration API Endpoint
 * Verifies if email server is configured and ready
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;

// Authenticate
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Check for SMTP configuration in environment or config
    $smtpConfigured = false;
    $smtpHost = getenv('SMTP_HOST') ?: '';
    $smtpPort = getenv('SMTP_PORT') ?: '';
    $smtpUsername = getenv('SMTP_USERNAME') ?: '';
    $smtpPassword = getenv('SMTP_PASSWORD') ?: '';
    
    // Check if config file exists
    $configFile = __DIR__ . '/../../config/email.php';
    if (file_exists($configFile)) {
        $emailConfig = require $configFile;
        if (isset($emailConfig['smtp'])) {
            $smtpHost = $emailConfig['smtp']['host'] ?? $smtpHost;
            $smtpPort = $emailConfig['smtp']['port'] ?? $smtpPort;
            $smtpUsername = $emailConfig['smtp']['username'] ?? $smtpUsername;
            $smtpPassword = $emailConfig['smtp']['password'] ?? $smtpPassword;
        }
    }
    
    // Consider configured if host and port are set
    if (!empty($smtpHost) && !empty($smtpPort)) {
        $smtpConfigured = true;
    }
    
    echo json_encode([
        'success' => true,
        'configured' => $smtpConfigured,
        'host' => $smtpConfigured ? substr($smtpHost, 0, 10) . '...' : null,
        'port' => $smtpConfigured ? $smtpPort : null
    ]);
    
} catch (\Exception $e) {
    error_log("Check SMTP Config Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'configured' => false,
        'message' => 'Error checking SMTP configuration'
    ]);
}
