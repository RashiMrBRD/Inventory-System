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

// CRITICAL: Suppress errors and set Content-Type BEFORE any other code
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE & ~E_STRICT);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

// Load app config
$appConfig = require __DIR__ . '/../../../config/app.php';

// Get current page title from query parameter or use default
$pageTitle = $_GET['title'] ?? 'Login - Inventory Management System';
$host = $_SERVER['HTTP_HOST'] ?? '';

// Check if host is allowed using centralized configuration
if (!isHostAllowed($host, $appConfig['security']['access_control'])) {
    http_response_code(403);
    echo '<!-- Access denied -->';
    exit;
}

$hostOnly = parse_url('http://' . $host, PHP_URL_HOST) ?: $host;
$isDemoDomain = (strpos($hostOnly, $appConfig['security']['access_control']['demo_domain']) === 0);

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
