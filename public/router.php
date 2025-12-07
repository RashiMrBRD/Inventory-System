<?php
// Performance optimization headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$uri = ($uri !== '/') ? rtrim($uri, '/') : $uri;
$docroot = __DIR__;

$requested = $docroot . $uri;
if (is_file($requested)) {
    return false;
}

if ($uri === '/favicon.ico') {
    if (is_file($docroot . '/assets/logo/favicon.svg')) {
        header('Location: /assets/logo/favicon.svg', true, 302);
        exit;
    }
    // Return empty 204 response for favicon.ico to prevent 404 errors
    http_response_code(204);
    exit;
}

if ($uri === '/asset') {
    require $docroot . '/asset.php';
    return true;
}

if ($uri === '/css-loader') {
    require $docroot . '/css-loader.php';
    return true;
}

if ($uri === '/font-loader') {
    require $docroot . '/font-loader.php';
    return true;
}

if ($uri === '/body-loader') {
    require $docroot . '/body-loader.php';
    return true;
}

if ($uri === '/page-loader') {
    require $docroot . '/page-loader.php';
    return true;
}

if ($uri === '/' || $uri === '') {
    require $docroot . '/login.php';
    return true;
}

$phpFile = $docroot . $uri . '.php';
if (is_file($phpFile)) {
    require $phpFile;
    return true;
}

if (is_dir($docroot . $uri) && is_file($docroot . $uri . '/index.php')) {
    require $docroot . $uri . '/index.php';
    return true;
}

http_response_code(404);
echo '404 Not Found';
return true;
