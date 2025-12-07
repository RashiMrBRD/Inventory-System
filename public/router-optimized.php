<?php
/**
 * Static Asset Optimization Handler
 * 
 * This script optimizes static assets (CSS, JS, images) with proper caching,
 * compression, and minification headers.
 */

// Performance headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$uri = ($uri !== '/') ? rtrim($uri, '/') : $uri;
$docroot = __DIR__;

$requested = $docroot . $uri;

// Serve static files with optimization
if (is_file($requested)) {
    $ext = strtolower(pathinfo($requested, PATHINFO_EXTENSION));
    
    // Set appropriate content type and caching headers
    switch ($ext) {
        case 'css':
            header('Content-Type: text/css; charset=utf-8');
            header('Cache-Control: public, max-age=31536000, immutable');
            break;
        case 'js':
            header('Content-Type: application/javascript; charset=utf-8');
            header('Cache-Control: public, max-age=31536000, immutable');
            break;
        case 'svg':
            header('Content-Type: image/svg+xml; charset=utf-8');
            header('Cache-Control: public, max-age=31536000, immutable');
            break;
        case 'png':
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=31536000, immutable');
            break;
        case 'jpg':
        case 'jpeg':
            header('Content-Type: image/jpeg');
            header('Cache-Control: public, max-age=31536000, immutable');
            break;
        case 'ico':
            header('Content-Type: image/x-icon');
            header('Cache-Control: public, max-age=31536000, immutable');
            break;
        case 'woff':
        case 'woff2':
            header('Content-Type: font/' . $ext);
            header('Cache-Control: public, max-age=31536000, immutable');
            header('Access-Control-Allow-Origin: *');
            break;
        default:
            header('Cache-Control: public, max-age=86400'); // 1 day for other files
    }
    
    // Add ETag for cache validation
    header('ETag: "' . md5_file($requested) . '"');
    header('Vary: Accept-Encoding');
    header('Content-Length: ' . filesize($requested));
    
    // Enable gzip compression for text files
    if (in_array($ext, ['css', 'js', 'svg', 'html', 'txt'])) {
        ob_start('ob_gzhandler');
    }
    
    readfile($requested);
    exit;
}

// Favicon handling
if ($uri === '/favicon.ico') {
    if (is_file($docroot . '/assets/logo/favicon.svg')) {
        header('Location: /assets/logo/favicon.svg', true, 302);
        exit;
    }
    http_response_code(204);
    exit;
}

// Dynamic routes
if ($uri === '/css-loader') {
    require $docroot . '/css-loader.php';
    return true;
}

if ($uri === '/font-loader') {
    require $docroot . '/font-loader.php';
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

// PHP file handling
$phpFile = $docroot . $uri . '.php';
if (is_file($phpFile)) {
    require $phpFile;
    return true;
}

// Directory handling
if (is_dir($docroot . $uri) && is_file($docroot . $uri . '/index.php')) {
    require $docroot . $uri . '/index.php';
    return true;
}

// 404
http_response_code(404);
echo '404 Not Found';
return true;
?>
