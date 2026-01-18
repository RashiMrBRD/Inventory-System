<?php
/**
 * API Endpoint: Server Time Polling
 * 
 * Provides current server time and uptime as a fallback when WebSocket is not available.
 */

header('Content-Type: application/json');

// Start session first to access user timezone settings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load app config to get default timezone
$appConfig = require __DIR__ . '/../../../config/app.php';

// Get timezone from session (user's selected timezone) or fall back to config
$timezone = $_SESSION['timezone'] ?? $appConfig['timezone'] ?? 'UTC';

// Validate timezone
if (!in_array($timezone, timezone_identifiers_list())) {
    $timezone = $appConfig['timezone'] ?? 'UTC';
}

// Set timezone for date functions
date_default_timezone_set($timezone);

// Calculate uptime (server or app uptime in seconds)
$uptime = 0;
// Try to get uptime from /proc/uptime (Linux)
if (file_exists('/proc/uptime')) {
    $uptimeData = file_get_contents('/proc/uptime');
    if ($uptimeData !== false) {
        $uptime = floatval(explode(' ', $uptimeData)[0]);
    }
}

// Fallback: app uptime using a persisted start time (Windows-friendly)
if ($uptime <= 0) {
    $uptimeFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'inventory_app_start_time.txt';
    $now = time();

    if (!file_exists($uptimeFile)) {
        @file_put_contents($uptimeFile, (string)$now, LOCK_EX);
    }

    $startTime = (int)trim((string)@file_get_contents($uptimeFile));
    if ($startTime > 0 && $startTime <= $now) {
        $uptime = $now - $startTime;
    }
}

// Format uptime
$uptimeFormatted = '';
if ($uptime > 0) {
    $days = floor($uptime / 86400);
    $hours = floor(($uptime % 86400) / 3600);
    $minutes = floor(($uptime % 3600) / 60);
    
    if ($days > 0) {
        $uptimeFormatted = sprintf('%dd %dh %dm', $days, $hours, $minutes);
    } elseif ($hours > 0) {
        $uptimeFormatted = sprintf('%dh %dm', $hours, $minutes);
    } else {
        $uptimeFormatted = sprintf('%dm', $minutes);
    }
} else {
    $uptimeFormatted = '0m';
}

try {
    echo json_encode([
        'success' => true,
        'time' => date('H:i:s'),
        'date' => date('Y-m-d'),
        'uptime' => $uptimeFormatted,
        'timezone' => $timezone
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
