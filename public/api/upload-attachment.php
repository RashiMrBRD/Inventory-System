<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Model\JournalEntry;

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $journalEntry = new JournalEntry();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle file upload
        if (!isset($_FILES['file']) || !isset($_POST['entry_id'])) {
            throw new Exception('Missing required parameters');
        }

        $entryId = $_POST['entry_id'];
        $file = $_FILES['file'];

        $result = $journalEntry->addAttachment($entryId, $file);
        
        echo json_encode($result);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Handle file deletion
        parse_str(file_get_contents("php://input"), $_DELETE);
        
        if (!isset($_DELETE['entry_id']) || !isset($_DELETE['file_id'])) {
            throw new Exception('Missing required parameters');
        }

        $result = $journalEntry->deleteAttachment($_DELETE['entry_id'], $_DELETE['file_id']);
        
        echo json_encode(['success' => $result]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Handle file download
        if (!isset($_GET['file_id'])) {
            throw new Exception('Missing file ID');
        }

        $fileData = $journalEntry->downloadAttachment($_GET['file_id']);
        
        if (!$fileData) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'File not found']);
            exit;
        }

        // Set headers for file download
        header('Content-Type: ' . $fileData['mime_type']);
        header('Content-Disposition: attachment; filename="' . $fileData['filename'] . '"');
        header('Content-Length: ' . $fileData['size']);

        // Output file stream
        fpassthru($fileData['stream']);
        fclose($fileData['stream']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
