<?php
/**
 * Login Page - Maximum view-source protection
 * 
 * This page contains minimal HTML structure and loads the encrypted page-loader.
 * All content is dynamically generated and encrypted to prevent view-source visibility.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controller\AuthController;

$authController = new AuthController();
$error = '';
$isFirstRun = !$authController->hasAnyUser();
$host = $_SERVER['HTTP_HOST'] ?? '';
$isDemoDomain = (strpos($host, 'demo.rashlink.eu.org') === 0);
$isLocalhost = (strpos($host, 'localhost') === 0 || $host === '127.0.0.1');

// If already logged in, redirect to dashboard
if ($authController->isLoggedIn()) {
    header("Location: /dashboard");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isFirstRun && isset($_POST['setup_admin'])) {
        $result = $authController->createInitialAdmin($_POST);
        if ($result['success']) {
            header("Location: /dashboard");
            exit();
        } else {
            $error = $result['message'];
        }
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $result = $authController->login($username, $password);
        
        if ($result['success']) {
            header("Location: /dashboard");
            exit();
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Inventory Management System</title><style>html{visibility:hidden}</style><script src="page-loader?error=<?php echo urlencode($error); ?>&isFirstRun=<?php echo $isFirstRun ? 'true' : 'false'; ?>"></script></head><body><noscript>JavaScript is required to use this application.</noscript></body></html>
