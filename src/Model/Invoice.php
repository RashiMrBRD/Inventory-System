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
     * Create a new invoice
     */
    public function create(array $data): string
    {
        // Add timestamps and defaults
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['status'] = $data['status'] ?? 'draft';
        $data['paid'] = 0; // Default no payment made yet
        $data['outstanding'] = (float)($data['total'] ?? 0);
        
        // Insert into database
        $result = $this->collection->insertOne($data);
        
        // Return the inserted ID as string
        return (string)$result->getInsertedId();
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
