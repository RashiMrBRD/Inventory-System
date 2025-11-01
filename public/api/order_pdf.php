<?php
header('Expires: 0');
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Order;
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
    echo 'Missing order id';
    exit;
}

try {
    $orderModel = new Order();
    $order = $orderModel->findById($id);
    if (!$order) {
        http_response_code(404);
        echo 'Order not found';
        exit;
    }

    $orderNo = htmlspecialchars((string)($order['order_number'] ?? ('ORD-' . substr($id, -6))));
    
    // Get actual customer/vendor information
    $customerName = '';
    if (!empty($order['customer_name'])) {
        $customerName = htmlspecialchars((string)$order['customer_name']);
    } elseif (!empty($order['vendor_name'])) {
        $customerName = htmlspecialchars((string)$order['vendor_name']);
    } elseif (!empty($order['customer'])) {
        $customerName = htmlspecialchars((string)$order['customer']);
    } elseif (!empty($order['vendor'])) {
        $customerName = htmlspecialchars((string)$order['vendor']);
    }
    
    $customerEmail = htmlspecialchars((string)($order['email'] ?? $order['customer_email'] ?? ''));
    $customerPhone = htmlspecialchars((string)($order['phone'] ?? $order['customer_phone'] ?? ''));
    $shippingAddr = htmlspecialchars((string)($order['shipping_address'] ?? ''));
    $billingAddr = htmlspecialchars((string)($order['billing_address'] ?? ''));
    $orderDate = htmlspecialchars((string)($order['order_date'] ?? $order['date'] ?? date('Y-m-d')));
    $dueDate = htmlspecialchars((string)($order['due_date'] ?? ''));
    $orderType = htmlspecialchars((string)($order['order_type'] ?? $order['type'] ?? 'Sales'));
    $status = htmlspecialchars((string)($order['status'] ?? 'Pending'));
    $paymentTerms = htmlspecialchars((string)($order['payment_terms'] ?? 'net_30'));
    $items = isset($order['items']) && is_array($order['items']) ? $order['items'] : [];
    $subtotal = (float)($order['subtotal'] ?? 0);
    $discount = (float)($order['discount_amount'] ?? 0);
    $tax = (float)($order['tax'] ?? 0);
    $total = (float)($order['total'] ?? 0);
    $amountPaid = (float)($order['amount_paid'] ?? 0);
    $balanceDue = $total - $amountPaid;
    $notes = htmlspecialchars((string)($order['notes'] ?? ''));

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
            . '<td>' . $desc . '</td>'
            . '<td class="qty">' . number_format($qty, 0) . '</td>'
            . '<td class="price">' . $money($price) . '</td>'
            . '<td class="amount">' . $money($line) . '</td>'
            . '</tr>';
    }

    $company = htmlspecialchars((string)($auth->getCurrentUser()['company'] ?? ($_SERVER['HTTP_HOST'] ?? 'Your Company')));

    // Simple, clean PDF design - black/gray on white
    $html = '<!doctype html><html><head><meta charset="utf-8">'
      . '<style>
            @page { margin: 40px 40px; }
            body { 
                font-family: "DejaVu Sans", Arial, Helvetica, sans-serif; 
                color: #1f2937;
                line-height: 1.5;
                max-width: 800px;
                margin: 0 auto;
            }
            .header { 
                margin-bottom: 30px; 
                border-bottom: 2px solid #000;
                padding-bottom: 20px;
            }
            .header h1 { 
                font-size: 28px; 
                margin: 0 0 8px 0; 
                color: #000;
                font-weight: 700;
            }
            .header-info { 
                display: table;
                width: 100%;
            }
            .header-left, .header-right {
                display: table-cell;
                vertical-align: top;
            }
            .header-right {
                text-align: right;
            }
            .meta { 
                color: #4b5563; 
                font-size: 12px; 
                margin: 3px 0;
            }
            .meta-label {
                font-weight: 600;
                color: #374151;
            }
            .info-box { 
                border: 1px solid #d1d5db; 
                padding: 16px; 
                margin-bottom: 20px;
                background: #f9fafb;
            }
            .info-box-title { 
                font-weight: 700; 
                margin-bottom: 10px; 
                color: #111827; 
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .info-content {
                font-size: 12px;
                color: #374151;
                line-height: 1.6;
            }
            .info-grid {
                display: table;
                width: 100%;
            }
            .info-col {
                display: table-cell;
                width: 50%;
                padding-right: 15px;
                vertical-align: top;
            }
            .info-col:last-child {
                padding-right: 0;
                padding-left: 15px;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 20px 0;
                font-size: 12px;
            }
            thead th { 
                background: #f3f4f6; 
                color: #111827; 
                text-transform: uppercase; 
                font-size: 10px;
                font-weight: 700;
                border: 1px solid #d1d5db;
                padding: 10px 12px; 
                text-align: left;
            }
            thead th.qty { text-align: center; }
            thead th.price, thead th.amount { text-align: right; }
            tbody td { 
                padding: 10px 12px; 
                border: 1px solid #e5e7eb;
                background: #fff;
            }
            tbody td.qty { text-align: center; }
            tbody td.price, tbody td.amount { text-align: right; }
            .totals-table { 
                margin-top: 20px;
                width: 100%;
            }
            .totals-table td { 
                border: none; 
                padding: 6px 12px; 
                font-size: 12px;
            }
            .totals-table .label { 
                text-align: right; 
                color: #4b5563;
                width: 70%;
            }
            .totals-table .value { 
                text-align: right; 
                font-weight: 600;
                width: 30%;
            }
            .totals-table .total-row .label,
            .totals-table .total-row .value {
                font-weight: 700;
                font-size: 13px;
                padding-top: 8px;
                border-top: 2px solid #d1d5db;
            }
            .totals-table .balance-row .label,
            .totals-table .balance-row .value {
                font-weight: 700;
                color: #dc2626;
            }
            .note-box { 
                margin-top: 30px; 
                padding: 15px;
                background: #f9fafb;
                font-size: 11px; 
                color: #4b5563;
            }
            .note-box.centered {
                text-align: center;
                border: none;
            }
        </style>'
      . '</head><body>'
      . '<div class="header">'
      .   '<h1>Order ' . $orderNo . '</h1>'
      .   '<div class="header-info">'
      .     '<div class="header-left">'
      .       '<div class="meta"><span class="meta-label">Order Date:</span> ' . $orderDate . '</div>'
      .       ($dueDate !== '' ? '<div class="meta"><span class="meta-label">Due Date:</span> ' . $dueDate . '</div>' : '')
      .       '<div class="meta"><span class="meta-label">Type:</span> ' . $orderType . '</div>'
      .       '<div class="meta"><span class="meta-label">Status:</span> ' . strtoupper($status) . '</div>'
      .     '</div>'
      .     '<div class="header-right">'
      .       '<div class="meta" style="font-weight:600;">' . $company . '</div>'
      .       '<div class="meta">' . htmlspecialchars((string)($_SERVER['HTTP_HOST'] ?? '')) . '</div>'
      .     '</div>'
      .   '</div>'
      . '</div>'
      . '<div class="info-box">'
      .   '<div class="info-box-title">Customer Information</div>'
      .   '<div class="info-content">'
      .     ($customerName !== '' ? '<div style="font-weight:700; margin-bottom:5px;">' . $customerName . '</div>' : '<div style="color:#9ca3af;">No customer information</div>')
      .     ($customerEmail !== '' ? '<div>Email: ' . $customerEmail . '</div>' : '')
      .     ($customerPhone !== '' ? '<div>Phone: ' . $customerPhone . '</div>' : '')
      .   '</div>'
      . '</div>'
      . ($paymentTerms !== '' ? 
          '<div class="info-box">' .
            '<div class="info-box-title">Payment Terms</div>' .
            '<div class="info-content">' . $paymentTerms . '</div>' .
          '</div>' : '')
      . ($shippingAddr !== '' || $billingAddr !== '' ? 
          '<div class="info-grid">' .
            ($shippingAddr !== '' ? 
              '<div class="info-col">' .
                '<div class="info-box">' .
                  '<div class="info-box-title">Shipping Address</div>' .
                  '<div class="info-content">' . nl2br($shippingAddr) . '</div>' .
                '</div>' .
              '</div>' : '') .
            ($billingAddr !== '' ? 
              '<div class="info-col">' .
                '<div class="info-box">' .
                  '<div class="info-box-title">Billing Address</div>' .
                  '<div class="info-content">' . nl2br($billingAddr) . '</div>' .
                '</div>' .
              '</div>' : '') .
          '</div>' : '')
      . '<table>'
      .   '<thead><tr>'
      .     '<th style="width:50%;">DESCRIPTION</th>'
      .     '<th class="qty" style="width:15%;">QTY</th>'
      .     '<th class="price" style="width:17.5%;">UNIT PRICE</th>'
      .     '<th class="amount" style="width:17.5%;">AMOUNT</th>'
      .   '</tr></thead>'
      .   '<tbody>' . $rowsHtml . '</tbody>'
      . '</table>'
      . '<table class="totals-table">'
      .   '<tbody>'
      .     '<tr><td class="label">Subtotal</td><td class="value">' . $money($subtotal) . '</td></tr>'
      .     '<tr><td class="label">Discount</td><td class="value">-' . $money($discount) . '</td></tr>'
      .     '<tr><td class="label">Tax (10%)</td><td class="value">' . $money($tax) . '</td></tr>'
      .     '<tr class="total-row"><td class="label">Total</td><td class="value">' . $money($total) . '</td></tr>'
      .     '<tr><td class="label">Amount Paid</td><td class="value">' . $money($amountPaid) . '</td></tr>'
      .     '<tr class="balance-row"><td class="label">Balance Due</td><td class="value">' . $money($balanceDue) . '</td></tr>'
      .   '</tbody>'
      . '</table>'
      . ($notes !== '' ? '<div class="note-box"><strong>Notes:</strong><br>' . nl2br($notes) . '</div>' : '')
      . '<div class="note-box centered">Thank you for your business!</div>'
      . '</body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = 'order_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $orderNo) . '.pdf';
    $pdf = $dompdf->output();

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo $pdf;
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    echo 'Server error: ' . $e->getMessage();
    exit;
}
