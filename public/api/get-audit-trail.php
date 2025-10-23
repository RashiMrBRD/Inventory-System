<?php
/**
 * Get Audit Trail API
 * Returns audit trail information for a journal entry
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
    
    // Get created by user information
    $createdByUsername = 'Unknown User';
    $modifiedByUsername = 'Unknown User';
    
    if (isset($entry['created_by'])) {
        $createdByUsername = getUsernameById($entry['created_by']);
    }
    
    if (isset($entry['modified_by'])) {
        $modifiedByUsername = getUsernameById($entry['modified_by']);
    }
    
    // Format timestamps
    $createdAt = isset($entry['created_at']) 
        ? $entry['created_at']->toDateTime()->format('M d, Y \a\t g:i A')
        : 'Unknown';
        
    $modifiedAt = isset($entry['modified_at']) 
        ? $entry['modified_at']->toDateTime()->format('M d, Y \a\t g:i A')
        : 'Unknown';
    
    // Build audit history from entry data
    $history = [];
    
    // Entry Created event
    $history[] = [
        'action' => 'Entry Created',
        'user' => $createdByUsername,
        'timestamp' => $createdAt,
        'details' => sprintf(
            'Initial journal entry created with %d transaction line(s)', 
            count($entry['lines'] ?? [])
        )
    ];
    
    // Entry Modified events (if different from creation)
    if (isset($entry['modified_at']) && isset($entry['created_at'])) {
        $createdTimestamp = $entry['created_at']->toDateTime()->getTimestamp();
        $modifiedTimestamp = $entry['modified_at']->toDateTime()->getTimestamp();
        
        if ($modifiedTimestamp > $createdTimestamp) {
            $history[] = [
                'action' => 'Entry Modified',
                'user' => $modifiedByUsername,
                'timestamp' => $modifiedAt,
                'details' => 'Journal entry details updated'
            ];
        }
    }
    
    // Posted event
    if (isset($entry['status']) && $entry['status'] === 'posted') {
        $postedAt = isset($entry['posted_at']) 
            ? $entry['posted_at']->toDateTime()->format('M d, Y \a\t g:i A')
            : $modifiedAt;
        $postedBy = isset($entry['posted_by']) 
            ? getUsernameById($entry['posted_by']) 
            : $modifiedByUsername;
            
        $history[] = [
            'action' => 'Entry Posted',
            'user' => $postedBy,
            'timestamp' => $postedAt,
            'details' => 'Entry posted to ledger and account balances updated'
        ];
    }
    
    // Voided event
    if (isset($entry['status']) && $entry['status'] === 'void') {
        $voidedAt = isset($entry['voided_at']) 
            ? $entry['voided_at']->toDateTime()->format('M d, Y \a\t g:i A')
            : $modifiedAt;
        $voidedBy = isset($entry['voided_by']) 
            ? getUsernameById($entry['voided_by']) 
            : $modifiedByUsername;
        $voidReason = $entry['void_reason'] ?? 'No reason provided';
            
        $history[] = [
            'action' => 'Entry Voided',
            'user' => $voidedBy,
            'timestamp' => $voidedAt,
            'details' => 'Reason: ' . $voidReason
        ];
    }
    
    // Prepare response data
    $auditData = [
        'entryId' => substr((string)$entry['_id'], 0, 8) . '...',
        'fullEntryId' => (string)$entry['_id'],
        'entryNumber' => $entry['entry_number'] ?? 'N/A',
        'created' => [
            'user' => $createdByUsername,
            'timestamp' => $createdAt,
            'ipAddress' => $entry['created_ip'] ?? 'N/A'
        ],
        'modified' => [
            'user' => $modifiedByUsername,
            'timestamp' => $modifiedAt,
            'ipAddress' => $entry['modified_ip'] ?? 'N/A'
        ],
        'history' => $history
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $auditData
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
    try {
        $database = \App\Service\DatabaseService::getInstance();
        $usersCollection = $database->getCollection('users');
        
        $user = $usersCollection->findOne(['_id' => new \MongoDB\BSON\ObjectId($userId)]);
        
        if ($user) {
            // Try to get full name first, then username
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
