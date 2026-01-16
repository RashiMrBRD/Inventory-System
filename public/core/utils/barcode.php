<?php

require __DIR__ . '/../../../vendor/autoload.php';

use App\Service\BarcodeService;

$code = isset($_GET['code']) ? trim((string) $_GET['code']) : '';

if ($code === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Missing code parameter.';
    exit;
}

$type = isset($_GET['type']) ? trim((string) $_GET['type']) : 'code128';

$service = new BarcodeService();

try {
    $png = $service->renderPng($code, $type);

    header('Content-Type: image/png');
    header('Cache-Control: private, max-age=31536000');

    echo $png;
} catch (Throwable $e) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Unable to generate barcode.';
}


