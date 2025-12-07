<?php
/**
 * Stealth Page Loader - Complete view-source protection with preloading
 * 
 * This script creates a completely blank page that preloads all resources
 * before showing any content. No HTML is visible during loading.
 */

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable'); // 1 year cache for JS
header('ETag: "' . md5_file(__FILE__) . '"');
header('Vary: Accept-Encoding');

$host = $_SERVER['HTTP_HOST'] ?? '';
$isDemoDomain = (strpos($host, 'demo.rashlink.eu.org') === 0);
$isLocalhost = (strpos($host, 'localhost') === 0 || $host === '127.0.0.1');

if (!$isDemoDomain && !$isLocalhost) {
    http_response_code(403);
    exit;
}

// Get variables from URL parameters
$error = $_GET['error'] ?? '';
$isFirstRun = ($_GET['isFirstRun'] ?? 'false') === 'true';

// Escape variables for JavaScript
$errorJs = addslashes(htmlspecialchars($error));

// The stealth loading JavaScript (will be encrypted)
$jsCode = "(function(){'use strict';var isFirstRun=" . ($isFirstRun ? 'true' : 'false') . ";var isDemoDomain=" . ($isDemoDomain ? 'true' : 'false') . ";var error='" . $errorJs . "';function preloadResources(){var resources=[{type:'css',url:'css-loader?files=core.css,components.css,utilities.css,login-page.css'},{type:'css',url:'font-loader'}];var loaded=0;var total=resources.length;function checkLoaded(){loaded++;if(loaded===total){createPage();}}resources.forEach(function(resource){if(resource.type==='css'){var link=document.createElement('link');link.rel='preload';link.as='style';link.href=resource.url;link.onload=function(){var styleLink=document.createElement('link');styleLink.rel='stylesheet';styleLink.href=resource.url;document.head.appendChild(styleLink);checkLoaded();};link.onerror=checkLoaded;document.head.appendChild(link);}});}function createPage(){document.documentElement.style.visibility='visible';var bodyHtml='<div class=\"login-wrapper\"><div class=\"login-header\"><h1 class=\"login-title\">Inventory Management</h1><p class=\"login-subtitle\">'+(isFirstRun?'Create the first admin account to start using the system':'Sign in to your account')+'</p></div><div class=\"login-form\">';if(error){bodyHtml+='<div class=\"alert alert-danger mb-4\"><svg width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\"/></svg><span>'+error+'</span></div>';}if(isFirstRun){bodyHtml+='<form method=\"POST\"><input type=\"hidden\" name=\"setup_admin\" value=\"1\"><div class=\"form-group\"><label for=\"full_name\" class=\"form-label\">Full Name</label><input type=\"text\" id=\"full_name\" name=\"full_name\" class=\"form-input\" placeholder=\"Enter full name (optional)\"></div><div class=\"form-group\"><label for=\"email\" class=\"form-label\">Email</label><input type=\"email\" id=\"email\" name=\"email\" class=\"form-input\" placeholder=\"Enter email (optional)\"></div><div class=\"form-group\"><label for=\"username\" class=\"form-label\">Username</label><input type=\"text\" id=\"username\" name=\"username\" class=\"form-input\" placeholder=\"Choose an admin username\" required></div><div class=\"form-group\"><label for=\"password\" class=\"form-label\">Password</label><input type=\"password\" id=\"password\" name=\"password\" class=\"form-input\" placeholder=\"Create a password\" required></div><div class=\"form-group\"><label for=\"confirm_password\" class=\"form-label\">Confirm Password</label><input type=\"password\" id=\"confirm_password\" name=\"confirm_password\" class=\"form-input\" placeholder=\"Confirm password\" required></div><button type=\"submit\" class=\"btn btn-primary w-full\">Create Admin Account</button></form>';}else{bodyHtml+='<form method=\"POST\"><div class=\"form-group\"><label for=\"username\" class=\"form-label\">Username</label><input type=\"text\" id=\"username\" name=\"username\" class=\"form-input\" placeholder=\"Enter username\" required></div><div class=\"form-group\"><label for=\"password\" class=\"form-label\">Password</label><input type=\"password\" id=\"password\" name=\"password\" class=\"form-input\" placeholder=\"Enter password\" required></div><button type=\"submit\" class=\"btn btn-primary w-full\">Sign In</button></form>';}if(!isFirstRun&&isDemoDomain){bodyHtml+='<div class=\"demo-info\"><strong>Demo Credentials</strong><code>Username: admin</code><code>Password: admin123</code></div>';}bodyHtml+='</div></div>';document.body.innerHTML=bodyHtml;setTimeout(function(){var firstInput=document.querySelector('input[type=\"text\"]');if(firstInput){firstInput.focus();}},100);}document.documentElement.style.visibility='hidden';preloadResources();})();";

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
$decryptorCode = "(function(){var k='InventoryManagement2025Secure';var d='{$encryptedCode}';function b64(e){return atob(e).split('').map(function(c){return c.charCodeAt(0)});}function xor(e,k){var r='';for(var i=0;i<e.length;i++){r+=String.fromCharCode(e[i]^k.charCodeAt(i%k.length));}return r;}try{var dec=xor(b64(d),k);eval(dec);}catch(e){console.error('Page loading failed');}})();";

// Compress and output
ob_start('ob_gzhandler');
echo $decryptorCode;
ob_end_flush();
?>
