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
}
