<?php
/**
 * Stealth Page Loader - Complete view-source protection with preloading
 * 
 * This script creates a completely blank page that preloads all resources
 * before showing any content. No HTML is visible during loading.
 */

// CRITICAL: Suppress errors and set Content-Type BEFORE any other code
// This prevents PHP warnings from corrupting the JavaScript output
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE & ~E_STRICT);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Set JavaScript content type FIRST before any other output
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Error handler to ensure JavaScript output on errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $msg = addslashes($errstr);
    echo "console.error('[page-loader] PHP Error: {$msg} in {$errfile}:{$errline}');";
    return true;
});

// Load app config with error handling
$configPath = __DIR__ . '/../../../config/app.php';
if (!file_exists($configPath)) {
    echo "(function(){console.error('[page-loader] Config file not found at: {$configPath}');document.body.innerHTML='<div style=\"padding:20px;font-family:monospace;text-align:center;\"><h1>Configuration Error</h1><p>Config file not found</p></div>';})();";
    exit;
}

try {
    $appConfig = require $configPath;
} catch (Exception $e) {
    $msg = addslashes($e->getMessage());
    echo "(function(){console.error('[page-loader] Config load error: {$msg}');document.body.innerHTML='<div style=\"padding:20px;font-family:monospace;text-align:center;\"><h1>Configuration Error</h1><p>{$msg}</p></div>';})();";
    exit;
}

$host = $_SERVER['HTTP_HOST'] ?? '';

// Check if host is allowed using centralized configuration
if (!isHostAllowed($host, $appConfig['security']['access_control'])) {
    // Return JavaScript error (since this is loaded as a script)
    $errorJs = 'Access Denied: You do not have permission to access this resource.';
    echo "(function(){document.open();document.write('<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><title>Access Denied</title><style>*{margin:0;padding:0;box-sizing:border-box}body{background:#f4f6f9;font-family:monospace;min-height:100vh;display:flex;align-items:center;justify-content:center}.card{background:#fff;border:1px solid rgba(220,38,38,.2);border-radius:20px;padding:40px;text-align:center;max-width:400px}.icon{font-size:48px;margin-bottom:16px}.title{font-size:24px;font-weight:700;color:#dc2626;margin-bottom:8px}.msg{color:#666}</style></head><body><div class=\"card\"><div class=\"icon\">🔒</div><div class=\"title\">Access Denied</div><div class=\"msg\">{$errorJs}</div></div></body></html>');document.close();})();";
    exit;
}

$hostOnly = parse_url('http://' . $host, PHP_URL_HOST) ?: $host;
$isDemoDomain = (strpos($hostOnly, $appConfig['security']['access_control']['demo_domain']) === 0);

// Get variables from URL parameters
$error = $_GET['error'] ?? '';
$isFirstRun = ($_GET['isFirstRun'] ?? 'false') === 'true';

// Read config values directly from file (not from URL params to avoid caching issues)
$allowRegistration = $appConfig['security']['allow_registration'] ?? false;
$allowInvitations = $appConfig['security']['allow_invitations'] ?? false;

// Get CSRF token from cookie
$csrfToken = $_COOKIE['csrf_token'] ?? '';

// Escape variables for JavaScript
$errorJs = addslashes(htmlspecialchars($error));
$csrfTokenJs = addslashes(htmlspecialchars($csrfToken));

// The stealth loading JavaScript (will be encrypted)
$jsCode = "(function(){'use strict';console.log('[Page-Loader] Starting...');var isFirstRun=" . ($isFirstRun ? 'true' : 'false') . ";var isDemoDomain=" . ($isDemoDomain ? 'true' : 'false') . ";var allowRegistration=" . ($allowRegistration ? 'true' : 'false') . ";var allowInvitations=" . ($allowInvitations ? 'true' : 'false') . ";var csrfToken='" . $csrfTokenJs . "';if(typeof window.debugLog==='function'){window.debugLog('[Page-Loader] allowRegistration:',allowRegistration);}if(typeof window.debugLog==='function'){window.debugLog('[Page-Loader] allowInvitations:',allowInvitations);}var error='" . $errorJs . "';var loadTimeout=null;function preloadResources(){console.log('[Page-Loader] preloadResources called');var resources=[{type:'css',url:'css-loader?files=core.css,components.css,utilities.css,login-page.css'},{type:'css',url:'font-loader'}];var loaded=0;var total=resources.length;console.log('[Page-Loader] Loading',total,'resources');function checkLoaded(){loaded++;console.log('[Page-Loader] Resource loaded',loaded,'of',total);if(loaded===total){console.log('[Page-Loader] All resources loaded, calling createPage');createPage();}}resources.forEach(function(resource){if(resource.type==='css'){var link=document.createElement('link');link.rel='preload';link.as='style';link.href=resource.url;link.onload=function(){var styleLink=document.createElement('link');styleLink.rel='stylesheet';styleLink.href=resource.url;document.head.appendChild(styleLink);console.log('[Page-Loader] CSS loaded:',resource.url);checkLoaded();};link.onerror=function(){console.error('[Page-Loader] Failed to load:',resource.url);checkLoaded();};document.head.appendChild(link);}});loadTimeout=setTimeout(function(){console.warn('[Page-Loader] CSS loading timeout, showing page anyway');createPage();},5000);}function createPage(){console.log('[Page-Loader] createPage called');if(loadTimeout)clearTimeout(loadTimeout);console.log('[Page-Loader] Setting visibility to visible');document.documentElement.style.visibility='visible';}preloadResources();})();";

// XOR encryption function
function xorEncrypt($data, $key) {
    $result = '';
    for ($i = 0; $i < strlen($data); $i++) {
        $result .= chr(ord($data[$i]) ^ ord($key[$i % strlen($key)]));
    }
    return base64_encode($result);
}

// Encryption key
$encryptionKey = 'InventoryManagement2025Secure';

// Encrypt the JavaScript code
$encryptedCode = xorEncrypt($jsCode, $encryptionKey);

// Generate the decryptor and execution code
$decryptorCode = "(function(){var k='InventoryManagement2025Secure';var d='{$encryptedCode}';function b64(e){return atob(e).split('').map(function(c){return c.charCodeAt(0)});}function xor(e,k){var r='';for(var i=0;i<e.length;i++){r+=String.fromCharCode(e[i]^k.charCodeAt(i%k.length));}return r;}function c(n){var p=('; '+document.cookie).split('; '+n+'=');if(p.length===2){return p.pop().split(';').shift();}return '';}function inject(){try{var t=c('csrf_token');if(!t){return;}var fs=document.getElementsByTagName('form');for(var i=0;i<fs.length;i++){var f=fs[i];var m=(f.getAttribute('method')||f.method||'').toString().toUpperCase();if(m!=='POST'){continue;}if(f.querySelector('input[name=csrf_token]')){continue;}var inp=document.createElement('input');inp.type='hidden';inp.name='csrf_token';inp.value=t;f.insertBefore(inp,f.firstChild);}}catch(e){}}try{var dec=xor(b64(d),k);eval(dec);inject();if(typeof MutationObserver!=='undefined'){var o=new MutationObserver(function(){inject();});o.observe(document.documentElement||document.body,{childList:true,subtree:true});setTimeout(function(){try{o.disconnect();}catch(e){}},5000);}else{var n=0;var iv=setInterval(function(){inject();n++;if(n>50){clearInterval(iv);}},100);}}catch(e){console.error('Page loading failed');}})();";

// Compress and output
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo $decryptorCode;
?>
