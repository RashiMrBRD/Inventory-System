<?php
/**
 * Dynamic Font Loader - Hides font links from view-source
 * 
 * This script generates Google Fonts preload and stylesheet links dynamically.
 * 
 * Usage: <?php include 'font-loader.php'; ?>
 */

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable'); // 1 year cache for fonts
header('ETag: "' . md5_file(__FILE__) . '"');
header('Vary: Accept-Encoding');

// Load app config
$appConfig = require __DIR__ . '/../../../config/app.php';

$host = $_SERVER['HTTP_HOST'] ?? '';

// Check if host is allowed using centralized configuration
if (!isHostAllowed($host, $appConfig['security']['access_control'])) {
    http_response_code(403);
    echo '/* Access denied */';
    exit;
}

$hostOnly = parse_url('http://' . $host, PHP_URL_HOST) ?: $host;
$isDemoDomain = (strpos($hostOnly, $appConfig['security']['access_control']['demo_domain']) === 0);

?>
/* Google Fonts - Inter */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
