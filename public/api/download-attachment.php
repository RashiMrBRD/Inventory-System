<?php
/**
 * Download Attachment API
 * Downloads an attachment from GridFS
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Model\JournalEntry;

try {
    if (!isset($_GET['file_id'])) {
        throw new Exception('Missing file ID');
    }
    
    $fileId = $_GET['file_id'];
    
    // Get file from GridFS
    $journalEntry = new JournalEntry();
    $fileData = $journalEntry->downloadAttachment($fileId);
    
    if (!$fileData) {
        throw new Exception('File not found');
    }
    
    // Set headers for download
    header('Content-Type: ' . $fileData['type']);
    header('Content-Disposition: attachment; filename="' . $fileData['filename'] . '"');
    header('Content-Length: ' . $fileData['size']);
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Stream the file
    $stream = $fileData['stream'];
    while (!feof($stream)) {
        echo fread($stream, 8192);
        flush();
    }
    fclose($stream);
    
} catch (Exception $e) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
