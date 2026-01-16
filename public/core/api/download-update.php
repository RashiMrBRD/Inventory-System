<?php
/**
 * API Endpoint: Download and Install Update
 * 
 * Downloads the update from GitHub, extracts it, and replaces files.
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

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$downloadUrl = $input['download_url'] ?? null;
$version = $input['version'] ?? null;

if (empty($downloadUrl) || empty($version)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

try {
    // Create temp directory for downloads
    $tempDir = __DIR__ . '/../../../temp/updates';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    // Create progress file
    $progressFile = $tempDir . '/progress_' . time() . '.json';
    $progressUrl = '/api/update-progress/' . basename($progressFile);
    
    file_put_contents($progressFile, json_encode([
        'status' => 'downloading',
        'progress' => 0,
        'message' => 'Downloading update...'
    ]));

    // Start background process for download and installation
    $phpPath = PHP_BINARY;
    $scriptPath = __DIR__ . '/process-update.php';
    $command = sprintf(
        '%s %s %s %s %s > /dev/null 2>&1 &',
        escapeshellarg($phpPath),
        escapeshellarg($scriptPath),
        escapeshellarg($downloadUrl),
        escapeshellarg($version),
        escapeshellarg($progressFile)
    );

    exec($command);

    echo json_encode([
        'success' => true,
        'message' => 'Update download started',
        'progress_url' => $progressUrl
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
