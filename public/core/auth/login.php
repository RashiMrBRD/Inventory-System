<?php
/**
 * Login Page - Maximum view-source protection
 * 
 * This page contains minimal HTML structure and loads the encrypted page-loader.
 * All content is dynamically generated and encrypted to prevent view-source visibility.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Helper\CsrfHelper;
use App\Service\RateLimitService;

// Load app config
$appConfig = require __DIR__ . '/../../../config/app.php';

$authController = new AuthController();
$error = '';
$isFirstRun = !$authController->hasAnyUser();
$host = $_SERVER['HTTP_HOST'] ?? '';
$isDemoDomain = (strpos($host, 'demo.rashlink.eu.org') === 0);
$isLocalhost = (strpos($host, 'localhost') === 0 || $host === '127.0.0.1');

CsrfHelper::setTokenCookie();

// If already logged in, redirect to dashboard
if ($authController->isLoggedIn()) {
    header("Location: /dashboard");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfHelper::validate($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid or missing security token. Please refresh and try again.';
    } elseif ($isFirstRun && isset($_POST['setup_admin'])) {
        $result = $authController->createInitialAdmin($_POST);
        if ($result['success']) {
            $newToken = CsrfHelper::rotate();
            CsrfHelper::setTokenCookie('csrf_token', $newToken);
            header("Location: /dashboard");
            exit();
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'register') {
        $rateLimit = new RateLimitService();
        $limit = $rateLimit->check('auth.register', null, 10, 60);
        if (!$limit['allowed']) {
            if (!headers_sent() && isset($limit['retry_after'])) {
                header('Retry-After: ' . (int)$limit['retry_after']);
            }
            $error = 'Too many registration attempts. Please try again later.';
        } else {
            $result = $authController->register($_POST);
            if ($result['success']) {
                $newToken = CsrfHelper::rotate();
                CsrfHelper::setTokenCookie('csrf_token', $newToken);
                header("Location: /dashboard");
                exit();
            } else {
                $error = $result['message'];
            }
        }
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $result = $authController->login($username, $password);
        
        if ($result['success']) {
            $newToken = CsrfHelper::rotate();
            CsrfHelper::setTokenCookie('csrf_token', $newToken);
            header("Location: /dashboard");
            exit();
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><title>Inventory Management System</title><style>html{visibility:hidden}</style><script src="/assets/js/debug-logger.js"></script><script src="page-loader?error=<?php echo urlencode($error); ?>&isFirstRun=<?php echo $isFirstRun ? 'true' : 'false'; ?>&allowRegistration=<?php echo $appConfig['security']['allow_registration'] ? 'true' : 'false'; ?>"></script><style>.login-footer{margin-top:1rem;text-align:center;font-size:0.875rem}.login-footer p{margin:0;color:var(--text-secondary)}.login-footer a{color:var(--color-primary);text-decoration:none;font-weight:500}.login-footer a:hover{text-decoration:underline}</style></head><body><noscript>JavaScript is required to use this application.</noscript><script src="/assets/js/login-register-link.js"></script></body></html>
