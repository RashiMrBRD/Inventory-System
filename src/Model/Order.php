<?php

namespace App\Model;

use App\Service\DatabaseService;
use App\Model\Invoice;
use App\Model\Inventory;
use MongoDB\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

/**
 * Order Model - Sales and Purchase Orders
 */
class Order
{
    private Collection $collection;

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        $collectionName = $db->getCollectionName('orders');
        $this->collection = $db->getCollection($collectionName);
    }

    public function getAll(array $filter = [], array $options = []): array
    {
        $options = array_merge([
            'sort' => ['date' => -1],
            'limit' => 500
        ], $options);
        $cursor = $this->collection->find($filter, $options);
        $out = [];
        foreach ($cursor as $doc) {
            $doc = (array)$doc;
            // Convert MongoDB UTCDateTime objects to ISO string format
            if (isset($doc['date']) && $doc['date'] instanceof UTCDateTime) {
                $doc['date'] = $doc['date']->toDateTime()->format('Y-m-d');
            }
            if (isset($doc['created_at']) && $doc['created_at'] instanceof UTCDateTime) {
                $doc['created_at'] = $doc['created_at']->toDateTime()->format('Y-m-d H:i:s');
            }
            if (isset($doc['updated_at']) && $doc['updated_at'] instanceof UTCDateTime) {
                $doc['updated_at'] = $doc['updated_at']->toDateTime()->format('Y-m-d H:i:s');
            }
            $out[] = $doc;
        }
        return $out;
    }

    public function findById(string $id): ?array
    {
        try {
            $doc = $this->collection->findOne(['_id' => new ObjectId($id)]);
            if (!$doc) return null;
            
            $doc = (array)$doc;
            // Convert MongoDB UTCDateTime objects to ISO string format
            if (isset($doc['date']) && $doc['date'] instanceof UTCDateTime) {
                $doc['date'] = $doc['date']->toDateTime()->format('Y-m-d');
            }
            if (isset($doc['created_at']) && $doc['created_at'] instanceof UTCDateTime) {
                $doc['created_at'] = $doc['created_at']->toDateTime()->format('Y-m-d H:i:s');
            }
            if (isset($doc['updated_at']) && $doc['updated_at'] instanceof UTCDateTime) {
                $doc['updated_at'] = $doc['updated_at']->toDateTime()->format('Y-m-d H:i:s');
            }
            return $doc;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if an order is 100% complete (fully paid)
     */
    public function isFullyPaid(array $order): bool
    {
        try {
            // For sales orders, check if paid amount equals total
            if (($order['type'] ?? '') === 'Sales' || ($order['type'] ?? '') === 'sales') {
                $total = (float)($order['total'] ?? 0);
                $paid = (float)($order['paid'] ?? 0);
                
                error_log("DEBUG: Checking if order is fully paid - Total: $total, Paid: $paid");
                
                return $paid >= $total && $total > 0;
            }
            
            // For purchase orders, check if fully received/paid
            if (($order['type'] ?? '') === 'Purchase' || ($order['type'] ?? '') === 'purchase') {
                // Purchase orders are considered complete if status is 'received' or 'delivered'
                $status = $order['status'] ?? '';
                return in_array($status, ['received', 'delivered', 'completed']);
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("ERROR: Failed to check if order is fully paid: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get active orders (excluding 100% completed ones)
     */
    public function getActiveOrders(): array
    {
        try {
            $allOrders = $this->getAll();
            $activeOrders = [];
            
            foreach ($allOrders as $order) {
                if (!$this->isFullyPaid($order)) {
                    $activeOrders[] = $order;
                } else {
                    error_log("DEBUG: Excluding fully paid order from active list: " . (string)$order['_id']);
                }
            }
            
            error_log("DEBUG: Active orders count: " . count($activeOrders) . " (from " . count($allOrders) . " total)");
            
            return $activeOrders;
        } catch (\Exception $e) {
            error_log("ERROR: Failed to get active orders: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update order payment amount and check if fully paid
     */
    public function updatePayment(string $orderId, float $paymentAmount): bool
    {
        try {
            $order = $this->findById($orderId);
            if (!$order) {
                error_log("DEBUG: Order not found for payment update: $orderId");
                return false;
            }
            
            $currentPaid = (float)($order['paid'] ?? 0);
            $newPaid = $currentPaid + $paymentAmount;
            $total = (float)($order['total'] ?? 0);
            
            error_log("DEBUG: Updating payment for order $orderId - Current: $currentPaid, Adding: $paymentAmount, New: $newPaid, Total: $total");
            
            // Update the paid amount
            $success = $this->update($orderId, ['paid' => $newPaid]);
            
            if ($success) {
                // Check if order is now fully paid
                if ($newPaid >= $total && $total > 0) {
                    error_log("DEBUG: Order $orderId is now fully paid!");
                    
                    // Update status to completed
                    $this->updateStatus($orderId, 'completed');
                    error_log("DEBUG: Order $orderId status updated to 'completed'");
                }
            }
            
            return $success;
        } catch (\Exception $e) {
            error_log("ERROR: Failed to update payment for order $orderId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reduce inventory quantities based on order items
     */
    public function reduceInventoryFromOrder(array $order): void
    {
        try {
            $inventoryModel = new Inventory();
            
            if (!isset($order['items']) || empty($order['items'])) {
                error_log("DEBUG: No items in order to reduce inventory");
                return;
            }
            
            error_log("DEBUG: Reducing inventory for " . count($order['items']) . " items");
            
            foreach ($order['items'] as $item) {
                $quantity = (float)($item['quantity'] ?? 0);
                if ($quantity <= 0) {
                    error_log("DEBUG: Skipping item with zero or negative quantity: " . json_encode($item));
                    continue;
                }
                
                $inventoryItem = null;
                
                // Try to find inventory item by various identifiers
                if (!empty($item['product_id'])) {
                    error_log("DEBUG: Trying to find inventory by product_id: " . $item['product_id']);
                    $inventoryItem = $inventoryModel->findById($item['product_id']);
                }
                
                if (!$inventoryItem && !empty($item['barcode'])) {
                    error_log("DEBUG: Trying to find inventory by barcode: " . $item['barcode']);
                    $inventoryItem = $inventoryModel->findByBarcode($item['barcode']);
                }
                
                if (!$inventoryItem && !empty($item['description'])) {
                    error_log("DEBUG: Trying to find inventory by description: " . $item['description']);
                    $allItems = $inventoryModel->getAll();
                    foreach ($allItems as $invItem) {
                        if (($invItem['name'] === $item['description'] || $invItem['barcode'] === $item['description']) && 
                            isset($invItem['_id'])) {
                            $inventoryItem = $invItem;
                            break;
                        }
                    }
                }
                
                if ($inventoryItem && isset($inventoryItem['_id'])) {
                    $inventoryId = (string)$inventoryItem['_id'];
                    $currentQuantity = (float)($inventoryItem['quantity'] ?? 0);
                    $newQuantity = $currentQuantity - $quantity;
                    
                    error_log("DEBUG: Reducing inventory $inventoryId from $currentQuantity to $newQuantity (ordered: $quantity)");
                    
                    if ($newQuantity <= 0) {
                        // Remove inventory item if quantity becomes zero or negative
                        $inventoryModel->delete($inventoryId);
                        error_log("DEBUG: Inventory item $inventoryId removed (quantity reached zero)");
                    } else {
                        // Update inventory quantity
                        $inventoryModel->update($inventoryId, ['quantity' => $newQuantity]);
                        error_log("DEBUG: Inventory item $inventoryId updated to quantity: $newQuantity");
                    }
                } else {
                    error_log("DEBUG: No matching inventory item found for order item: " . json_encode($item));
                }
            }
        } catch (\Exception $e) {
            error_log("ERROR: Failed to reduce inventory from order: " . $e->getMessage());
        }
    }

    public function create(array $data): ?string
    {
        try {
            $data['created_at'] = new UTCDateTime();
            $data['updated_at'] = new UTCDateTime();
            if (!isset($data['date'])) {
                $data['date'] = new UTCDateTime();
            } else if (is_string($data['date'])) {
                $data['date'] = new UTCDateTime(strtotime($data['date']) * 1000);
            }
            
            // Insert the order first
            $result = $this->collection->insertOne($data);
            $orderId = $result->getInsertedId()->__toString();
            
            // Reduce inventory quantities for sales orders
            if (($data['type'] ?? '') === 'Sales' || ($data['type'] ?? '') === 'sales') {
                error_log("DEBUG: Sales order created, reducing inventory quantities");
                $data['_id'] = $orderId; // Add ID for inventory reduction
                $this->reduceInventoryFromOrder($data);
            }
            
            return $orderId;
        } catch (\Exception $e) {
            error_log("Order create error: " . $e->getMessage());
            return null;
        }
    }

    public function update(string $id, array $data): bool
    {
        try {
            $data['updated_at'] = new UTCDateTime();
            $result = $this->collection->updateOne(
                ['_id' => new ObjectId($id)],
                ['$set' => $data]
            );
            return $result->getModifiedCount() > 0 || $result->getMatchedCount() > 0;
        } catch (\Exception $e) {
            error_log("Order update error: " . $e->getMessage());
            return false;
        }
    }

    public function delete(string $id): bool
    {
        try {
            $result = $this->collection->deleteOne(['_id' => new ObjectId($id)]);
            return $result->getDeletedCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updateStatus(string $id, string $status): bool
    {
        return $this->update($id, ['status' => $status]);
    }

    public function getStats(): array
    {
        try {
            $all = $this->getAll();
            $total = count($all);
            $salesOrders = array_filter($all, fn($o) => ($o['type'] ?? '') === 'Sales');
            $purchaseOrders = array_filter($all, fn($o) => ($o['type'] ?? '') === 'Purchase');
            
            return [
                'total' => $total,
                'sales_count' => count($salesOrders),
                'purchase_count' => count($purchaseOrders),
                'sales_value' => array_sum(array_map(fn($o) => (float)($o['total'] ?? 0), $salesOrders)),
                'purchase_value' => array_sum(array_map(fn($o) => (float)($o['total'] ?? 0), $purchaseOrders)),
                'total_value' => array_sum(array_map(fn($o) => (float)($o['total'] ?? 0), $all)),
                'pending' => count(array_filter($all, fn($o) => ($o['status'] ?? '') === 'pending')),
                'processing' => count(array_filter($all, fn($o) => ($o['status'] ?? '') === 'processing')),
                'shipped' => count(array_filter($all, fn($o) => in_array(($o['status'] ?? ''), ['shipped', 'delivered', 'received']))),
                'cancelled' => count(array_filter($all, fn($o) => ($o['status'] ?? '') === 'cancelled'))
            ];
        } catch (\Exception $e) {
            return [
                'total' => 0,
                'sales_count' => 0,
                'purchase_count' => 0,
                'sales_value' => 0,
                'purchase_value' => 0,
                'total_value' => 0,
                'pending' => 0,
                'processing' => 0,
                'shipped' => 0,
                'cancelled' => 0
            ];
        }
    }

    /**
     * Convert an order to an invoice
     * Returns the new invoice ID on success or null on failure
     */
    public function convertToInvoice(string $orderId): ?string
    {
        try {
            error_log("DEBUG: convertToInvoice called with orderId: $orderId");
            
            $order = $this->findById($orderId);
            if (!$order) {
                error_log("DEBUG: Order not found for conversion: $orderId");
                return null;
            }
            
            error_log("DEBUG: Found order: " . json_encode($order));

            // Prepare invoice data from order
            $invoiceModel = new Invoice();
            $invoiceNumber = $invoiceModel->generateInvoiceNumber();
            error_log("DEBUG: Generated invoice number: $invoiceNumber for order: $orderId");
            
            // Check if order is already paid
            $orderTotal = (float)($order['total'] ?? 0);
            $orderPaid = (float)($order['paid'] ?? 0);
            $isOrderPaid = $orderPaid >= $orderTotal && $orderTotal > 0;
            
            error_log("DEBUG: Order payment status - Total: $orderTotal, Paid: $orderPaid, Is Paid: $isOrderPaid");
            
            // Set invoice status based on order payment
            $invoiceStatus = 'draft';
            $invoicePaid = 0;
            $outstanding = $orderTotal;
            $paymentHistory = [];
            
            if ($isOrderPaid) {
                $invoiceStatus = 'paid';
                $invoicePaid = $orderPaid;
                $outstanding = 0;
                error_log("DEBUG: Order is already paid, setting invoice status to 'paid'");
                
                // Add payment history record for the paid amount
                $paymentHistory[] = [
                    'amount' => $orderPaid,
                    'payment_date' => $order['updated_at'] ?? date('Y-m-d H:i:s'),
                    'payment_method' => 'converted_from_order',
                    'reference' => 'Order conversion',
                    'notes' => "Payment transferred from order $orderId",
                    'recorded_by' => 'system',
                    'recorded_at' => date('Y-m-d H:i:s')
                ];
            } elseif ($orderPaid > 0) {
                $invoiceStatus = 'partial';
                $invoicePaid = $orderPaid;
                $outstanding = $orderTotal - $orderPaid;
                error_log("DEBUG: Order has partial payments, setting invoice status to 'partial'");
                
                // Add payment history record for partial payments
                $paymentHistory[] = [
                    'amount' => $orderPaid,
                    'payment_date' => $order['updated_at'] ?? date('Y-m-d H:i:s'),
                    'payment_method' => 'converted_from_order',
                    'reference' => 'Order conversion',
                    'notes' => "Partial payment transferred from order $orderId",
                    'recorded_by' => 'system',
                    'recorded_at' => date('Y-m-d H:i:s')
                ];
            }
            
            $invoiceData = [
                'invoice_number' => $invoiceNumber,
                'order_id' => $orderId,
                'customer' => $order['customer'] ?? '',
                'customer_email' => $order['customer_email'] ?? '',
                'customer_phone' => $order['customer_phone'] ?? '',
                'billing_address' => $order['billing_address'] ?? '',
                'shipping_address' => $order['shipping_address'] ?? '',
                'date' => date('Y-m-d'),
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'items' => $order['items'] ?? [],
                'subtotal' => $order['subtotal'] ?? 0,
                'tax_rate' => $order['tax_rate'] ?? 0,
                'tax_amount' => $order['tax_amount'] ?? 0,
                'total' => $orderTotal,
                'notes' => $order['notes'] ?? '',
                'status' => $invoiceStatus,
                'paid' => $invoicePaid,
                'outstanding' => $outstanding,
                'payment_history' => $paymentHistory
            ];
            
            error_log("DEBUG: Invoice data being created: " . json_encode($invoiceData));

            // Create invoice using Invoice model
            $invoiceId = $invoiceModel->create($invoiceData);
            error_log("DEBUG: Invoice created with ID: $invoiceId");

            if ($invoiceId) {
                // Reduce inventory quantities for converted orders/quotations
                if (($order['type'] ?? '') === 'Quotation' || ($order['type'] ?? '') === 'quotation') {
                    error_log("DEBUG: Quotation converted to invoice, reducing inventory quantities");
                    $this->reduceInventoryFromOrder($order);
                }
                
                // Optionally update order status to indicate it's been invoiced
                $this->updateStatus($orderId, 'invoiced');
                error_log("DEBUG: Order status updated to 'invoiced'");
            }

            return $invoiceId;
        } catch (\Exception $e) {
            error_log("ERROR: Convert order to invoice exception: " . $e->getMessage());
            error_log("ERROR: Stack trace: " . $e->getTraceAsString());
            return null;
        } catch (\Error $e) {
            error_log("ERROR: Convert order to invoice fatal error: " . $e->getMessage());
            error_log("ERROR: Stack trace: " . $e->getTraceAsString());
            return null;
        }
    }
}
