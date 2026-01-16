<?php
/**
 * API Endpoint: Update Progress
 * 
 * Returns the current progress of an update installation.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Helper\SessionHelper;
use App\Controller\AuthController;

// Ensure session is started
SessionHelper::start();

// Enforce authentication
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// Get progress file from URL
$progressFile = basename($_SERVER['REQUEST_URI']);
$progressFilePath = __DIR__ . '/../../../temp/updates/' . $progressFile;

if (!file_exists($progressFilePath)) {
    echo json_encode([
        'success' => false,
        'message' => 'Progress file not found'
    ]);
    exit;
}

try {
    $progressData = json_decode(file_get_contents($progressFilePath), true);
    echo json_encode($progressData);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
