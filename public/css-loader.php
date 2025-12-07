<?php
/**
 * Dynamic CSS Loader - Hides style content from view-source
 * 
 * This script serves CSS content dynamically without exposing it in the HTML source.
 * It loads CSS files and outputs them with proper headers, making styles invisible 
 * when viewing page source via view-source: protocol.
 * 
 * Usage: 
 * - Single file: <link rel="stylesheet" href="css-loader.php?file=style.css">
 * - Multiple files: <link rel="stylesheet" href="css-loader.php?files=core.css,components.css,utilities.css,login-page.css">
 */

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable'); // 1 year cache for CSS
header('ETag: "' . md5_file(__FILE__) . '"');
header('Vary: Accept-Encoding');
header('X-Content-Type-Options: nosniff');

// Security: Only allow CSS files within the css and assets/css directories
$allowedFiles = [
    'core.css',
    'components.css', 
    'utilities.css',
    'form.css',
    'login.css',
    'login-page.css',
    'style.css'
];

$cssContent = '';

// Handle single file request (backward compatibility)
if (isset($_GET['file'])) {
    $requestedFile = $_GET['file'];
    $filePath = __DIR__ . '/css/' . basename($requestedFile);
    
    if (in_array($requestedFile, $allowedFiles) && file_exists($filePath)) {
        $cssContent = file_get_contents($filePath);
    }
}
// Handle multiple files request
elseif (isset($_GET['files'])) {
    $requestedFiles = $_GET['files'];
    $fileArray = explode(',', $requestedFiles);
    
    foreach ($fileArray as $file) {
        $file = trim($file);
        
        if (in_array($file, $allowedFiles)) {
            if (in_array($file, ['core.css', 'components.css', 'utilities.css'])) {
                $filePath = __DIR__ . '/assets/css/' . $file;
            } else {
                $filePath = __DIR__ . '/css/' . $file;
            }
            
            if (file_exists($filePath)) {
                $cssContent .= "/* === $file === */\n";
                $cssContent .= file_get_contents($filePath);
                $cssContent .= "\n\n";
            }
        }
    }
}

if (empty($cssContent)) {
    http_response_code(404);
    echo '/* CSS files not found */';
    exit;
}

// Compress and output CSS
ob_start('ob_gzhandler');
echo $cssContent;
ob_end_flush();
?>
