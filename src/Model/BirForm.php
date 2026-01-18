<?php

namespace App\Model;

use App\Service\DatabaseService;
use MongoDB\Collection;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class BirForm
{
    private Collection $collection;

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        $collectionName = $db->getCollectionName("bir_forms");
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
            if (!isset($formData["created_at"])) {
                $formData["created_at"] = new UTCDateTime();
            }

            // Add default status if not present
            if (!isset($formData["status"])) {
                $formData["status"] = "draft";
            }

            // Add user_id from session if not present
            if (!isset($formData["user_id"]) && isset($_SESSION["user_id"])) {
                $formData["user_id"] = $_SESSION["user_id"];
            }

            $result = $this->collection->insertOne($formData);
            return $result->getInsertedId()->__toString();
        } catch (\Exception $e) {
            error_log("Error creating BIR form: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find a form by ID
     * @param string $id
     * @return array|null
     */
    public function findById(string $id): ?array
    {
        try {
            $objectId = new ObjectId($id);
            $form = $this->collection->findOne(["_id" => $objectId]);

            if ($form) {
                return $this->convertDocument($form);
            }
            return null;
        } catch (\Exception $e) {
            error_log("Error finding BIR form by ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all BIR forms
     * @param array $filters
     * @param array $options
     * @return array
     */
    public function getAll(array $filters = [], array $options = []): array
    {
        try {
            // Get current user ID from session
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                return [];
            }

            // Check if user is admin
            $user = new User();
            $currentUser = $user->findById($userId);
            $isAdmin = ($currentUser['access_level'] ?? 'user') === 'admin';

            // Add user_id filter for non-admin users
            if (!$isAdmin) {
                $filters['user_id'] = $userId;
            }

            $defaultOptions = [
                "sort" => ["created_at" => -1],
                "limit" => 100,
            ];

            $options = array_merge($defaultOptions, $options);

            $cursor = $this->collection->find($filters, $options);
            $forms = [];

            foreach ($cursor as $doc) {
                $forms[] = $this->convertDocument($doc);
            }

            return $forms;
        } catch (\Exception $e) {
            error_log("Error getting all BIR forms: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent forms
     * @param int $limit
     * @return array
     */
    public function getRecentForms(int $limit = 50): array
    {
        return $this->getAll(
            [],
            [
                "sort" => ["created_at" => -1],
                "limit" => $limit,
            ],
        );
    }

    /**
     * Get forms by type
     * @param string $formType
     * @param int $limit
     * @return array
     */
    public function getByType(string $formType, int $limit = 50): array
    {
        return $this->getAll(["form_type" => $formType], ["limit" => $limit]);
    }

    /**
     * Get forms by status
     * @param string $status
     * @param int $limit
     * @return array
     */
    public function getByStatus(string $status, int $limit = 50): array
    {
        return $this->getAll(["status" => $status], ["limit" => $limit]);
    }

    /**
     * Get forms by date range
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getByDateRange(string $startDate, string $endDate): array
    {
        try {
            $start = new UTCDateTime(strtotime($startDate) * 1000);
            $end = new UTCDateTime(strtotime($endDate . " 23:59:59") * 1000);

            return $this->getAll([
                "created_at" => [
                    '$gte' => $start,
                    '$lte' => $end,
                ],
            ]);
        } catch (\Exception $e) {
            error_log(
                "Error getting BIR forms by date range: " . $e->getMessage(),
            );
            return [];
        }
    }

    /**
     * Get forms by period (month/year or quarter/year)
     * @param string $period Format: "MM/YYYY" or "QN YYYY"
     * @return array
     */
    public function getByPeriod(string $period): array
    {
        return $this->getAll(["period" => $period]);
    }

    /**
     * Get forms by user
     * @param string $userId
     * @param int $limit
     * @return array
     */
    public function getByUser(string $userId, int $limit = 50): array
    {
        return $this->getAll(["user_id" => $userId], ["limit" => $limit]);
    }

    /**
     * Update a form
     * @param string $id
     * @param array $data
     * @return bool
     */
    public function update(string $id, array $data): bool
    {
        try {
            $objectId = new ObjectId($id);

            // Add updated timestamp
            $data["updated_at"] = new UTCDateTime();

            $result = $this->collection->updateOne(
                ["_id" => $objectId],
                ['$set' => $data],
            );

            return $result->getModifiedCount() > 0 ||
                $result->getMatchedCount() > 0;
        } catch (\Exception $e) {
            error_log("Error updating BIR form: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update form status
     * @param string $id
     * @param string $status
     * @return bool
     */
    public function updateStatus(string $id, string $status): bool
    {
        $validStatuses = [
            "draft",
            "submitted",
            "filed",
            "approved",
            "rejected",
        ];

        if (!in_array($status, $validStatuses)) {
            error_log("Invalid status: " . $status);
            return false;
        }

        return $this->update($id, ["status" => $status]);
    }

    /**
     * Delete a form
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool
    {
        try {
            $objectId = new ObjectId($id);
            $result = $this->collection->deleteOne(["_id" => $objectId]);

            return $result->getDeletedCount() > 0;
        } catch (\Exception $e) {
            error_log("Error deleting BIR form: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Count forms with optional filters
     * @param array $filters
     * @return int
     */
    public function count(array $filters = []): int
    {
        try {
            return $this->collection->countDocuments($filters);
        } catch (\Exception $e) {
            error_log("Error counting BIR forms: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Count forms by status
     * @param string $status
     * @return int
     */
    public function countByStatus(string $status): int
    {
        return $this->count(["status" => $status]);
    }

    /**
     * Count forms by type
     * @param string $formType
     * @return int
     */
    public function countByType(string $formType): int
    {
        return $this->count(["form_type" => $formType]);
    }

    /**
     * Get forms with upcoming due dates
     * @param int $daysAhead
     * @return array
     */
    public function getUpcomingDueDates(int $daysAhead = 30): array
    {
        try {
            $now = new UTCDateTime();
            $future = new UTCDateTime((time() + $daysAhead * 86400) * 1000);

            return $this->getAll(
                [
                    "due_date" => [
                        '$gte' => $now,
                        '$lte' => $future,
                    ],
                    "status" => ['$ne' => "filed"],
                ],
                [
                    "sort" => ["due_date" => 1],
                ],
            );
        } catch (\Exception $e) {
            error_log("Error getting upcoming due dates: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get total duties/taxes for a period
     * @param string $formType
     * @param string $startDate
     * @param string $endDate
     * @return float
     */
    public function getTotalDuties(
        string $formType = "",
        string $startDate = "",
        string $endDate = "",
    ): float {
        try {
            $match = [];

            if ($formType) {
                $match["form_type"] = $formType;
            }

            if ($startDate && $endDate) {
                $match["created_at"] = [
                    '$gte' => new UTCDateTime(strtotime($startDate) * 1000),
                    '$lte' => new UTCDateTime(
                        strtotime($endDate . " 23:59:59") * 1000,
                    ),
                ];
            }

            $pipeline = [
                ['$match' => $match ?: (object) []],
                [
                    '$group' => [
                        "_id" => null,
                        "total" => ['$sum' => '$total_duties'],
                    ],
                ],
            ];

            $result = $this->collection->aggregate($pipeline)->toArray();

            if (!empty($result)) {
                return floatval($result[0]["total"]);
            }

            return 0.0;
        } catch (\Exception $e) {
            error_log("Error getting total duties: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Get summary statistics
     * @return array
     */
    public function getSummary(): array
    {
        try {
            $totalForms = $this->count();
            $draftCount = $this->countByStatus("draft");
            $submittedCount = $this->countByStatus("submitted");
            $filedCount = $this->countByStatus("filed");
            $approvedCount = $this->countByStatus("approved");

            // Get total duties by form type
            $pipeline = [
                [
                    '$group' => [
                        "_id" => '$form_type',
                        "count" => ['$sum' => 1],
                        "total_duties" => ['$sum' => '$total_duties'],
                    ],
                ],
                ['$sort' => ["count" => -1]],
            ];

            $byType = $this->collection->aggregate($pipeline)->toArray();

            return [
                "total_forms" => $totalForms,
                "by_status" => [
                    "draft" => $draftCount,
                    "submitted" => $submittedCount,
                    "filed" => $filedCount,
                    "approved" => $approvedCount,
                ],
                "by_type" => array_map(function ($item) {
                    return [
                        "form_type" => $item["_id"],
                        "count" => $item["count"],
                        "total_duties" => floatval($item["total_duties"]),
                    ];
                }, $byType),
            ];
        } catch (\Exception $e) {
            error_log("Error getting BIR form summary: " . $e->getMessage());
            return [
                "total_forms" => 0,
                "by_status" => [],
                "by_type" => [],
            ];
        }
    }

    /**
     * Search forms by keyword
     * @param string $keyword
     * @param int $limit
     * @return array
     */
    public function search(string $keyword, int $limit = 50): array
    {
        try {
            $regex = new \MongoDB\BSON\Regex($keyword, "i");

            return $this->getAll(
                [
                    '$or' => [
                        ["form_type" => $regex],
                        ["container_number" => $regex],
                        ["period" => $regex],
                        ["payee_name" => $regex],
                    ],
                ],
                ["limit" => $limit],
            );
        } catch (\Exception $e) {
            error_log("Error searching BIR forms: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Convert MongoDB document to array with string IDs
     * @param mixed $doc
     * @return array
     */
    private function convertDocument($doc): array
    {
        $array = (array) $doc;

        // Convert ObjectId to string
        if (isset($array["_id"])) {
            if ($array["_id"] instanceof ObjectId) {
                $array["_id"] = (string) $array["_id"];
            } elseif (
                is_object($array["_id"]) &&
                method_exists($array["_id"], "__toString")
            ) {
                $array["_id"] = $array["_id"]->__toString();
            }
        }

        // Convert UTCDateTime to string
        if (
            isset($array["created_at"]) &&
            $array["created_at"] instanceof UTCDateTime
        ) {
            $array["created_at"] = $array["created_at"]
                ->toDateTime()
                ->format("Y-m-d H:i:s");
        }

        if (
            isset($array["updated_at"]) &&
            $array["updated_at"] instanceof UTCDateTime
        ) {
            $array["updated_at"] = $array["updated_at"]
                ->toDateTime()
                ->format("Y-m-d H:i:s");
        }

        if (
            isset($array["due_date"]) &&
            $array["due_date"] instanceof UTCDateTime
        ) {
            $array["due_date"] = $array["due_date"]
                ->toDateTime()
                ->format("Y-m-d");
        }

        return $array;
    }

    /**
     * Get the raw MongoDB collection
     * @return Collection
     */
    public function getCollection(): Collection
    {
        return $this->collection;
    }
}
