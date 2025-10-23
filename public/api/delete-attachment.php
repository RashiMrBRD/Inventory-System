<?php
/**
 * Delete Attachment API
 * Deletes an attachment from a journal entry
 */

header('Content-Type: application/json');

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

use App\Controller\AccountingController;

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['entry_id']) || !isset($input['file_id'])) {
        throw new Exception('Missing required parameters');
    }
    
    $entryId = $input['entry_id'];
    $fileId = $input['file_id'];
    
    // Use JournalEntry model to delete attachment
    $journalEntry = new \App\Model\JournalEntry();
    $result = $journalEntry->deleteAttachment($entryId, $fileId);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Attachment deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete attachment');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
