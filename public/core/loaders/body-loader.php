<?php
/**
 * Encrypted Body Content Loader - Maximum view-source protection
 * 
 * This script generates encrypted JavaScript that dynamically creates body content.
 * All content including comments is encrypted to prevent any visibility in view-source.
 */

// CRITICAL: Suppress errors and set Content-Type BEFORE any other code
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE & ~E_STRICT);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable'); // 1 year cache for JS
header('ETag: "' . md5_file(__FILE__) . '"');
header('Vary: Accept-Encoding');

// Load app config
$appConfig = require __DIR__ . '/../../../config/app.php';

$host = $_SERVER['HTTP_HOST'] ?? '';

// Check if host is allowed using centralized configuration
if (!isHostAllowed($host, $appConfig['security']['access_control'])) {
    http_response_code(403);
    echo '/* Access denied */';
    exit;
}

$hostOnly = parse_url('http://' . $host, PHP_URL_HOST) ?: $host;
$isDemoDomain = (strpos($hostOnly, $appConfig['security']['access_control']['demo_domain']) === 0);

// Get variables from URL parameters
$error = $_GET['error'] ?? '';
$isFirstRun = ($_GET['isFirstRun'] ?? 'false') === 'true';

// Escape variables for JavaScript
$errorJs = addslashes(htmlspecialchars($error));

// The actual JavaScript code (will be encrypted)
$jsCode = "(function(){'use strict';console.log('[Body-Loader] Script starting...');var isFirstRun=" . ($isFirstRun ? 'true' : 'false') . ";var isDemoDomain=" . ($isDemoDomain ? 'true' : 'false') . ";var error='" . $errorJs . "';function createBodyContent(){console.log('[Body-Loader] createBodyContent called');var bodyHtml='<div class=\"login-wrapper\"><div class=\"login-header\"><h1 class=\"login-title\">Inventory Management</h1><p class=\"login-subtitle\">'+(isFirstRun?'Create the first admin account to start using the system':'Sign in to your account')+'</p></div><div class=\"login-form\">';if(error){bodyHtml+='<div class=\"alert alert-danger mb-4\"><svg width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\"/></svg><span>'+error+'</span></div>';}if(isFirstRun){bodyHtml+='<form method=\"POST\" id=\"loginForm\"><input type=\"hidden\" name=\"setup_admin\" value=\"1\"><div class=\"form-group\"><label for=\"full_name\" class=\"form-label\">Full Name</label><input type=\"text\" id=\"full_name\" name=\"full_name\" class=\"form-input\" placeholder=\"Enter full name (optional)\"></div><div class=\"form-group\"><label for=\"email\" class=\"form-label\">Email</label><input type=\"email\" id=\"email\" name=\"email\" class=\"form-input\" placeholder=\"Enter email (optional)\"></div><div class=\"form-group\"><label for=\"username\" class=\"form-label\">Username</label><input type=\"text\" id=\"username\" name=\"username\" class=\"form-input\" placeholder=\"Choose an admin username\" required autofocus></div><div class=\"form-group\"><label for=\"password\" class=\"form-label\">Password</label><input type=\"password\" id=\"password\" name=\"password\" class=\"form-input\" placeholder=\"Create a password\" required></div><div class=\"form-group\"><label for=\"confirm_password\" class=\"form-label\">Confirm Password</label><input type=\"password\" id=\"confirm_password\" name=\"confirm_password\" class=\"form-input\" placeholder=\"Confirm password\" required></div><button type=\"submit\" class=\"btn btn-primary w-full\" id=\"submitBtn\"><span class=\"btn-text\">Create Admin Account</span><span class=\"spinner spinner-sm\" style=\"display:none;width:1rem;height:1rem;border-width:2px;\"></span></button></form>';}else{bodyHtml+='<form method=\"POST\" id=\"loginForm\"><div class=\"form-group\"><label for=\"username\" class=\"form-label\">Username</label><input type=\"text\" id=\"username\" name=\"username\" class=\"form-input\" placeholder=\"Enter username\" required autofocus></div><div class=\"form-group\"><label for=\"password\" class=\"form-label\">Password</label><input type=\"password\" id=\"password\" name=\"password\" class=\"form-input\" placeholder=\"Enter password\" required></div><button type=\"submit\" class=\"btn btn-primary w-full\" id=\"submitBtn\"><span class=\"btn-text\">Sign In</span><span class=\"spinner spinner-sm\" style=\"display:none;width:1rem;height:1rem;border-width:2px;\"></span></button></form>';}if(!isFirstRun&&isDemoDomain){bodyHtml+='<div class=\"demo-info\"><strong>Demo Credentials</strong><code>Username: admin</code><code>Password: admin123</code></div>';}bodyHtml+='</div></div>';console.log('[Body-Loader] About to set body.innerHTML');document.body.innerHTML=bodyHtml;console.log('[Body-Loader] body.innerHTML set, looking for login-form');var loginForm=document.querySelector('.login-form');console.log('[Body-Loader] loginForm found after injection:',!!loginForm);var firstInput=document.querySelector('input[autofocus]');if(firstInput){firstInput.focus();}setupFormLoading();}function setupFormLoading(){var form=document.getElementById('loginForm');if(!form)return;form.addEventListener('submit',function(e){var btn=document.getElementById('submitBtn');if(!btn)return;var btnText=btn.querySelector('.btn-text');var spinner=btn.querySelector('.spinner');if(btn.disabled)return;btn.disabled=true;btn.style.opacity='0.8';btn.style.cursor='wait';if(btnText)btnText.style.opacity='0';if(spinner){spinner.style.display='inline-block';}});}console.log('[Body-Loader] Document readyState:',document.readyState);if(document.readyState==='loading'){console.log('[Body-Loader] Adding DOMContentLoaded listener');document.addEventListener('DOMContentLoaded',createBodyContent);}else{console.log('[Body-Loader] Calling createBodyContent immediately');createBodyContent();}})();";

// Simple XOR encryption
function xorEncrypt($data, $key) {
    $result = '';
    for ($i = 0; $i < strlen($data); $i++) {
        $result .= chr(ord($data[$i]) ^ ord($key[$i % strlen($key)]));
    }
    return base64_encode($result);
}

// Encryption key (you can change this)
$encryptionKey = 'InventoryManagement2025Secure';

// Encrypt the JavaScript code
$encryptedCode = xorEncrypt($jsCode, $encryptionKey);

// Generate the decryptor and execution code with detailed error logging
$decryptorCode = "(function(){try{console.log('[Body-Loader Decryptor] Starting decryption...');var k='InventoryManagement2025Secure';var d='{$encryptedCode}';console.log('[Body-Loader Decryptor] Encrypted data length:',d.length);function b64(e){try{return atob(e).split('').map(function(c){return c.charCodeAt(0)});}catch(err){console.error('[Body-Loader Decryptor] Base64 decode failed:',err);throw err;}}function xor(e,k){var r='';for(var i=0;i<e.length;i++){r+=String.fromCharCode(e[i]^k.charCodeAt(i%k.length));}return r;}var decBytes=b64(d);console.log('[Body-Loader Decryptor] Decoded to',decBytes.length,'bytes');var dec=xor(decBytes,k);console.log('[Body-Loader Decryptor] Decrypted, length:',dec.length);console.log('[Body-Loader Decryptor] About to eval...');eval(dec);console.log('[Body-Loader Decryptor] eval completed successfully');}catch(e){console.error('[Body-Loader Decryptor] FATAL ERROR:',e.message);console.error('[Body-Loader Decryptor] Stack:',e.stack);console.error('[Body-Loader Decryptor] This usually indicates encryption/decryption failure');}})();";

// Compress and output
ob_start('ob_gzhandler');
echo $decryptorCode;
ob_end_flush();
?>
