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
    
    // Get recent entries (last 20)
    $recentEntries = $journalEntry->getAllEntries([], 20);
    
    // Format entries for template selection
    $templates = array_map(function($entry) {
        return [
            '_id' => (string)$entry['_id'],
            'entry_number' => $entry['entry_number'],
            'entry_type' => $entry['entry_type'],
            'entry_date' => $entry['entry_date']->toDateTime()->format('Y-m-d'),
            'description' => $entry['description'],
            'reference' => $entry['reference'] ?? '',
            'notes' => $entry['notes'] ?? '',
            'tags' => $entry['tags'] ?? [],
            'lines' => $entry['lines'],
            'total_debit' => $entry['total_debit'],
            'total_credit' => $entry['total_credit'],
            'created_at' => $entry['created_at']->toDateTime()->format('Y-m-d H:i')
        ];
    }, $recentEntries);
    
    echo json_encode([
        'success' => true,
        'templates' => $templates
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
