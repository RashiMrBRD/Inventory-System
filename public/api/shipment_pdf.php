<?php
header('Expires: 0');
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Controller\AuthController;
use App\Model\Shipment;
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
    echo 'Missing shipment id';
    exit;
}

try {
    $shipmentModel = new Shipment();
    $shipment = $shipmentModel->findById($id);
    if (!$shipment) {
        http_response_code(404);
        echo 'Shipment not found';
        exit;
    }

    $shipmentNo = htmlspecialchars((string)($shipment['shipment_number'] ?? ('SHP-' . substr($id, -6))));
    $orderNo = htmlspecialchars((string)($shipment['order'] ?? '-'));
    $customerName = htmlspecialchars((string)($shipment['customer'] ?? ''));
    $carrier = htmlspecialchars((string)($shipment['carrier'] ?? ''));
    $tracking = htmlspecialchars((string)($shipment['tracking'] ?? ''));
    $shipDate = htmlspecialchars((string)($shipment['date'] ?? date('Y-m-d')));
    $expectedDelivery = htmlspecialchars((string)($shipment['expected_delivery'] ?? ''));
    $status = htmlspecialchars((string)($shipment['status'] ?? 'Pending'));
    $address = htmlspecialchars((string)($shipment['address'] ?? ''));
    $serviceType = htmlspecialchars((string)($shipment['service_type'] ?? 'standard'));
    $weight = (float)($shipment['weight'] ?? 0);
    $notes = htmlspecialchars((string)($shipment['notes'] ?? ''));

    $company = htmlspecialchars((string)($auth->getCurrentUser()['company'] ?? ($_SERVER['HTTP_HOST'] ?? 'Your Company')));

    // Status badge colors
    $statusColors = [
        'pending' => ['bg' => '#f3f4f6', 'text' => '#374151'],
        'in-transit' => ['bg' => '#dbeafe', 'text' => '#1e40af'],
        'out-for-delivery' => ['bg' => '#fef3c7', 'text' => '#92400e'],
        'delivered' => ['bg' => '#d1fae5', 'text' => '#065f46'],
        'returned' => ['bg' => '#fee2e2', 'text' => '#991b1b'],
    ];
    $statusKey = strtolower(str_replace(' ', '-', $status));
    $statusBg = $statusColors[$statusKey]['bg'] ?? '#f3f4f6';
    $statusText = $statusColors[$statusKey]['text'] ?? '#374151';

    // Clean PDF design
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
            .header-subtitle {
                font-size: 14px;
                color: #6b7280;
                margin: 0;
            }
            .header-info { 
                display: table;
                width: 100%;
                margin-top: 15px;
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
            .status-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                background: ' . $statusBg . ';
                color: ' . $statusText . ';
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
            .info-box-content { 
                font-size: 12px; 
                line-height: 1.7;
                color: #374151;
            }
            .tracking-box {
                background: #fef9c3;
                border: 2px solid #f59e0b;
                padding: 16px;
                margin: 20px 0;
                text-align: center;
            }
            .tracking-box .label {
                font-size: 11px;
                text-transform: uppercase;
                font-weight: 600;
                color: #92400e;
                margin-bottom: 5px;
            }
            .tracking-box .number {
                font-size: 18px;
                font-weight: 700;
                color: #000;
                font-family: monospace;
                letter-spacing: 1px;
            }
            .details-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            .details-table td {
                padding: 10px;
                border: 1px solid #d1d5db;
                font-size: 12px;
            }
            .details-table .label {
                font-weight: 600;
                background: #f9fafb;
                width: 40%;
                color: #374151;
            }
            .note-box { 
                margin-top: 30px; 
                padding: 15px;
                background: #f9fafb;
                font-size: 11px; 
                color: #4b5563;
                border-left: 3px solid #9ca3af;
            }
            .footer {
                margin-top: 40px;
                text-align: center;
                font-size: 10px;
                color: #9ca3af;
                border-top: 1px solid #e5e7eb;
                padding-top: 15px;
            }
        </style>'
      . '</head><body>'
      . '<div class="header">'
      .   '<h1>Shipment Label</h1>'
      .   '<div class="header-subtitle">' . $shipmentNo . '</div>'
      .   '<div class="header-info">'
      .     '<div class="header-left">'
      .       '<div class="meta"><span class="meta-label">Order:</span> ' . $orderNo . '</div>'
      .       '<div class="meta"><span class="meta-label">Ship Date:</span> ' . $shipDate . '</div>'
      .       ($expectedDelivery !== '' ? '<div class="meta"><span class="meta-label">Expected Delivery:</span> ' . $expectedDelivery . '</div>' : '')
      .     '</div>'
      .     '<div class="header-right">'
      .       '<div class="meta"><span class="meta-label">Status:</span> <span class="status-badge">' . strtoupper($status) . '</span></div>'
      .       '<div class="meta"><span class="meta-label">Service:</span> ' . ucwords(str_replace(['_', '-'], ' ', $serviceType)) . '</div>'
      .     '</div>'
      .   '</div>'
      . '</div>'
      
      . '<div class="info-box">'
      .   '<div class="info-box-title">Ship To</div>'
      .   '<div class="info-box-content">'
      .     '<strong>' . $customerName . '</strong><br>'
      .     nl2br($address)
      .   '</div>'
      . '</div>'
      
      . '<div class="tracking-box">'
      .   '<div class="label">Tracking Number</div>'
      .   '<div class="number">' . $tracking . '</div>'
      . '</div>'
      
      . '<table class="details-table">'
      .   '<tr>'
      .     '<td class="label">Carrier</td>'
      .     '<td>' . $carrier . '</td>'
      .   '</tr>'
      .   '<tr>'
      .     '<td class="label">Service Type</td>'
      .     '<td>' . ucwords(str_replace(['_', '-'], ' ', $serviceType)) . '</td>'
      .   '</tr>'
      .   ($weight > 0 ? '<tr><td class="label">Weight</td><td>' . number_format($weight, 2) . ' lbs</td></tr>' : '')
      . '</table>';

    if ($notes !== '') {
        $html .= '<div class="note-box">'
              .  '<strong>Notes:</strong><br>' . nl2br($notes)
              .  '</div>';
    }

    $html .= '<div class="footer">'
          .  'Generated on ' . date('F j, Y g:i A') . ' by ' . $company
          .  '</div>'
          . '</body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('shipment_' . $shipmentNo . '.pdf', ['Attachment' => 0]);

} catch (\Exception $e) {
    error_log('Shipment PDF error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error generating PDF: ' . htmlspecialchars($e->getMessage());
}
