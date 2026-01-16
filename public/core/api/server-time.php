<?php
/**
 * API Endpoint: Server Time Polling
 * 
 * Provides current server time as a fallback when WebSocket is not available.
 */

header('Content-Type: application/json');

try {
    echo json_encode([
        'success' => true,
        'time' => date('H:i:s')
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
