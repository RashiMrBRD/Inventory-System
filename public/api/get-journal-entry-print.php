<?php
/**
 * Get Journal Entry for Print API
 * Returns formatted journal entry data for printing
 */

header('Content-Type: application/json');

session_start();

// Initialize timezone
require_once __DIR__ . '/../init_timezone.php';

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

// Get entry ID from query parameter
$entryId = $_GET['entry_id'] ?? '';

if (empty($entryId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Entry ID is required'
    ]);
    exit;
}

try {
    $accountingController = new AccountingController();
    
    // Get the journal entry
    $entryResult = $accountingController->getJournalEntry($entryId);
    
    if (!$entryResult['success']) {
        throw new Exception($entryResult['message'] ?? 'Entry not found');
    }
    
    $entry = $entryResult['data'];
    
    // Format the entry data for printing
    $printData = [
        'entry_number' => $entry['entry_number'] ?? 'N/A',
        'entry_date' => isset($entry['entry_date']) 
            ? $entry['entry_date']->toDateTime()->format('F d, Y')
            : 'N/A',
        'entry_type' => ucfirst($entry['entry_type'] ?? 'General'),
        'reference' => $entry['reference'] ?? '',
        'description' => $entry['description'] ?? '',
        'status' => ucfirst($entry['status'] ?? 'draft'),
        'lines' => [],
        'total_debit' => $entry['total_debit'] ?? 0,
        'total_credit' => $entry['total_credit'] ?? 0,
        'created_by' => getUsernameById($entry['created_by'] ?? ''),
        'created_at' => isset($entry['created_at']) 
            ? $entry['created_at']->toDateTime()->format('M d, Y g:i A')
            : 'N/A',
        'notes' => $entry['notes'] ?? '',
        'tags' => $entry['tags'] ?? []
    ];
    
    // Format transaction lines
    foreach ($entry['lines'] as $line) {
        $printData['lines'][] = [
            'account_code' => $line['account_code'] ?? '',
            'account_name' => $line['account_name'] ?? '',
            'description' => $line['description'] ?? '',
            'debit' => $line['debit'] ?? 0,
            'credit' => $line['credit'] ?? 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $printData
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get username by user ID
 * 
 * @param string $userId User ID
 * @return string Username
 */
function getUsernameById($userId): string
{
    if (empty($userId)) {
        return 'Unknown User';
    }
    
    try {
        $database = \App\Service\DatabaseService::getInstance();
        $usersCollection = $database->getCollection('users');
        
        $user = $usersCollection->findOne(['_id' => new \MongoDB\BSON\ObjectId($userId)]);
        
        if ($user) {
            if (isset($user['first_name']) && isset($user['last_name'])) {
                return trim($user['first_name'] . ' ' . $user['last_name']);
            }
            if (isset($user['username'])) {
                return $user['username'];
            }
            if (isset($user['email'])) {
                return $user['email'];
            }
        }
        
        return 'Unknown User';
    } catch (Exception $e) {
        error_log("Error fetching username for ID {$userId}: " . $e->getMessage());
        return 'Unknown User';
    }
}
