<?php

namespace App\Model;

use App\Service\DatabaseService;
use MongoDB\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

/**
 * Quotation Model - Enterprise Features
 * Xero + QuickBooks + LedgerSMB parity
 */
class Quotation
{
    private Collection $collection;

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        $collectionName = $db->getCollectionName('quotations');
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
            if (isset($doc['valid_until']) && $doc['valid_until'] instanceof UTCDateTime) {
                $doc['valid_until'] = $doc['valid_until']->toDateTime()->format('Y-m-d');
            }
            $out[] = $doc;
        }
        return $out;
    }

    /**
     * Search quotations by quote number, customer, or status
     * This method searches quotations by quote number, customer name, or status
     * 
     * @param string $query
     * @return array
     */
    public function search(string $query): array
    {
        $regex = new \MongoDB\BSON\Regex($query, 'i');
        
        $items = $this->collection->find([
            '$or' => [
                ['quote_number' => $regex],
                ['customer_name' => $regex],
                ['customer_email' => $regex],
                ['status' => $regex]
            ]
        ])->toArray();
        
        $out = [];
        foreach ($items as $doc) {
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
            if (isset($doc['valid_until']) && $doc['valid_until'] instanceof UTCDateTime) {
                $doc['valid_until'] = $doc['valid_until']->toDateTime()->format('Y-m-d');
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
            if (isset($doc['valid_until']) && $doc['valid_until'] instanceof UTCDateTime) {
                $doc['valid_until'] = $doc['valid_until']->toDateTime()->format('Y-m-d');
            }
            return $doc;
        } catch (\Exception $e) {
            return null;
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
            if (!isset($data['valid_until']) && isset($data['validity_days'])) {
                $validityDays = (int)$data['validity_days'];
                $data['valid_until'] = new UTCDateTime((time() + $validityDays * 86400) * 1000);
            }
            $result = $this->collection->insertOne($data);
            return $result->getInsertedId()->__toString();
        } catch (\Exception $e) {
            error_log("Quotation create error: " . $e->getMessage());
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
            error_log("Quotation update error: " . $e->getMessage());
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

    public function getStats(): array
    {
        try {
            $all = $this->getAll();
            $total = count($all);
            $totalValue = array_sum(array_column($all, 'total'));
            $pending = count(array_filter($all, fn($q) => ($q['status'] ?? '') === 'pending'));
            $approved = count(array_filter($all, fn($q) => ($q['status'] ?? '') === 'approved'));
            $rejected = count(array_filter($all, fn($q) => ($q['status'] ?? '') === 'rejected'));
            $converted = count(array_filter($all, fn($q) => ($q['status'] ?? '') === 'converted'));
            $expired = count(array_filter($all, function($q) {
                if (!isset($q['valid_until'])) return false;
                $validUntil = is_object($q['valid_until']) ? $q['valid_until']->toDateTime()->getTimestamp() : strtotime($q['valid_until']);
                return $validUntil < time();
            }));

            return [
                'total' => $total,
                'total_value' => $totalValue,
                'pending' => $pending,
                'approved' => $approved,
                'rejected' => $rejected,
                'converted' => $converted,
                'expired' => $expired
            ];
        } catch (\Exception $e) {
            return [
                'total' => 0,
                'total_value' => 0,
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0,
                'converted' => 0,
                'expired' => 0
            ];
        }
    }

    public function updateStatus(string $id, string $status): bool
    {
        return $this->update($id, ['status' => $status]);
    }

    public function convertToOrder(string $id): ?array
    {
        try {
            $quote = $this->findById($id);
            if (!$quote) return null;

            // Create a Sales order based on the quotation so it appears in orders.php
            $orderModel = new Order();

            $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad((string)rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $items = $quote['items'] ?? [];

            // Use quotation financials when available
            $subtotal = isset($quote['subtotal']) ? (float)$quote['subtotal'] : 0;
            $tax = isset($quote['tax']) ? (float)$quote['tax'] : 0;
            $total = isset($quote['total']) ? (float)$quote['total'] : ($subtotal + $tax);

            $discountPercent = isset($quote['discount_percent']) ? (float)$quote['discount_percent'] : 0;
            $discountAmount = isset($quote['discount_amount']) ? (float)$quote['discount_amount'] : 0;
            $taxRate = isset($quote['tax_rate']) ? (float)$quote['tax_rate'] : 0;

            $orderData = [
                'order_number' => $orderNumber,
                'type' => 'Sales',
                'customer' => $quote['customer'] ?? '',
                'date' => $quote['date'] ?? date('Y-m-d'),
                'status' => 'pending',
                'items' => $items,
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax' => $tax,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'total' => $total,
                'payment_terms' => $quote['payment_terms'] ?? 'Net 30',
                'reference' => $quote['quote_number'] ?? '',
                'notes' => $quote['notes'] ?? '',
                'email' => $quote['customer_email'] ?? '',
                'phone' => $quote['customer_phone'] ?? '',
                'company' => $quote['customer_company'] ?? '',
                'currency' => $quote['currency'] ?? null,
                'quote_id' => $id,
            ];

            $orderId = $orderModel->create($orderData);
            if (!$orderId) {
                return null;
            }

            // Best-effort: mark quotation as converted after order is created
            $this->updateStatus($id, 'converted');

            return [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'type' => 'Sales',
                'customer' => $orderData['customer'],
                'total' => $total,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
