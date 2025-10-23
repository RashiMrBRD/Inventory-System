<?php

namespace App\Model;

use App\Service\DatabaseService;
use MongoDB\Collection;

class BirForm
{
    private Collection $collection;

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        $collectionName = $db->getCollectionName('bir_forms');
        $this->collection = $db->getCollection($collectionName);
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
