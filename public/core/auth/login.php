<?php
/**
 * Login Page - Maximum view-source protection
 * 
 * This page contains minimal HTML structure and loads the encrypted page-loader.
 * All content is dynamically generated and encrypted to prevent view-source visibility.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

// Prevent caching of the login page
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
header('Pragma: no-cache');
header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');

use App\Controller\AuthController;
use App\Helper\CsrfHelper;
use App\Service\RateLimitService;

// Load app config
$appConfig = require __DIR__ . '/../../../config/app.php';

// Get config file modification time for cache-busting
$configFile = __DIR__ . '/../../../config/app.php';
$configMtime = filemtime($configFile);

$authController = new AuthController();
$error = '';
$isFirstRun = !$authController->hasAnyUser();

// Load app config
$appConfig = require __DIR__ . '/../../../config/app.php';

$host = $_SERVER['HTTP_HOST'] ?? '';
$hostOnly = parse_url('http://' . $host, PHP_URL_HOST) ?: $host;
$isDemoDomain = (strpos($hostOnly, $appConfig['security']['access_control']['demo_domain']) === 0);

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
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><title>Inventory Management System</title><style>html{visibility:hidden}</style><script src="/assets/js/debug-logger.js"></script><script src="page-loader?error=<?php echo urlencode($error); ?>&isFirstRun=<?php echo $isFirstRun ? 'true' : 'false'; ?>&allowRegistration=<?php echo $appConfig['security']['allow_registration'] ? 'true' : 'false'; ?>&allowInvitations=<?php echo ($appConfig['security']['allow_invitations'] ?? false) ? 'true' : 'false'; ?>&_v=<?php echo time(); ?>"></script><style>.login-footer{margin-top:1rem;text-align:center;font-size:0.875rem}.login-footer p{margin:0;color:var(--text-secondary)}.login-footer a{color:var(--color-primary);text-decoration:none;font-weight:500}.login-footer a:hover{text-decoration:underline}</style></head><body data-allow-registration="<?php echo $appConfig['security']['allow_registration'] ? 'true' : 'false'; ?>" data-allow-invitations="<?php echo ($appConfig['security']['allow_invitations'] ?? false) ? 'true' : 'false'; ?>"><noscript>JavaScript is required to use this application.</noscript><script src="/assets/js/login-register-link.js"></script></body></html>
