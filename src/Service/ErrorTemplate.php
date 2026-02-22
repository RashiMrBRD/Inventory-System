<?php

namespace App\Service;

/**
 * Error Template Service
 * Renders styled error pages for database connection failures
 * Based on connector.html design
 */
class ErrorTemplate
{
    private const ERRORS = [
        'ECONNREFUSED' => [
            'icon' => '⛔',
            'label' => 'CONNECTION REFUSED',
            'head' => 'Failed to Connect to Database',
            'body' => 'The database server refused the connection. It may be offline or the port is blocked.'
        ],
        'ETIMEDOUT' => [
            'icon' => '⏱',
            'label' => 'CONNECTION TIMED OUT',
            'head' => 'Connection Timed Out',
            'body' => 'No response from the database server. Check your network or firewall settings.'
        ],
        'AUTH_FAILED' => [
            'icon' => '🔒',
            'label' => 'ACCESS DENIED',
            'head' => 'Access Denied',
            'body' => 'Authentication failed. Invalid credentials or insufficient database privileges.'
        ],
        'UNAUTHORIZED' => [
            'icon' => '🔒',
            'label' => 'ACCESS DENIED',
            'head' => 'Access Denied — Cannot Proceed',
            'body' => 'You are not authorized to access this database.'
        ],
        'FORBIDDEN' => [
            'icon' => '🚫',
            'label' => 'ACCESS FORBIDDEN',
            'head' => 'Access Forbidden',
            'body' => 'Your role does not have permission to access this database.'
        ],
        'NOT_FOUND' => [
            'icon' => '🔍',
            'label' => 'DATABASE NOT FOUND',
            'head' => 'Database Does Not Exist',
            'body' => 'The specified database was not found on this server.'
        ],
        'TLS_ERROR' => [
            'icon' => '🛡',
            'label' => 'TLS / SSL ERROR',
            'head' => 'Secure Connection Failed',
            'body' => 'TLS/SSL handshake failed. Certificate may be invalid or expired.'
        ],
        'DEFAULT' => [
            'icon' => '⚡',
            'label' => 'CANNOT PROCEED',
            'head' => 'Failed to Connect to Database',
            'body' => 'The application cannot establish a database connection. Cannot proceed.'
        ],
    ];

    /**
     * Render error page and output HTML
     * 
     * @param string $code Error code (ECONNREFUSED, ETIMEDOUT, AUTH_FAILED, etc.)
     * @param string $message Detailed error message
     * @param string|null $database Database name
     * @param string|null $host Host name
     * @param string $theme Theme (light, dark, system)
     */
    public static function render(
        string $code = 'DEFAULT',
        string $message = '',
        ?string $database = null,
        ?string $host = null,
        string $theme = 'light'
    ): void {
        // Clean any output buffers first
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Check if this is a JavaScript request (page-loader, css-loader, etc.)
        $isJsRequest = self::isJavaScriptRequest();
        
        if ($isJsRequest) {
            self::renderJavaScriptError($code, $message, $database, $host);
            return;
        }
        
        $error = self::ERRORS[$code] ?? self::ERRORS['DEFAULT'];
        $errorBody = $message ?: $error['body'];
        $dbLabel = $database ? "database: {$database}" : 'database: unreachable';
        $timestamp = date('H:i:s');

        echo <<<HTML
<!DOCTYPE html>
<html lang="en" data-theme="{$theme}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Database Status - Error</title>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Syne:wght@700;800;900&display=swap" rel="stylesheet">
<style>

/* LIGHT THEME */
:root,
[data-theme="light"] {
  --bg:            #f4f6f9;
  --surface:       #ffffff;
  --surface-2:     #f0f2f5;
  --border:        #dde1e8;
  --border-strong: #c5cad4;
  --text:          #1a1f2e;
  --text-2:        #4a5568;
  --muted:         #8892a4;

  --red:           #dc2626;
  --red-bg:        rgba(220,38,38,.06);
  --red-border:    rgba(220,38,38,.2);
  --red-glow:      0 0 24px rgba(220,38,38,.2);
  --red-soft:      #b91c1c;
  --red-text:      #991b1b;

  --amber:         #d97706;

  --log-bg:        #f8fafc;
  --log-info:      #2563eb;
  --log-ok:        #059669;
  --log-fail:      #dc2626;
  --log-warn:      #d97706;
  --log-ts:        #94a3b8;
  --cursor-color:  #059669;

  --shadow:        0 4px 24px rgba(0,0,0,.08), 0 1px 4px rgba(0,0,0,.04);
  --shadow-lg:     0 12px 48px rgba(0,0,0,.12), 0 2px 8px rgba(0,0,0,.06);
}

/* DARK THEME */
[data-theme="dark"] {
  --bg:            #080b10;
  --surface:       #0d1117;
  --surface-2:     #060a0e;
  --border:        #1e2733;
  --border-strong: #2d3748;
  --text:          #c9d1d9;
  --text-2:        #8b9ab0;
  --muted:         #4a5568;

  --red:           #ff3366;
  --red-bg:        rgba(255,51,102,.08);
  --red-border:    rgba(255,51,102,.25);
  --red-glow:      0 0 40px rgba(255,51,102,.35), 0 0 80px rgba(255,51,102,.15);
  --red-soft:      #ff6b8a;
  --red-text:      #ff8099;

  --amber:         #ffaa00;

  --log-bg:        #060a0e;
  --log-info:      #58a6ff;
  --log-ok:        #00ff88;
  --log-fail:      #ff3366;
  --log-warn:      #ffaa00;
  --log-ts:        #4a5568;
  --cursor-color:  #00ff88;

  --shadow:        0 4px 24px rgba(0,0,0,.4);
  --shadow-lg:     0 24px 64px rgba(0,0,0,.6);
}

/* BASE */
* { margin:0; padding:0; box-sizing:border-box; }
body {
  background: var(--bg);
  font-family: 'JetBrains Mono', monospace;
  color: var(--text);
  min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  overflow: hidden;
  transition: background .3s, color .3s;
}

/* Grid pattern */
body::before {
  content: ''; position: fixed; inset: 0; pointer-events: none;
  background-image:
    linear-gradient(var(--border) 1px, transparent 1px),
    linear-gradient(90deg, var(--border) 1px, transparent 1px);
  background-size: 40px 40px;
  opacity: .4;
  animation: grid-scroll 20s linear infinite;
}
[data-theme="light"] body::before { opacity: .18; }
@keyframes grid-scroll { to { background-position: 0 40px; } }

/* FAILED CARD */
.fail-card {
  background: transparent;
  border: none;
  border-radius: 20px;
  padding: 40px 44px;
  width: 460px;
  text-align: center;
  position: relative; z-index: 1;
  animation: card-fail .65s cubic-bezier(.16,1,.3,1) both;
}
@keyframes card-fail {
  0%   { opacity:0; transform:translateY(18px) scale(.96); }
  60%  { transform:translateX(-4px); }
  65%  { transform:translateX(4px); }
  70%  { transform:translateX(-3px); }
  75%  { transform:translateX(3px); }
  100% { opacity:1; transform:none; }
}

.lock-icon { font-size: 50px; margin-bottom: 16px; animation: throb 2.5s ease-in-out infinite; }
[data-theme="light"] .lock-icon { filter: drop-shadow(0 2px 8px rgba(220,38,38,.3)); }
@keyframes throb {
  0%,100% { filter: drop-shadow(0 0 8px rgba(220,38,38,.3)); }
  50%     { filter: drop-shadow(0 0 22px rgba(220,38,38,.7)); }
}
[data-theme="dark"] .lock-icon { animation: throb-dark 2.5s ease-in-out infinite; }
@keyframes throb-dark {
  0%,100% { filter: drop-shadow(0 0 10px rgba(255,51,102,.4)); }
  50%     { filter: drop-shadow(0 0 28px rgba(255,51,102,.9)); }
}

.fail-label {
  font-family: 'Syne', sans-serif; font-size: 10px; font-weight: 800;
  letter-spacing: .2em; text-transform: uppercase;
  color: var(--red); opacity: .65; margin-bottom: 10px;
}
.fail-title {
  font-family: 'Syne', sans-serif; font-size: 27px; font-weight: 900;
  color: var(--red); letter-spacing: -.4px; line-height: 1.2; margin-bottom: 6px;
}
.fail-db {
  display: inline-block; margin: 12px auto 16px;
  background: var(--red-bg); border: 1px solid var(--red-border);
  border-radius: 8px; padding: 7px 18px;
  font-size: 12px; font-weight: 700; color: var(--red-text);
}

.fail-reason {
  background: var(--red-bg); border: 1px solid var(--red-border);
  border-radius: 10px; padding: 14px 16px;
  font-size: 12px; color: var(--red-text);
  line-height: 1.75; text-align: left; margin-bottom: 20px;
}
.fail-reason .r-head { font-weight:700; color:var(--red); margin-bottom:6px; font-size:13px; }
.fail-reason .r-code { font-size:10px; color:var(--muted); margin-top:8px; letter-spacing:.06em; }

.fail-actions { display:flex; gap:10px; }
button {
  flex:1; padding:13px; border-radius:10px; border:none;
  font-family:'JetBrains Mono',monospace; font-size:12px; font-weight:700;
  cursor:pointer; letter-spacing:.05em; transition:all .2s;
}
.btn-retry { background:var(--red); color:#fff; }
.btn-retry:hover { transform:translateY(-1px); box-shadow:var(--red-glow); }
.btn-back {
  background: var(--surface-2); border: 1px solid var(--border);
  color: var(--muted);
}
.btn-back:hover { background:var(--border); color:var(--text); }

/* LOG DRAWER */
.log-drawer {
  max-height:0; overflow:hidden; transition:max-height .4s ease;
  background:var(--log-bg); border:0 solid var(--border);
  border-radius:0 0 14px 14px; width:460px;
}
.log-drawer.open { max-height:160px; border-width:0 1px 1px; }
.log-inner {
  padding:12px 14px; height:160px; overflow-y:auto; font-size:11px;
  scrollbar-width:thin; scrollbar-color:var(--border) transparent;
}
.log-line { line-height:1.9; }
.log-line .ts   { color: var(--log-ts); margin-right:8px; }
.log-line.fail  .msg { color: var(--log-fail); }
.log-line.warn  .msg { color: var(--log-warn); }
.cursor {
  display:inline-block; width:7px; height:13px;
  background:var(--cursor-color); animation:blink 1s step-end infinite; vertical-align:middle;
}
@keyframes blink { 50%{opacity:0} }

</style>
</head>
<body>

<!-- FAILED -->
<div class="fail-card">
  <div class="lock-icon">{$error['icon']}</div>
  <div class="fail-label">{$error['label']}</div>
  <div class="fail-title">{$error['head']}</div>
  <div class="fail-db">{$dbLabel}</div>
  <div class="fail-reason">
    <div class="r-head">Connection Error</div>
    <div>{$errorBody}</div>
    <div class="r-code">code: {$code}</div>
  </div>
  <div class="fail-actions">
    <button class="btn-retry" onclick="location.reload()">↺ Retry Connection</button>
    <button class="btn-back" onclick="history.back()">← Go Back</button>
  </div>
</div>
<div class="log-drawer open" id="log-drawer">
  <div class="log-inner" id="terminal">
    <div class="log-line fail"><span class="ts">[{$timestamp}]</span><span class="msg">✗ {$error['label']}</span></div>
    <div class="log-line fail"><span class="ts">[{$timestamp}]</span><span class="msg">  {$errorBody}</span></div>
    <div class="log-line warn"><span class="ts">[{$timestamp}]</span><span class="msg">  code: {$code}</span></div>
    <span class="cursor"></span>
  </div>
</div>

<script>
// Theme: resolve "system" preference
(function () {
  const html = document.documentElement;
  if (html.dataset.theme === 'system') {
    html.dataset.theme = window.matchMedia('(prefers-color-scheme: dark)').matches
      ? 'dark' : 'light';
  }
})();
</script>
</body>
</html>
HTML;
    }

    /**
     * Check if current request expects JavaScript (page-loader, css-loader, etc.)
     */
    private static function isJavaScriptRequest(): bool
    {
        // Check REQUEST_URI (may be modified by proxy)
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Check Cloudflare's original URI header
        $cfRequestUri = $_SERVER['HTTP_CF_REQUEST_URI'] ?? $_SERVER['HTTP_X_ORIGINAL_URI'] ?? '';
        $xRewriteUrl = $_SERVER['HTTP_X_REWRITE_URL'] ?? '';
        
        // Parse the path from REQUEST_URI (strip query string)
        $path = parse_url($requestUri, PHP_URL_PATH);
        if ($path === false) {
            $path = $requestUri;
        }
        
        // Also check alternative headers
        $cfPath = parse_url($cfRequestUri, PHP_URL_PATH) ?: '';
        $rewritePath = parse_url($xRewriteUrl, PHP_URL_PATH) ?: '';
        
        // Normalize paths - remove leading slash
        $path = ltrim($path, '/');
        $cfPath = ltrim($cfPath, '/');
        $rewritePath = ltrim($rewritePath, '/');
        
        // Debug logging
        error_log("[ErrorTemplate] REQUEST_URI: " . $requestUri . " | path: " . $path . " | cfPath: " . $cfPath . " | rewritePath: " . $rewritePath);
        
        // Check for loader endpoints
        $jsEndpoints = ['page-loader', 'css-loader', 'font-loader', 'head-loader', 'body-loader'];
        
        foreach ($jsEndpoints as $endpoint) {
            // Match exact endpoint or endpoint with query params in any of the paths
            if (self::matchesEndpoint($path, $endpoint) ||
                self::matchesEndpoint($cfPath, $endpoint) ||
                self::matchesEndpoint($rewritePath, $endpoint)) {
                error_log("[ErrorTemplate] Detected JS endpoint: " . $endpoint);
                return true;
            }
        }
        
        // Check Accept header for JavaScript
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'application/javascript') !== false || strpos($accept, 'text/javascript') !== false) {
            error_log("[ErrorTemplate] Detected JS via Accept header");
            return true;
        }
        
        error_log("[ErrorTemplate] NOT a JS request, will render HTML");
        return false;
    }
    
    /**
     * Check if path matches a JS endpoint
     */
    private static function matchesEndpoint(string $path, string $endpoint): bool
    {
        return $path === $endpoint || 
               strpos($path, $endpoint . '?') === 0 || 
               strpos($path, $endpoint . '/') === 0;
    }

    /**
     * Render JavaScript error for script requests
     */
    private static function renderJavaScriptError(
        string $code,
        string $message,
        ?string $database,
        ?string $host
    ): void {
        $error = self::ERRORS[$code] ?? self::ERRORS['DEFAULT'];
        $errorBody = addslashes($message ?: $error['body']);
        $errorTitle = addslashes($error['head']);
        $errorLabel = addslashes($error['label']);
        $dbLabel = $database ? addslashes($database) : 'unreachable';
        
        // Clean any output buffers and ensure we can send headers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Set JavaScript content type (only if headers not sent)
        if (!headers_sent()) {
            header('Content-Type: application/javascript; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        
        error_log("[ErrorTemplate] Rendering JavaScript error for code: " . $code);
        
        // Output JavaScript that shows error page
        echo <<<JS
(function(){
    'use strict';
    console.error('[Loader] Database connection failed: {$errorLabel}');
    console.error('[Loader] Error: {$errorBody}');
    console.error('[Loader] Code: {$code}');
    
    // Show error page
    var html = '<!DOCTYPE html><html lang="en" data-theme="light"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Database Error</title>';
    html += '<style>';
    html += '*{margin:0;padding:0;box-sizing:border-box}';
    html += 'body{background:#f4f6f9;font-family:"JetBrains Mono",monospace;color:#1a1f2e;min-height:100vh;display:flex;align-items:center;justify-content:center}';
    html += '.card{background:#fff;border:1px solid rgba(220,38,38,.2);border-radius:20px;padding:40px 44px;width:460px;text-align:center;box-shadow:0 12px 48px rgba(0,0,0,.12)}';
    html += '.icon{font-size:50px;margin-bottom:16px;animation:throb 2.5s ease-in-out infinite}';
    html += '@keyframes throb{0%,100%{filter:drop-shadow(0 0 8px rgba(220,38,38,.3))}50%{filter:drop-shadow(0 0 22px rgba(220,38,38,.7))}}';
    html += '.label{font-size:10px;font-weight:800;letter-spacing:.2em;text-transform:uppercase;color:#dc2626;opacity:.65;margin-bottom:10px}';
    html += '.title{font-size:27px;font-weight:900;color:#dc2626;letter-spacing:-.4px;line-height:1.2;margin-bottom:6px}';
    html += '.db{display:inline-block;margin:12px auto 16px;background:rgba(220,38,38,.06);border:1px solid rgba(220,38,38,.2);border-radius:8px;padding:7px 18px;font-size:12px;font-weight:700;color:#991b1b}';
    html += '.reason{background:rgba(220,38,38,.06);border:1px solid rgba(220,38,38,.2);border-radius:10px;padding:14px 16px;font-size:12px;color:#991b1b;line-height:1.75;text-align:left;margin-bottom:20px}';
    html += '.reason h{font-weight:700;color:#dc2626;margin-bottom:6px;font-size:13px}';
    html += '.reason code{font-size:10px;color:#8892a4;margin-top:8px;display:block;letter-spacing:.06em}';
    html += '.actions{display:flex;gap:10px}';
    html += 'button{flex:1;padding:13px;border-radius:10px;border:none;font-family:"JetBrains Mono",monospace;font-size:12px;font-weight:700;cursor:pointer;letter-spacing:.05em;transition:all .2s}';
    html += '.retry{background:#dc2626;color:#fff}.retry:hover{transform:translateY(-1px);box-shadow:0 0 24px rgba(220,38,38,.2)}';
    html += '.back{background:#f0f2f5;border:1px solid #dde1e8;color:#8892a4}.back:hover{background:#dde1e8;color:#1a1f2e}';
    html += '</style></head><body>';
    html += '<div class="card"><div class="icon">{$error['icon']}</div>';
    html += '<div class="label">{$errorLabel}</div>';
    html += '<div class="title">{$errorTitle}</div>';
    html += '<div class="db">database: {$dbLabel}</div>';
    html += '<div class="reason"><h>Connection Error</h>{$errorBody}<code>code: {$code}</code></div>';
    html += '<div class="actions"><button class="retry" onclick="location.reload()">↺ Retry Connection</button><button class="back" onclick="history.back()">← Go Back</button></div></div>';
    html += '</body></html>';
    
    document.open();
    document.write(html);
    document.close();
})();
JS;
    }

    /**
     * Determine error code from exception message
     * 
     * @param string $message Error message
     * @return string Error code
     */
    public static function getErrorCode(string $message): string
    {
        $message = strtolower($message);
        
        if (strpos($message, 'refused') !== false || strpos($message, 'connection refused') !== false) {
            return 'ECONNREFUSED';
        }
        if (strpos($message, 'timeout') !== false || strpos($message, 'timed out') !== false) {
            return 'ETIMEDOUT';
        }
        if (strpos($message, 'authentication') !== false || strpos($message, 'auth') !== false || 
            strpos($message, 'credentials') !== false || strpos($message, 'unauthorized') !== false) {
            return 'AUTH_FAILED';
        }
        if (strpos($message, 'forbidden') !== false || strpos($message, 'permission') !== false) {
            return 'FORBIDDEN';
        }
        if (strpos($message, 'not found') !== false || strpos($message, 'does not exist') !== false) {
            return 'NOT_FOUND';
        }
        if (strpos($message, 'ssl') !== false || strpos($message, 'tls') !== false || 
            strpos($message, 'certificate') !== false) {
            return 'TLS_ERROR';
        }
        
        return 'DEFAULT';
    }

    /**
     * Render and exit
     */
    public static function show(
        string $code = 'DEFAULT',
        string $message = '',
        ?string $database = null,
        ?string $host = null,
        string $theme = 'light'
    ): void {
        self::render($code, $message, $database, $host, $theme);
        exit;
    }
}
