<?php
header('Expires: 0');
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Invoice;
use App\Helper\CurrencyHelper;
use Dompdf\Dompdf;
use Dompdf\Options;

$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$id = isset($_GET['id']) ? trim((string)$_GET['id']) : '';
if ($id === '') {
    http_response_code(400);
    echo 'Missing invoice id';
    exit;
}

try {
    $invoiceModel = new Invoice();
    $invoice = $invoiceModel->getById($id);
    if (!$invoice) {
        http_response_code(404);
        echo 'Invoice not found';
        exit;
    }

    $invNo = (string)($invoice['invoice_number'] ?? ('INV-' . substr($id, -6)));
    $customer = htmlspecialchars((string)($invoice['customer'] ?? 'Customer'));
    $customerEmail = htmlspecialchars((string)($invoice['customer_email'] ?? ''));
    $customerAddr = nl2br(htmlspecialchars((string)($invoice['customer_address'] ?? '')));
    $date = htmlspecialchars((string)($invoice['date'] ?? date('Y-m-d')));
    $due = htmlspecialchars((string)($invoice['due_date'] ?? ''));
    $items = isset($invoice['items']) && is_array($invoice['items']) ? $invoice['items'] : [];
    $subtotal = (float)($invoice['subtotal'] ?? 0);
    $discount = (float)($invoice['discount_amount'] ?? 0);
    $tax = (float)($invoice['tax'] ?? 0);
    $shipping = (float)($invoice['shipping_cost'] ?? 0);
    $total = (float)($invoice['total'] ?? 0);

    $money = function($v) {
        return htmlspecialchars(CurrencyHelper::format((float)$v), ENT_QUOTES, 'UTF-8');
    };

    $rowsHtml = '';
    foreach ($items as $it) {
        $desc = htmlspecialchars((string)($it['description'] ?? ''));
        $qty = (float)($it['quantity'] ?? 0);
        $price = (float)($it['unit_price'] ?? 0);
        $line = (float)($it['total'] ?? ($qty * $price));
        $rowsHtml .= '<tr>'
            . '<td style="padding:8px 10px; border-bottom:1px solid #e5e7eb; width:55%">' . $desc . '</td>'
            . '<td style="padding:8px 10px; border-bottom:1px solid #e5e7eb; text-align:right; width:15%">' . number_format($qty, 2) . '</td>'
            . '<td style="padding:8px 10px; border-bottom:1px solid #e5e7eb; text-align:right; width:15%">' . $money($price) . '</td>'
            . '<td style="padding:8px 10px; border-bottom:1px solid #e5e7eb; text-align:right; width:15%">' . $money($line) . '</td>'
            . '</tr>';
    }

    $company = htmlspecialchars((string)($auth->getCurrentUser()['company'] ?? ($_SERVER['HTTP_HOST'] ?? 'Your Company')));

    $html = '<!doctype html><html><head><meta charset="utf-8">'
      . '<style>
            @page { margin: 28px 28px; }
            body { font-family: "DejaVu Sans", Arial, Helvetica, sans-serif; color: #0f172a; }
            .wrap { }
            .header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:18px; }
            .left h1 { font-size:24px; line-height:1.15; margin:0 0 4px 0; }
            .meta { color:#475569; font-size:12px; line-height:1.4; }
            .brand { text-align:right; }
            .brand .name { font-weight:700; font-size:13px; }
            .brand .host { color:#64748b; font-size:12px; }
            .card { border: 1px solid #e2e8f0; border-radius:8px; padding:14px; background:#ffffff; }
            .billto-title { font-weight:700; margin-bottom:6px; color:#0f172a; }
            table { width:100%; border-collapse:collapse; margin-top:12px; font-size:12px; }
            thead th { background:#eef2f7; color:#0f172a; text-transform:uppercase; letter-spacing:0.06em; font-size:11px; border-bottom:1px solid #e2e8f0; padding:8px 10px; text-align:right; }
            thead th:first-child { text-align:left; }
            tbody td { padding:8px 10px; border-bottom:1px solid #e5e7eb; }
            tbody tr:nth-child(even) td { background:#fafafa; }
            tbody td:first-child { text-align:left; }
            tbody td:not(:first-child) { text-align:right; }
            .totals { width:100%; margin-top:10px; }
            .totals td { border:none; padding:6px 10px; font-size:12px; }
            .totals .label { color:#334155; text-align:right; }
            .totals .value { text-align:right; font-weight:600; }
            .total-row .label, .total-row .value { font-weight:700; }
            .note { margin-top:14px; font-size:12px; color:#475569; }
        </style>'
      . '</head><body>'
      . '<div class="wrap">'
      . '<div class="header">'
      .   '<div class="left">'
      .     '<h1>Invoice ' . htmlspecialchars($invNo) . '</h1>'
      .     '<div class="meta">Issued: ' . $date . '</div>'
      .     ($due !== '' ? '<div class="meta">Due: ' . $due . '</div>' : '')
      .   '</div>'
      .   '<div class="brand">'
      .       '<div class="name">' . $company . '</div>'
      .       '<div class="host">' . htmlspecialchars((string)($_SERVER['HTTP_HOST'] ?? '')) . '</div>'
      .   '</div>'
      . '</div>'
      . '<div class="card" style="margin-bottom:12px">'
      .   '<div class="billto-title">Bill To</div>'
      .   '<div>' . $customer . '</div>'
      .   ($customerEmail !== '' ? '<div class="muted">' . $customerEmail . '</div>' : '')
      .   ($customerAddr !== '' ? '<div class="muted">' . $customerAddr . '</div>' : '')
      . '</div>'
      . '<table>'
      .   '<thead><tr>'
      .     '<th style="width:55%">Description</th>'
      .     '<th style="width:15%">Qty</th>'
      .     '<th style="width:15%">Unit Price</th>'
      .     '<th style="width:15%">Amount</th>'
      .   '</tr></thead>'
      .   '<tbody>' . $rowsHtml . '</tbody>'
      . '</table>'
      . '<table class="totals">'
      .   '<tbody>'
      .     '<tr><td style="width:70%"></td><td class="label" style="width:15%">Subtotal</td><td class="value" style="width:15%">' . $money($subtotal) . '</td></tr>'
      .     '<tr><td></td><td class="label">Discount</td><td class="value">' . $money($discount) . '</td></tr>'
      .     '<tr><td></td><td class="label">Tax</td><td class="value">' . $money($tax) . '</td></tr>'
      .     '<tr><td></td><td class="label">Shipping</td><td class="value">' . $money($shipping) . '</td></tr>'
      .     '<tr class="total-row"><td></td><td class="label">Total</td><td class="value">' . $money($total) . '</td></tr>'
      .   '</tbody>'
      . '</table>'
      . '<div class="note">Thank you for your business.</div>'
      . '</div>'
      . '</body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = 'invoice_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $invNo) . '.pdf';
    $pdf = $dompdf->output();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo $pdf;
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'Server error: ' . $e->getMessage();
    exit;
}
