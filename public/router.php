<?php
// Performance optimization headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(self)');

$requestedUri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$docroot = __DIR__;

$staticPath = $docroot . $requestedUri;
if (is_file($staticPath) && realpath($staticPath) !== realpath(__FILE__)) {
    return false;
}

if ($requestedUri === '/favicon.ico' && is_file($docroot . '/assets/logo/favicon.svg')) {
    header('Location: /assets/logo/favicon.svg', true, 302);
    exit;
}

require $docroot . '/index.php';
return true;
