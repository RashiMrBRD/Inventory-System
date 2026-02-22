<?php

namespace App\Model;

use App\Service\DatabaseService;
use MongoDB\Collection;
use MongoDB\BSON\ObjectId;

class Project
{
    private Collection $collection;

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        $collectionName = $db->getCollectionName('projects');
        $this->collection = $db->getCollection($collectionName);
    }

    public function getAll(array $filter = [], array $options = []): array
    {
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return [];
        }

        // Check if user is admin
        $user = (new User())->findById($userId);
        $isAdmin = ($user['access_level'] ?? 'user') === 'admin';

        // Add user_id filter for non-admin users
        if (!$isAdmin) {
            $filter['user_id'] = $userId;
        }

        $options = array_merge([
            'sort' => ['created_at' => -1],
            'limit' => 500
        ], $options);
        $cursor = $this->collection->find($filter, $options);
        $out = [];
        foreach ($cursor as $doc) { $out[] = (array)$doc; }
        return $out;
    }

    /**
     * Search projects by name, client, or description
     * This method searches projects by name, client, description, or status
     * 
     * @param string $query
     * @return array
     */
    public function search(string $query): array
    {
        $regex = new \MongoDB\BSON\Regex($query, 'i');
        
        $items = $this->collection->find([
            '$or' => [
                ['name' => $regex],
                ['client' => $regex],
                ['description' => $regex],
                ['status' => $regex]
            ]
        ])->toArray();
        
        return array_map(function($item) {
            return (array)$item;
        }, $items);
    }

    public function getById(string $id): ?array
    {
        try {
            $doc = $this->collection->findOne(['_id' => new ObjectId($id)]);
            return $doc ? (array)$doc : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function create(array $data): string
    {
        // Get current user ID from session
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            throw new \Exception("User not authenticated");
        }

        $data['user_id'] = $userId;
        $data['created_at'] = new \MongoDB\BSON\UTCDateTime();
        $data['updated_at'] = new \MongoDB\BSON\UTCDateTime();
        $data['spent'] = $data['spent'] ?? 0;

        $result = $this->collection->insertOne($data);
        return (string)$result->getInsertedId();
    }

    public function update(string $id, array $data): bool
    {
        try {
            $data['updated_at'] = new \MongoDB\BSON\UTCDateTime();
            $result = $this->collection->updateOne(
                ['_id' => new ObjectId($id)],
                ['$set' => $data]
            );
            return $result->getModifiedCount() > 0;
        } catch (\Exception $e) {
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
        $all = $this->getAll();
        $activeCount = count(array_filter($all, fn($p) => ($p['status'] ?? '') === 'active'));
        $totalBudget = array_sum(array_map(fn($p) => (float)($p['budget'] ?? 0), $all));
        $totalSpent = array_sum(array_map(fn($p) => (float)($p['spent'] ?? 0), $all));
        
        return [
            'total_projects' => count($all),
            'active_projects' => $activeCount,
            'total_budget' => $totalBudget,
            'total_spent' => $totalSpent,
            'remaining' => max(0, $totalBudget - $totalSpent),
            'avg_progress' => $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100) : 0
        ];
    }

    // Add time entry and calculate billable amount
    public function addTimeEntry(string $projectId, array $timeEntry): bool
    {
        try {
            $project = $this->getById($projectId);
            if (!$project) return false;

            // Calculate billable amount
            $hours = (float)($timeEntry['hours'] ?? 0);
            $rate = (float)($timeEntry['rate'] ?? $project['hourly_rate'] ?? 0);
            $amount = $hours * $rate;

            $timeEntry['amount'] = $amount;
            $timeEntry['date'] = $timeEntry['date'] ?? date('Y-m-d');
            $timeEntry['billable'] = $timeEntry['billable'] ?? true;
            $timeEntry['invoiced'] = false;
            $timeEntry['created_at'] = new \MongoDB\BSON\UTCDateTime();

            // Add to time entries array
            $this->collection->updateOne(
                ['_id' => new ObjectId($projectId)],
                [
                    '$push' => ['time_entries' => $timeEntry],
                    '$inc' => ['spent' => $amount]
                ]
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // Add expense to project
    public function addExpense(string $projectId, array $expense): bool
    {
        try {
            $expense['date'] = $expense['date'] ?? date('Y-m-d');
            $expense['amount'] = (float)($expense['amount'] ?? 0);
            $expense['billable'] = $expense['billable'] ?? true;
            $expense['invoiced'] = false;
            $expense['created_at'] = new \MongoDB\BSON\UTCDateTime();

            $this->collection->updateOne(
                ['_id' => new ObjectId($projectId)],
                [
                    '$push' => ['expenses' => $expense],
                    '$inc' => ['spent' => $expense['amount']]
                ]
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // Get project profitability report
    public function getProfitability(string $projectId): ?array
    {
        $project = $this->getById($projectId);
        if (!$project) return null;

        $budget = (float)($project['budget'] ?? 0);
        $spent = (float)($project['spent'] ?? 0);
        $timeEntries = $project['time_entries'] ?? [];
        $expenses = $project['expenses'] ?? [];

        $laborCost = array_sum(array_map(fn($t) => (float)($t['amount'] ?? 0), $timeEntries));
        $expenseCost = array_sum(array_map(fn($e) => (float)($e['amount'] ?? 0), $expenses));
        $totalCost = $laborCost + $expenseCost;

        $billableTime = array_sum(array_map(
            fn($t) => ($t['billable'] ?? true) ? (float)($t['amount'] ?? 0) : 0,
            $timeEntries
        ));
        $billableExpenses = array_sum(array_map(
            fn($e) => ($e['billable'] ?? true) ? (float)($e['amount'] ?? 0) : 0,
            $expenses
        ));
        $totalBillable = $billableTime + $billableExpenses;

        return [
            'budget' => $budget,
            'spent' => $spent,
            'remaining' => $budget - $spent,
            'labor_cost' => $laborCost,
            'expense_cost' => $expenseCost,
            'total_cost' => $totalCost,
            'billable_amount' => $totalBillable,
            'profit_margin' => $totalBillable > 0 ? (($totalBillable - $totalCost) / $totalBillable) * 100 : 0,
            'budget_utilization' => $budget > 0 ? ($spent / $budget) * 100 : 0
        ];
    }

    // Create invoice from project
    public function generateInvoice(string $projectId, array $options = []): ?array
    {
        $project = $this->getById($projectId);
        if (!$project) return null;

        $timeEntries = array_filter(
            $project['time_entries'] ?? [],
            fn($t) => !($t['invoiced'] ?? false) && ($t['billable'] ?? true)
        );

        $expenses = array_filter(
            $project['expenses'] ?? [],
            fn($e) => !($e['invoiced'] ?? false) && ($e['billable'] ?? true)
        );

        $lineItems = [];

        // Add time entries as line items
        foreach ($timeEntries as $entry) {
            $lineItems[] = [
                'description' => $entry['description'] ?? 'Time Entry',
                'quantity' => (float)($entry['hours'] ?? 0),
                'rate' => (float)($entry['rate'] ?? 0),
                'amount' => (float)($entry['amount'] ?? 0),
                'type' => 'time'
            ];
        }

        // Add expenses as line items
        foreach ($expenses as $expense) {
            $lineItems[] = [
                'description' => $expense['description'] ?? 'Expense',
                'quantity' => 1,
                'rate' => (float)($expense['amount'] ?? 0),
                'amount' => (float)($expense['amount'] ?? 0),
                'type' => 'expense'
            ];
        }

        $total = array_sum(array_map(fn($item) => $item['amount'], $lineItems));

        return [
            'project_id' => $projectId,
            'project_name' => $project['name'] ?? '',
            'client' => $project['client'] ?? '',
            'line_items' => $lineItems,
            'subtotal' => $total,
            'total' => $total,
            'date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days'))
        ];
    }

    // Mark time/expenses as invoiced
    public function markAsInvoiced(string $projectId, string $invoiceId): bool
    {
        try {
            $this->collection->updateOne(
                ['_id' => new ObjectId($projectId)],
                [
                    '$set' => [
                        'time_entries.$[elem].invoiced' => true,
                        'time_entries.$[elem].invoice_id' => $invoiceId,
                        'expenses.$[elem].invoiced' => true,
                        'expenses.$[elem].invoice_id' => $invoiceId
                    ]
                ],
                [
                    'arrayFilters' => [
                        ['elem.invoiced' => ['$ne' => true]],
                        ['elem.billable' => true]
                    ]
                ]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // Create project template
    public function createTemplate(string $projectId, string $templateName): ?string
    {
        try {
            $project = $this->getById($projectId);
            if (!$project) return null;

            // Remove instance-specific data
            unset($project['_id'], $project['project_id'], $project['created_at'], $project['updated_at']);
            unset($project['spent'], $project['time_entries'], $project['expenses']);

            $template = [
                'name' => $templateName,
                'template_data' => $project,
                'created_at' => new \MongoDB\BSON\UTCDateTime(),
                'is_template' => true
            ];

            $result = $this->collection->insertOne($template);
            return (string)$result->getInsertedId();
        } catch (\Exception $e) {
            return null;
        }
    }

    // Create project from template
    public function createFromTemplate(string $templateId, array $overrides = []): ?string
    {
        try {
            $template = $this->getById($templateId);
            if (!$template || !($template['is_template'] ?? false)) return null;

            $templateData = $template['template_data'] ?? [];
            $projectData = array_merge($templateData, $overrides);

            return $this->create($projectData);
        } catch (\Exception $e) {
            return null;
        }
    }

    // Get budget vs actual report
    public function getBudgetVsActual(string $projectId): ?array
    {
        $project = $this->getById($projectId);
        if (!$project) return null;

        $budget = (float)($project['budget'] ?? 0);
        $spent = (float)($project['spent'] ?? 0);
        $variance = $budget - $spent;
        $variancePercent = $budget > 0 ? ($variance / $budget) * 100 : 0;

        return [
            'budget' => $budget,
            'actual' => $spent,
            'variance' => $variance,
            'variance_percent' => $variancePercent,
            'status' => $variance >= 0 ? 'under_budget' : 'over_budget'
        ];
    }

    /**
     * Get dashboard analytics using aggregation (efficient for large datasets)
     */
    public function getDashboardAnalytics(int $daysBack = 30): array
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return ['total_count' => 0, 'active_count' => 0, 'by_status' => [], 'total_budget' => 0, 'total_spent' => 0, 'budget_utilization' => 0];
        }

        $user = (new User())->findById($userId);
        $isAdmin = ($user['access_level'] ?? 'user') === 'admin';
        $baseFilter = $isAdmin ? [] : ['user_id' => $userId];

        // Single aggregation for project stats
        $pipeline = [];
        if (!empty($baseFilter)) {
            $pipeline[] = ['$match' => $baseFilter];
        }
        $pipeline[] = ['$group' => [
            '_id' => null,
            'total_count' => ['$sum' => 1],
            'active_count' => ['$sum' => ['$cond' => [['$eq' => ['$status', 'active']], 1, 0]]],
            'total_budget' => ['$sum' => ['$toDouble' => '$budget']],
            'total_spent' => ['$sum' => ['$toDouble' => '$spent']]
        ]];

        $result = $this->collection->aggregate($pipeline)->toArray();
        $stats = !empty($result) ? (array)$result[0] : [];

        $totalBudget = (float)($stats['total_budget'] ?? 0);
        $totalSpent = (float)($stats['total_spent'] ?? 0);

        // Projects by status
        $statusPipeline = [];
        if (!empty($baseFilter)) {
            $statusPipeline[] = ['$match' => $baseFilter];
        }
        $statusPipeline[] = ['$group' => ['_id' => '$status', 'count' => ['$sum' => 1]]];

        $statusResult = $this->collection->aggregate($statusPipeline)->toArray();
        $byStatus = [];
        foreach ($statusResult as $r) {
            if ($r['_id']) {
                $byStatus[$r['_id']] = (int)$r['count'];
            }
        }

        return [
            'total_count' => (int)($stats['total_count'] ?? 0),
            'active_count' => (int)($stats['active_count'] ?? 0),
            'by_status' => $byStatus,
            'total_budget' => $totalBudget,
            'total_spent' => $totalSpent,
            'budget_utilization' => $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100, 1) : 0
        ];
    }
}
