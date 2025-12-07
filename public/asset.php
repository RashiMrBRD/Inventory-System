<?php
// Secure Asset Proxy: serves files from /assets and /features via signed URLs with no-cache headers
// Query params: d=<base64url path relative to public>, e=<expiry epoch>, n=<nonce>, s=<signature>

// Basic hardening
ini_set('display_errors', '0');
error_reporting(0);

// Resolve base paths
$publicRoot = realpath(__DIR__);
$allowedRoots = [
    realpath(__DIR__ . '/assets'),
    realpath(__DIR__ . '/features'),
];

// Load signing key from config or env
$signingKey = null;
try {
    $appConfig = @require __DIR__ . '/../config/app.php';
    if (is_array($appConfig) && isset($appConfig['assets']['signing_key'])) {
        $signingKey = (string)$appConfig['assets']['signing_key'];
    }

// Comment stripping helpers (safe, minimal)
if (!function_exists('strip_css_comments')) {
    function strip_css_comments(string $css): string {
        return preg_replace('!/\*.*?\*/!s', '', $css);
    }
}
if (!function_exists('strip_js_block_comments')) {
    function strip_js_block_comments(string $js): string {
        return preg_replace('!/\*.*?\*/!s', '', $js);
    }
}
if (!function_exists('strip_html_comments')) {
    function strip_html_comments(string $html): string {
        return preg_replace('/<!--(?!\[if).*?-->/s', '', $html);
    }
}
} catch (Throwable $e) {
    // ignore
}
if (!$signingKey) {
    $signingKey = getenv('ASSET_SIGNING_KEY') ?: 'change-me-dev-key';
}

// Helpers
function b64url_decode_str(string $s): string {
    $p = strtr($s, '-_', '+/');
    $pad = strlen($p) % 4;
    if ($pad) $p .= str_repeat('=', 4 - $pad);
    return base64_decode($p) ?: '';
}
function b64url_encode_str(string $s): string {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}
function normalize_path(string $path): string {
    $parts = [];
    foreach (explode('/', str_replace('\\', '/', $path)) as $seg) {
        if ($seg === '' || $seg === '.') continue;
        if ($seg === '..') {
            array_pop($parts);
        } else {
            $parts[] = $seg;
        }
    }
    return implode('/', $parts);
}
function constant_time_equals(string $a, string $b): bool {
    if (strlen($a) !== strlen($b)) return false;
    $res = 0;
    for ($i = 0, $len = strlen($a); $i < $len; $i++) {
        $res |= ord($a[$i]) ^ ord($b[$i]);
    }
    return $res === 0;
}
function detect_mime(string $file): string {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $map = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'mjs' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'map' => 'application/json; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
        'html' => 'text/html; charset=UTF-8',
        'txt' => 'text/plain; charset=UTF-8',
    ];
    if (isset($map[$ext])) return $map[$ext];
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $file);
            finfo_close($finfo);
            if ($mime) return $mime;
        }
    }
    return 'application/octet-stream';
}
function base_prefix(): string {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($dir === '/' || $dir === '\\' || $dir === '.') return '';
    return rtrim($dir, '/');
}
function sign_token(string $key, string $path, int $exp, string $nonce): string {
    return hash_hmac('sha256', $path . '|' . $exp . '|' . $nonce, $key);
}
function build_proxy_url(string $path, string $key): string {
    $exp = time() + 3600; // 1h validity
    try {
        $nonce = bin2hex(random_bytes(12));
    } catch (Throwable $e) {
        $nonce = bin2hex((string)mt_rand());
    }
    $sig = sign_token($key, $path, $exp, $nonce);
    $prefix = base_prefix();
    $proxy = $prefix . '/asset?d=' . rawurlencode(b64url_encode_str($path)) . '&e=' . $exp . '&n=' . rawurlencode($nonce) . '&s=' . $sig;
    return $proxy;
}
function rewrite_css_urls(string $css, string $cssRelPath, string $key): string {
    $cssDir = trim(dirname($cssRelPath), '/');
    return preg_replace_callback('/url\(\s*([\"\']?)([^)\"\']+)\1\s*\)/i', function ($m) use ($cssDir, $key) {
        $u = trim($m[2]);
        // ignore data URLs and external links
        if ($u === '' || stripos($u, 'data:') === 0 || preg_match('#^https?://#i', $u) || strpos($u, '//') === 0) {
            return $m[0];
        }
        // Strip quotes if accidentally included
        $u = trim($u, "\"' ");
        // Resolve to relative public path
        if ($u[0] === '/') {
            $rel = ltrim($u, '/');
        } else {
            $rel = $cssDir !== '' ? ($cssDir . '/' . $u) : $u;
        }
        $rel = normalize_path($rel);
        if (strpos($rel, 'assets/') === 0 || strpos($rel, 'features/') === 0) {
            $proxied = build_proxy_url($rel, $key);
            return 'url(' . $proxied . ')';
        }
        return $m[0];
    }, $css);
}

// Fetch and validate params
$d = isset($_GET['d']) ? b64url_decode_str($_GET['d']) : '';
$e = isset($_GET['e']) ? (int)$_GET['e'] : 0;
$n = isset($_GET['n']) ? (string)$_GET['n'] : '';
$s = isset($_GET['s']) ? (string)$_GET['s'] : '';

if ($d === '' || $e <= 0 || $n === '' || $s === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Bad Request';
    exit;
}

// Normalize and ensure allowed directories
$relPath = normalize_path($d);
$full = realpath($publicRoot . '/' . $relPath);
if ($full === false || !is_file($full)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not Found';
    exit;
}
$allowed = false;
foreach ($allowedRoots as $root) {
    if ($root && strpos($full, $root) === 0) { $allowed = true; break; }
}
if (!$allowed) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    exit;
}

// Check expiry and signature
if ($e < time()) {
    http_response_code(410);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Expired';
    exit;
}
$expected = sign_token($signingKey, $relPath, $e, $n);
if (!constant_time_equals($expected, $s)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invalid signature';
    exit;
}

// Serve content with no-cache headers
$mime = detect_mime($full);
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Disposition: inline; filename="' . basename($relPath) . '"');

$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
if ($ext === 'css') {
    $css = @file_get_contents($full);
    if ($css === false) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Unable to read file';
        exit;
    }
    // Proxy URLs then strip comments
    $css = rewrite_css_urls($css, $relPath, $signingKey);
    $css = strip_css_comments($css);
    echo $css;
    exit;
}
if ($ext === 'js' || $ext === 'mjs') {
    $js = @file_get_contents($full);
    if ($js === false) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Unable to read file';
        exit;
    }
    echo $js;
    exit;
}
if ($ext === 'html' || $ext === 'htm') {
    $html = @file_get_contents($full);
    if ($html === false) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Unable to read file';
        exit;
    }
    $html = strip_html_comments($html);
    echo $html;
    exit;
}

// Stream file contents
$fp = @fopen($full, 'rb');
if (!$fp) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Unable to open file';
    exit;
}
// Optional: set length
$stat = @fstat($fp);
if ($stat && isset($stat['size'])) {
    header('Content-Length: ' . (string)$stat['size']);
}
fpassthru($fp);
@fclose($fp);
exit;
