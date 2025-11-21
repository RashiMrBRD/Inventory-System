<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$uri = ($uri !== '/') ? rtrim($uri, '/') : $uri;
$docroot = __DIR__;

$requested = $docroot . $uri;
if (is_file($requested)) {
    return false;
}

if ($uri === '/favicon.ico' && is_file($docroot . '/favicon.svg')) {
    header('Location: /favicon.svg', true, 302);
    exit;
}

if ($uri === '/asset') {
    require $docroot . '/asset.php';
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
