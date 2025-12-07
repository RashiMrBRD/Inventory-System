<?php

namespace App\Model;

use App\Service\DatabaseService;
use MongoDB\Collection;

/**
 * Invoice Model
 * Simple CRUD/read methods for invoices collection
 */
class Invoice
{
    private Collection $collection;

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        $collectionName = $db->getCollectionName('invoices');
        $this->collection = $db->getCollection($collectionName);
    }

    /**
     * Get all invoices with optional filter and options
     */
    public function getAll(array $filter = [], array $options = []): array
    {
        $options = array_merge([
            'sort' => ['date' => -1],
            'limit' => 200
        ], $options);

        $cursor = $this->collection->find($filter, $options);
        $items = [];
        foreach ($cursor as $doc) {
            $items[] = (array)$doc;
        }
        return $items;
    }

    /**
     * Search invoices by invoice number, customer name, or status
     * This method searches invoices by invoice number, customer name, or status
     * 
     * @param string $query
     * @return array
     */
    public function search(string $query): array
    {
        $regex = new \MongoDB\BSON\Regex($query, 'i');
        
        $items = $this->collection->find([
            '$or' => [
                ['invoice_number' => $regex],
                ['customer_name' => $regex],
                ['customer_email' => $regex],
                ['status' => $regex]
            ]
        ])->toArray();
        
        return array_map(function($item) {
            return (array)$item;
        }, $items);
    }

    /**
     * Generate a unique invoice number
     */
    public function generateInvoiceNumber(): string
    {
        try {
            $year = date('Y');
            $month = date('m');
            
            error_log("DEBUG: Generating invoice number for year: $year, month: $month");
            
            // Get the count of invoices for this month
            $monthlyCount = $this->collection->countDocuments([
                'invoice_number' => ['$regex' => "^INV-{$year}{$month}"]
            ]);
            
            error_log("DEBUG: Found $monthlyCount existing invoices for this month");
            
            // Generate sequential number (starting from 1)
            $sequence = $monthlyCount + 1;
            $sequencePadded = str_pad($sequence, 3, '0', STR_PAD_LEFT);
            
            $invoiceNumber = "INV-{$year}{$month}-{$sequencePadded}";
            error_log("DEBUG: Generated invoice number: $invoiceNumber");
            
            return $invoiceNumber;
        } catch (\Exception $e) {
            error_log("ERROR: Failed to generate invoice number: " . $e->getMessage());
            // Fallback to timestamp-based number if there's an error
            return "INV-" . date('Ym') . "-" . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Create a new invoice
     */
    public function create(array $data): string
    {
        try {
            error_log("DEBUG: Invoice create called with data: " . json_encode($data));
            
            // Add timestamps and defaults
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            $data['status'] = $data['status'] ?? 'draft';
            $data['paid'] = 0; // Default no payment made yet
            $data['outstanding'] = (float)($data['total'] ?? 0);
            
            error_log("DEBUG: Final invoice data before insert: " . json_encode($data));
            
            // Insert into database
            $result = $this->collection->insertOne($data);
            
            $invoiceId = (string)$result->getInsertedId();
            error_log("DEBUG: Invoice inserted with ID: $invoiceId");
            
            // Return the inserted ID as string
            return $invoiceId;
        } catch (\Exception $e) {
            error_log("ERROR: Failed to create invoice: " . $e->getMessage());
            error_log("ERROR: Stack trace: " . $e->getTraceAsString());
            throw $e; // Re-throw to let caller handle it
        }
    }

    /**
     * Update an existing invoice
     */
    public function update(string $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $result = $this->collection->updateOne(
            ['_id' => new \MongoDB\BSON\ObjectId($id)],
            ['$set' => $data]
        );
        
        return $result->getModifiedCount() > 0;
    }

    /**
     * Delete an invoice
     */
    public function delete(string $id): bool
    {
        $result = $this->collection->deleteOne(
            ['_id' => new \MongoDB\BSON\ObjectId($id)]
        );
        
        return $result->getDeletedCount() > 0;
    }

    /**
     * Get invoice by ID
     */
    public function getById(string $id): ?array
    {
        $invoice = $this->collection->findOne(
            ['_id' => new \MongoDB\BSON\ObjectId($id)]
        );
        
        return $invoice ? (array)$invoice : null;
    }

    /**
     * Get invoice by ID (alias for getById)
     */
    public function findById(string $id): ?array
    {
        return $this->getById($id);
    }

    /**
     * Calculate totals (total, paid, outstanding)
     */
    public function totals(array $filter = []): array
    {
        try {
            $pipeline = [
                ['$match' => $filter],
                ['$group' => [
                    '_id' => null,
                    'total' => ['$sum' => ['$toDouble' => '$total']],
                    'paid' => ['$sum' => ['$toDouble' => '$paid']]
                ]]
            ];
            $res = $this->collection->aggregate($pipeline)->toArray();
            $row = $res[0] ?? [];
            $total = (float)($row['total'] ?? 0);
            $paid = (float)($row['paid'] ?? 0);
            return [
                'total' => $total,
                'paid' => $paid,
                'outstanding' => max(0, $total - $paid)
            ];
        } catch (\Throwable $e) {
            $all = $this->getAll($filter, ['limit' => 10000]);
            $total = 0; $paid = 0;
            foreach ($all as $inv) {
                $total += (float)($inv['total'] ?? 0);
                $paid += (float)($inv['paid'] ?? 0);
            }
            return [
                'total' => $total,
                'paid' => $paid,
                'outstanding' => max(0, $total - $paid)
            ];
        }
    }

    /**
     * Get paginated invoices with total count
     */
    public function getPaginated(int $page = 1, int $perPage = 6, array $filter = [], array $sort = ['date' => -1]): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $skip = ($page - 1) * $perPage;

        $totalCount = $this->collection->countDocuments($filter);
        
        $options = [
            'sort' => $sort,
            'skip' => $skip,
            'limit' => $perPage
        ];

        $cursor = $this->collection->find($filter, $options);
        $items = [];
        foreach ($cursor as $doc) {
            $items[] = (array)$doc;
        }

        return [
            'items' => $items,
            'total' => $totalCount,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int)ceil($totalCount / $perPage)
        ];
    }
}
