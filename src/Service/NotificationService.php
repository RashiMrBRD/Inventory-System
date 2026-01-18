<?php

namespace App\Service;

use App\Database\NotificationRepository;
use App\Model\Inventory;
use App\Model\Invoice;
use App\Model\BirForm;
use App\Model\FdaProduct;

/**
 * Notification Service
 * Generates and sends contextual notifications based on business events
 */
class NotificationService
{
    private NotificationRepository $repo;
    private string $userId;

    public function __construct(string $userId)
    {
        $this->userId = $userId;
        $this->repo = new NotificationRepository($userId);
    }

    /**
     * Create notification
     */
    public function create(string $type, string $title, string $message, string $priority = 'normal', ?string $userId = null): string
    {
        try {
            $data = [
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'priority' => $priority
            ];

            // If a specific userId is provided, create notification for that user
            if ($userId) {
                $repo = new NotificationRepository($userId);
                return $repo->create($data);
            }

            // Otherwise, create notification for current user
            return $this->repo->create($data);
        } catch (\Exception $e) {
            error_log("Failed to create notification: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Check and generate low stock alerts
     */
    public function generateLowStockAlerts(): int
    {
        try {
            $inventory = new Inventory();
            $lowStockItems = $inventory->getLowStock(5); // Get items with quantity <= 5
            $count = 0;

            foreach ($lowStockItems as $item) {
                $quantity = $item['quantity'] ?? 0;
                $name = $item['name'] ?? 'Unknown Item';
                $itemUserId = $item['user_id'] ?? null;

                // Create notification for the user who owns this item
                $this->create(
                    'inventory',
                    'Low Stock Alert',
                    "Item '{$name}' is running low (only {$quantity} left). Please restock soon.",
                    $quantity === 0 ? 'high' : 'medium',
                    $itemUserId
                );
                $count++;
            }

            return $count;
        } catch (\Exception $e) {
            error_log("Low stock alert generation failed: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check and generate expiry alerts for FDA products
     */
    public function generateExpiryAlerts(int $daysThreshold = 30): int
    {
        try {
            $fdaModel = new FdaProduct();
            $expiringProducts = $fdaModel->getExpiringProducts($daysThreshold);
            $count = 0;

            foreach ($expiringProducts as $product) {
                $daysLeft = $product['days_left'] ?? 0;
                $name = $product['name'] ?? 'Unknown Product';
                $batch = $product['batch'] ?? 'Unknown Batch';
                $productUserId = $product['user_id'] ?? null;

                $priority = $daysLeft <= 10 ? 'high' : ($daysLeft <= 20 ? 'medium' : 'normal');

                // Create notification for the user who owns this product
                $this->create(
                    'expiry',
                    'Product Expiring Soon',
                    "{$name} (Batch: {$batch}) expires in {$daysLeft} days. FEFO priority required.",
                    $priority,
                    $productUserId
                );
                $count++;
            }

            return $count;
        } catch (\Exception $e) {
            error_log("Expiry alert generation failed: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check and generate overdue invoice alerts
     */
    public function generateOverdueInvoiceAlerts(): int
    {
        try {
            $invoiceModel = new Invoice();
            $invoices = $invoiceModel->getAll();
            $today = strtotime('today');
            $count = 0;

            foreach ($invoices as $invoice) {
                $status = strtolower((string)($invoice['status'] ?? ''));
                $dueTs = isset($invoice['due']) ? strtotime((string)$invoice['due']) : null;
                $balance = (float)($invoice['total'] ?? 0) - (float)($invoice['paid'] ?? 0);
                $invId = $invoice['id'] ?? ((isset($invoice['_id']) && is_object($invoice['_id'])) ? (string)$invoice['_id'] : '');
                $customer = $invoice['customer'] ?? 'Unknown';
                $invoiceUserId = $invoice['user_id'] ?? null;

                if ($balance > 0 && $dueTs && $dueTs < $today) {
                    $daysOverdue = floor((time() - $dueTs) / 86400);
                    // Create notification for the user who owns this invoice
                    $this->create(
                        'financial',
                        'Overdue Invoice',
                        "Invoice {$invId} from {$customer} is {$daysOverdue} days overdue. Outstanding: " . number_format($balance, 2),
                        $daysOverdue > 30 ? 'high' : 'medium',
                        $invoiceUserId
                    );
                    $count++;
                }
            }

            return $count;
        } catch (\Exception $e) {
            error_log("Overdue invoice alert generation failed: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check and generate BIR filing deadline alerts
     */
    public function generateBIRDeadlineAlerts(): int
    {
        try {
            $birModel = new BirForm();
            $forms = $birModel->getRecentForms(100);
            $count = 0;
            $today = strtotime('today');

            foreach ($forms as $form) {
                if (empty($form['due_date']) || ($form['status'] ?? '') === 'filed') {
                    continue;
                }

                $dueTs = is_string($form['due_date']) ? strtotime($form['due_date']) :
                         (is_object($form['due_date']) && method_exists($form['due_date'], 'toDateTime') ?
                          $form['due_date']->toDateTime()->getTimestamp() : null);

                if ($dueTs && $dueTs >= $today) {
                    $daysLeft = floor(($dueTs - time()) / 86400);
                    $formType = $form['form_type'] ?? 'BIR Form';
                    $period = $form['period'] ?? '';
                    $formUserId = $form['user_id'] ?? null;

                    $priority = $daysLeft <= 3 ? 'high' : ($daysLeft <= 7 ? 'medium' : 'normal');

                    // Create notification for the user who owns this form
                    $this->create(
                        'bir',
                        'BIR Filing Deadline',
                        "{$formType} ({$period}) due in {$daysLeft} days. Please file before " . date('M d, Y', $dueTs),
                        $priority,
                        $formUserId
                    );
                    $count++;
                }
            }

            return $count;
        } catch (\Exception $e) {
            error_log("BIR deadline alert generation failed: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Generate all alerts in one call
     */
    public function generateAllAlerts(): array
    {
        return [
            'low_stock' => $this->generateLowStockAlerts(),
            'expiry' => $this->generateExpiryAlerts(),
            'overdue_invoices' => $this->generateOverdueInvoiceAlerts(),
            'bir_deadlines' => $this->generateBIRDeadlineAlerts()
        ];
    }

    /**
     * Create notification for successful action
     */
    public function notifySuccess(string $title, string $message): string
    {
        return $this->create('success', $title, $message, 'normal');
    }

    /**
     * Create notification for system event
     */
    public function notifySystem(string $title, string $message, string $priority = 'normal'): string
    {
        return $this->create('system', $title, $message, $priority);
    }
}
