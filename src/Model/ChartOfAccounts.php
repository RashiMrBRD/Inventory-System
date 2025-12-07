<?php

namespace App\Model;

use App\Service\DatabaseService;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;

/**
 * Chart of Accounts Model
 * Manages the accounting chart of accounts
 */
class ChartOfAccounts
{
    private Collection $collection;

    // Account Types (Main Categories)
    const TYPE_ASSET = 'asset';
    const TYPE_LIABILITY = 'liability';
    const TYPE_EQUITY = 'equity';
    const TYPE_INCOME = 'income';
    const TYPE_EXPENSE = 'expense';

    // Account Subtypes
    const SUBTYPE_CURRENT_ASSET = 'current_asset';
    const SUBTYPE_FIXED_ASSET = 'fixed_asset';
    const SUBTYPE_CURRENT_LIABILITY = 'current_liability';
    const SUBTYPE_LONG_TERM_LIABILITY = 'long_term_liability';
    const SUBTYPE_OPERATING_INCOME = 'operating_income';
    const SUBTYPE_OTHER_INCOME = 'other_income';
    const SUBTYPE_OPERATING_EXPENSE = 'operating_expense';
    const SUBTYPE_COST_OF_GOODS_SOLD = 'cost_of_goods_sold';

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        $collectionName = $db->getCollectionName('chart_of_accounts');
        $this->collection = $db->getCollection($collectionName);
    }

    /**
     * Create a new account
     * 
     * @param array $accountData
     * @return string|null Account ID
     */
    public function createAccount(array $accountData): ?string
    {
        try {
            // Validate required fields
            $required = ['account_code', 'account_name', 'account_type'];
            foreach ($required as $field) {
                if (empty($accountData[$field])) {
                    throw new \Exception("Missing required field: {$field}");
                }
            }

            // Check if account code already exists
            $existing = $this->collection->findOne(['account_code' => $accountData['account_code']]);
            if ($existing) {
                throw new \Exception("Account code already exists");
            }

            // Set defaults
            $accountData['is_active'] = $accountData['is_active'] ?? true;
            $accountData['is_system'] = $accountData['is_system'] ?? false;
            $accountData['balance'] = $accountData['balance'] ?? 0;
            $accountData['created_at'] = new \MongoDB\BSON\UTCDateTime();
            $accountData['updated_at'] = new \MongoDB\BSON\UTCDateTime();

            $result = $this->collection->insertOne($accountData);
            return $result->getInsertedId()->__toString();
        } catch (\Exception $e) {
            error_log("Error creating account: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all accounts
     * 
     * @param array $filter Optional filter criteria
     * @return array
     */
    public function getAllAccounts(array $filter = []): array
    {
        $options = ['sort' => ['account_code' => 1]];
        $accounts = $this->collection->find($filter, $options)->toArray();
        
        return array_map(function($account) {
            return (array)$account;
        }, $accounts);
    }

    /**
     * Search accounts by name, code, or type
     * This method searches accounts by account name, account code, or account type
     * 
     * @param string $query
     * @return array
     */
    public function search(string $query): array
    {
        $regex = new \MongoDB\BSON\Regex($query, 'i');
        
        $items = $this->collection->find([
            '$or' => [
                ['account_name' => $regex],
                ['account_code' => $regex],
                ['account_type' => $regex],
                ['account_subtype' => $regex]
            ]
        ])->toArray();
        
        return array_map(function($item) {
            return (array)$item;
        }, $items);
    }

    /**
     * Get accounts by type
     * 
     * @param string $type Account type
     * @return array
     */
    public function getAccountsByType(string $type): array
    {
        return $this->getAllAccounts(['account_type' => $type, 'is_active' => true]);
    }

    /**
     * Get account by ID
     * 
     * @param string $id Account ID
     * @return array|null
     */
    public function getAccount(string $id): ?array
    {
        try {
            $account = $this->collection->findOne(['_id' => new ObjectId($id)]);
            return $account ? (array)$account : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get account by code
     * 
     * @param string $code Account code
     * @return array|null
     */
    public function getAccountByCode(string $code): ?array
    {
        $account = $this->collection->findOne(['account_code' => $code]);
        return $account ? (array)$account : null;
    }

    /**
     * Update account
     * 
     * @param string $id Account ID
     * @param array $accountData Updated data
     * @return bool
     */
    public function updateAccount(string $id, array $accountData): bool
    {
        try {
            $accountData['updated_at'] = new \MongoDB\BSON\UTCDateTime();
            
            $result = $this->collection->updateOne(
                ['_id' => new ObjectId($id)],
                ['$set' => $accountData]
            );
            
            return $result->getModifiedCount() > 0 || $result->getMatchedCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update account balance (used by journal entries)
     * 
     * @param string $id Account ID
     * @param float $amount Amount to add (positive or negative)
     * @return bool
     */
    public function updateBalance(string $id, float $amount): bool
    {
        try {
            $result = $this->collection->updateOne(
                ['_id' => new ObjectId($id)],
                [
                    '$inc' => ['balance' => $amount],
                    '$set' => ['updated_at' => new \MongoDB\BSON\UTCDateTime()]
                ]
            );
            
            return $result->getModifiedCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete account (soft delete - mark as inactive)
     * 
     * @param string $id Account ID
     * @return bool
     */
    public function deleteAccount(string $id): bool
    {
        try {
            // Check if account is system account
            $account = $this->getAccount($id);
            if ($account && $account['is_system']) {
                throw new \Exception("Cannot delete system account");
            }

            // Soft delete - mark as inactive
            $result = $this->collection->updateOne(
                ['_id' => new ObjectId($id)],
                [
                    '$set' => [
                        'is_active' => false,
                        'updated_at' => new \MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );
            
            return $result->getModifiedCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get account hierarchy (grouped by type)
     * 
     * @return array
     */
    public function getAccountHierarchy(): array
    {
        $hierarchy = [
            self::TYPE_ASSET => [],
            self::TYPE_LIABILITY => [],
            self::TYPE_EQUITY => [],
            self::TYPE_INCOME => [],
            self::TYPE_EXPENSE => []
        ];

        $accounts = $this->getAllAccounts(['is_active' => true]);

        foreach ($accounts as $account) {
            $type = $account['account_type'];
            if (isset($hierarchy[$type])) {
                $hierarchy[$type][] = $account;
            }
        }

        return $hierarchy;
    }

    /**
     * Get total balance by account type
     * 
     * @param string $type Account type
     * @return float
     */
    public function getTotalByType(string $type): float
    {
        $pipeline = [
            [
                '$match' => [
                    'account_type' => $type,
                    'is_active' => true
                ]
            ],
            [
                '$group' => [
                    '_id' => null,
                    'total' => ['$sum' => '$balance']
                ]
            ]
        ];

        $result = $this->collection->aggregate($pipeline)->toArray();
        
        return $result[0]['total'] ?? 0;
    }

    /**
     * Initialize default chart of accounts
     * Creates a standard set of accounts for a small business
     * 
     * @return bool
     */
    public function initializeDefaultAccounts(): bool
    {
        $defaultAccounts = [
            // ASSETS
            ['account_code' => '1000', 'account_name' => 'Cash', 'account_type' => self::TYPE_ASSET, 'account_subtype' => self::SUBTYPE_CURRENT_ASSET, 'is_system' => true],
            ['account_code' => '1010', 'account_name' => 'Petty Cash', 'account_type' => self::TYPE_ASSET, 'account_subtype' => self::SUBTYPE_CURRENT_ASSET],
            ['account_code' => '1020', 'account_name' => 'Bank Account', 'account_type' => self::TYPE_ASSET, 'account_subtype' => self::SUBTYPE_CURRENT_ASSET, 'is_system' => true],
            ['account_code' => '1100', 'account_name' => 'Accounts Receivable', 'account_type' => self::TYPE_ASSET, 'account_subtype' => self::SUBTYPE_CURRENT_ASSET, 'is_system' => true],
            ['account_code' => '1200', 'account_name' => 'Inventory', 'account_type' => self::TYPE_ASSET, 'account_subtype' => self::SUBTYPE_CURRENT_ASSET, 'is_system' => true],
            ['account_code' => '1500', 'account_name' => 'Furniture & Equipment', 'account_type' => self::TYPE_ASSET, 'account_subtype' => self::SUBTYPE_FIXED_ASSET],
            ['account_code' => '1510', 'account_name' => 'Accumulated Depreciation', 'account_type' => self::TYPE_ASSET, 'account_subtype' => self::SUBTYPE_FIXED_ASSET],

            // LIABILITIES
            ['account_code' => '2000', 'account_name' => 'Accounts Payable', 'account_type' => self::TYPE_LIABILITY, 'account_subtype' => self::SUBTYPE_CURRENT_LIABILITY, 'is_system' => true],
            ['account_code' => '2100', 'account_name' => 'Credit Card Payable', 'account_type' => self::TYPE_LIABILITY, 'account_subtype' => self::SUBTYPE_CURRENT_LIABILITY],
            ['account_code' => '2200', 'account_name' => 'Sales Tax Payable', 'account_type' => self::TYPE_LIABILITY, 'account_subtype' => self::SUBTYPE_CURRENT_LIABILITY, 'is_system' => true],
            ['account_code' => '2500', 'account_name' => 'Long-term Loan', 'account_type' => self::TYPE_LIABILITY, 'account_subtype' => self::SUBTYPE_LONG_TERM_LIABILITY],

            // EQUITY
            ['account_code' => '3000', 'account_name' => 'Owner\'s Equity', 'account_type' => self::TYPE_EQUITY, 'is_system' => true],
            ['account_code' => '3100', 'account_name' => 'Retained Earnings', 'account_type' => self::TYPE_EQUITY, 'is_system' => true],

            // INCOME
            ['account_code' => '4000', 'account_name' => 'Sales Revenue', 'account_type' => self::TYPE_INCOME, 'account_subtype' => self::SUBTYPE_OPERATING_INCOME, 'is_system' => true],
            ['account_code' => '4100', 'account_name' => 'Service Revenue', 'account_type' => self::TYPE_INCOME, 'account_subtype' => self::SUBTYPE_OPERATING_INCOME],
            ['account_code' => '4900', 'account_name' => 'Other Income', 'account_type' => self::TYPE_INCOME, 'account_subtype' => self::SUBTYPE_OTHER_INCOME],

            // EXPENSES
            ['account_code' => '5000', 'account_name' => 'Cost of Goods Sold', 'account_type' => self::TYPE_EXPENSE, 'account_subtype' => self::SUBTYPE_COST_OF_GOODS_SOLD, 'is_system' => true],
            ['account_code' => '6000', 'account_name' => 'Rent Expense', 'account_type' => self::TYPE_EXPENSE, 'account_subtype' => self::SUBTYPE_OPERATING_EXPENSE],
            ['account_code' => '6100', 'account_name' => 'Utilities Expense', 'account_type' => self::TYPE_EXPENSE, 'account_subtype' => self::SUBTYPE_OPERATING_EXPENSE],
            ['account_code' => '6200', 'account_name' => 'Salaries Expense', 'account_type' => self::TYPE_EXPENSE, 'account_subtype' => self::SUBTYPE_OPERATING_EXPENSE],
            ['account_code' => '6300', 'account_name' => 'Office Supplies', 'account_type' => self::TYPE_EXPENSE, 'account_subtype' => self::SUBTYPE_OPERATING_EXPENSE],
            ['account_code' => '6400', 'account_name' => 'Advertising Expense', 'account_type' => self::TYPE_EXPENSE, 'account_subtype' => self::SUBTYPE_OPERATING_EXPENSE],
            ['account_code' => '6500', 'account_name' => 'Insurance Expense', 'account_type' => self::TYPE_EXPENSE, 'account_subtype' => self::SUBTYPE_OPERATING_EXPENSE],
            ['account_code' => '6600', 'account_name' => 'Depreciation Expense', 'account_type' => self::TYPE_EXPENSE, 'account_subtype' => self::SUBTYPE_OPERATING_EXPENSE],
        ];

        try {
            foreach ($defaultAccounts as $account) {
                $this->createAccount($account);
            }
            return true;
        } catch (\Exception $e) {
            error_log("Error initializing default accounts: " . $e->getMessage());
            return false;
        }
    }
}
