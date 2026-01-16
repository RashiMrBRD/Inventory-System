<?php
/**
 * Dynamic Head Content Loader - Hides head content from view-source
 * 
 * This script generates HTML head content dynamically without exposing it in the HTML source.
 * It outputs proper head tags with CSS, fonts, and meta information, making them invisible 
 * when viewing page source via view-source: protocol.
 * 
 * Usage: <?php include 'head-loader.php'; ?>
 */

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

// Get current page title from query parameter or use default
$pageTitle = $_GET['title'] ?? 'Login - Inventory Management System';
$host = $_SERVER['HTTP_HOST'] ?? '';
$hostOnly = parse_url('http://' . $host, PHP_URL_HOST) ?: $host;
$isDemoDomain = (strpos($hostOnly, 'demo.rashlink.eu.org') === 0);
$isLocalhost = ($hostOnly === 'localhost' || $hostOnly === '127.0.0.1' || $hostOnly === '::1');

// Only serve head content for demo domain and localhost
if (!$isDemoDomain && !$isLocalhost) {
    http_response_code(403);
    echo '<!-- Access denied -->';
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <!-- Minimalist Design System CSS -->
    <link rel="stylesheet" href="css-loader?files=core.css,components.css,utilities.css,login-page.css">
    <link rel="stylesheet" href="font-loader">
    <!-- Google Fonts - Inter -->
</head>
