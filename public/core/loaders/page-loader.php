<?php
/**
 * Stealth Page Loader - Complete view-source protection with preloading
 * 
 * This script creates a completely blank page that preloads all resources
 * before showing any content. No HTML is visible during loading.
 */

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Load app config
$appConfig = require __DIR__ . '/../../../config/app.php';

$host = $_SERVER['HTTP_HOST'] ?? '';

// Check if host is allowed using centralized configuration
if (!isHostAllowed($host, $appConfig['security']['access_control'])) {
    // Return generic error to avoid revealing security information
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access Denied</title></head><body style="font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0;"><div style="text-align: center;"><h1 style="color: #333;">Access Denied</h1><p style="color: #666;">You do not have permission to access this resource.</p></div></body></html>';
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
