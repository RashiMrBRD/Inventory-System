<?php
/**
 * Timezone Initialization
 * This file should be included at the start of every page to set the correct timezone
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get timezone from session or default to system timezone
$timezone = $_SESSION['timezone'] ?? date_default_timezone_get();

// Validate and set timezone
if (in_array($timezone, timezone_identifiers_list())) {
    date_default_timezone_set($timezone);
} else {
    // Fallback to UTC if invalid timezone
    date_default_timezone_set('UTC');
    $_SESSION['timezone'] = 'UTC';
}
