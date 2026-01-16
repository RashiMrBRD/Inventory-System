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

$host = $_SERVER['HTTP_HOST'] ?? '';
$hostOnly = parse_url('http://' . $host, PHP_URL_HOST) ?: $host;
$isDemoDomain = (strpos($hostOnly, 'demo.rashlink.eu.org') === 0);
$isLocalhost = ($hostOnly === 'localhost' || $hostOnly === '127.0.0.1' || $hostOnly === '::1');

// Only serve font content for demo domain and localhost
if (!$isDemoDomain && !$isLocalhost) {
    http_response_code(403);
    echo '/* Access denied */';
    exit;
}

?>
/* Google Fonts - Inter */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
