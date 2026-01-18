<?php

namespace App\Model;

use App\Service\DatabaseService;
use MongoDB\Collection;

class FdaProduct
{
    private Collection $collection;

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        $collectionName = $db->getCollectionName('fda_products');
        $this->collection = $db->getCollection($collectionName);
    }

    /**
     * Get products with expiry within N days from today
     */
    public function getExpiringProducts(int $withinDays = 60): array
    {
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return [];
        }

        // Check if user is admin
        $user = new User();
        $currentUser = $user->findById($userId);
        $isAdmin = ($currentUser['access_level'] ?? 'user') === 'admin';

        $todayStart = strtotime('today');
        $upper = strtotime("+$withinDays days 23:59:59");
        $match = [
            'expiry' => [
                '$gte' => new \MongoDB\BSON\UTCDateTime($todayStart * 1000),
                '$lte' => new \MongoDB\BSON\UTCDateTime($upper * 1000)
            ]
        ];

        // Add user_id filter for non-admin users
        if (!$isAdmin) {
            $match['user_id'] = $userId;
        }

        $cursor = $this->collection->find($match, ['sort' => ['expiry' => 1]]);
        $out = [];
        foreach ($cursor as $doc) {
            $arr = (array)$doc;
            // add days_left computed
            if (!empty($arr['expiry']) && $arr['expiry'] instanceof \MongoDB\BSON\UTCDateTime) {
                $expTs = $arr['expiry']->toDateTime()->getTimestamp();
                $arr['days_left'] = (int) floor(($expTs - time()) / 86400);
                $arr['expiry_str'] = date('Y-m-d', $expTs);
            }
            $out[] = $arr;
        }
        return $out;
    }

    public function countActive(): int
    {
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return 0;
        }

        // Check if user is admin
        $user = new User();
        $currentUser = $user->findById($userId);
        $isAdmin = ($currentUser['access_level'] ?? 'user') === 'admin';

        $filter = ['active' => ['$ne' => false]];

        // Add user_id filter for non-admin users
        if (!$isAdmin) {
            $filter['user_id'] = $userId;
        }

        return $this->collection->countDocuments($filter);
    }
}
