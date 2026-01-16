<?php
/**
 * Timezone Initialization
 * This file should be included at the start of every page to set the correct timezone
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Helper\SessionHelper;

// Ensure session is started
SessionHelper::start();

// Get timezone from session or default to system timezone
$timezone = SessionHelper::get('timezone', date_default_timezone_get());

// Validate and set timezone
if (in_array($timezone, timezone_identifiers_list())) {
    date_default_timezone_set($timezone);
} else {
    // Fallback to UTC if invalid timezone
    date_default_timezone_set('UTC');
    SessionHelper::set('timezone', 'UTC');
}
