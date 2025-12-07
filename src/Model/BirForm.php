<?php

namespace App\Model;

use App\Service\DatabaseService;
use MongoDB\Collection;
use MongoDB\BSON\ObjectId;

class BirForm
{
    private Collection $collection;

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        $collectionName = $db->getCollectionName('bir_forms');
        $this->collection = $db->getCollection($collectionName);
    }

    /**
     * Create a new BIR form
     * @param array $formData
     * @return string|null Returns the ID of the created form, or null on failure
     */
    public function create(array $formData): ?string
    {
        try {
            // Add created timestamp if not present
            if (!isset($formData['created_at'])) {
                $formData['created_at'] = new \MongoDB\BSON\UTCDateTime();
            }
            
            $result = $this->collection->insertOne($formData);
            return $result->getInsertedId()->__toString();
        } catch (\Exception $e) {
            error_log('Error creating BIR form: ' . $e->getMessage());
            return null;
        }
    }

    public function getRecentForms(int $limit = 50): array
    {
        $cursor = $this->collection->find([], [
            'sort' => ['date' => -1],
            'limit' => $limit
        ]);
        $out = [];
        foreach ($cursor as $doc) { $out[] = (array)$doc; }
        return $out;
    }
}
