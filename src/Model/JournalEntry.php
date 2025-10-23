<?php

namespace App\Model;

use App\Service\DatabaseService;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;

/**
 * Journal Entry Model
 * Handles double-entry bookkeeping transactions
 */
class JournalEntry
{
    private Collection $collection;
    private ChartOfAccounts $chartOfAccounts;

    // Entry Status
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_POSTED = 'posted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_VOID = 'void';

    // Entry Types
    const TYPE_GENERAL = 'general';
    const TYPE_SALES = 'sales';
    const TYPE_PURCHASE = 'purchase';
    const TYPE_PAYMENT = 'payment';
    const TYPE_RECEIPT = 'receipt';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_RECURRING = 'recurring';

    private $gridFSBucket;

    public function __construct()
    {
        $db = DatabaseService::getInstance();
        $collectionName = $db->getCollectionName('journal_entries');
        $this->collection = $db->getCollection($collectionName);
        $this->chartOfAccounts = new ChartOfAccounts();
        
        // Initialize GridFS bucket for file attachments
        $mongoDb = $db->getDatabase();
        $this->gridFSBucket = $mongoDb->selectGridFSBucket([
            'bucketName' => 'journal_attachments'
        ]);
    }

    /**
     * Create a new journal entry
     * 
     * @param array $entryData Entry header data
     * @param array $lines Array of entry lines (debits and credits)
     * @return array Result with success status and entry ID
     */
    public function createEntry(array $entryData, array $lines): array
    {
        try {
            // Validate entry lines
            $validation = $this->validateEntry($lines);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }

            // Prepare entry data
            $entry = [
                'entry_number' => $entryData['entry_number'] ?? $this->generateEntryNumber(),
                'entry_date' => $entryData['entry_date'] ?? new \MongoDB\BSON\UTCDateTime(),
                'entry_type' => $entryData['entry_type'] ?? self::TYPE_GENERAL,
                'description' => $entryData['description'] ?? '',
                'reference' => $entryData['reference'] ?? '',
                'notes' => $entryData['notes'] ?? '',
                'status' => self::STATUS_DRAFT,
                'lines' => $lines,
                'total_debit' => $validation['total_debit'],
                'total_credit' => $validation['total_credit'],
                'attachments' => [],
                'tags' => $entryData['tags'] ?? [],
                'is_recurring' => $entryData['is_recurring'] ?? false,
                'recurring_frequency' => $entryData['recurring_frequency'] ?? null,
                'recurring_next_date' => $entryData['recurring_next_date'] ?? null,
                'requires_approval' => $entryData['requires_approval'] ?? false,
                'approved_by' => null,
                'approved_at' => null,
                'created_by' => $entryData['created_by'] ?? null,
                'created_at' => new \MongoDB\BSON\UTCDateTime(),
                'updated_at' => new \MongoDB\BSON\UTCDateTime()
            ];

            $result = $this->collection->insertOne($entry);
            
            // Auto-post if requested
            if ($entryData['auto_post'] ?? false) {
                $this->postEntry($result->getInsertedId()->__toString());
            }

            return [
                'success' => true,
                'entry_id' => $result->getInsertedId()->__toString(),
                'entry_number' => $entry['entry_number']
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate journal entry lines (double-entry check)
     * 
     * @param array $lines Entry lines
     * @return array Validation result
     */
    private function validateEntry(array $lines): array
    {
        if (empty($lines)) {
            return [
                'valid' => false,
                'message' => 'Entry must have at least one line'
            ];
        }

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            if (empty($line['account_code'])) {
                return [
                    'valid' => false,
                    'message' => 'All lines must have an account code'
                ];
            }

            // Verify account exists
            $account = $this->chartOfAccounts->getAccountByCode($line['account_code']);
            if (!$account) {
                return [
                    'valid' => false,
                    'message' => "Account code {$line['account_code']} not found"
                ];
            }

            $debit = floatval($line['debit'] ?? 0);
            $credit = floatval($line['credit'] ?? 0);

            // A line should have either debit or credit, not both
            if ($debit > 0 && $credit > 0) {
                return [
                    'valid' => false,
                    'message' => 'A line cannot have both debit and credit'
                ];
            }

            if ($debit == 0 && $credit == 0) {
                return [
                    'valid' => false,
                    'message' => 'A line must have either debit or credit amount'
                ];
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        // Check if debits equal credits (double-entry rule)
        if (abs($totalDebit - $totalCredit) > 0.01) { // Allow for rounding errors
            return [
                'valid' => false,
                'message' => "Debits ({$totalDebit}) must equal credits ({$totalCredit})"
            ];
        }

        return [
            'valid' => true,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit
        ];
    }

    /**
     * Post a journal entry (update account balances)
     * 
     * @param string $entryId Entry ID
     * @return bool
     */
    public function postEntry(string $entryId): bool
    {
        try {
            $entry = $this->getEntry($entryId);
            
            if (!$entry) {
                throw new \Exception("Entry not found");
            }

            if ($entry['status'] !== self::STATUS_DRAFT) {
                throw new \Exception("Only draft entries can be posted");
            }

            // Update account balances
            foreach ($entry['lines'] as $line) {
                $account = $this->chartOfAccounts->getAccountByCode($line['account_code']);
                $accountId = (string)$account['_id'];
                
                $debit = floatval($line['debit'] ?? 0);
                $credit = floatval($line['credit'] ?? 0);

                // Determine if the account increases with debit or credit
                $amount = $this->calculateBalanceChange($account['account_type'], $debit, $credit);

                // Update account balance
                $this->chartOfAccounts->updateBalance($accountId, $amount);
            }

            // Update entry status
            $this->collection->updateOne(
                ['_id' => new ObjectId($entryId)],
                [
                    '$set' => [
                        'status' => self::STATUS_POSTED,
                        'posted_at' => new \MongoDB\BSON\UTCDateTime(),
                        'updated_at' => new \MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );

            return true;
        } catch (\Exception $e) {
            error_log("Error posting entry: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculate balance change based on account type and debit/credit
     * 
     * @param string $accountType Account type
     * @param float $debit Debit amount
     * @param float $credit Credit amount
     * @return float Amount to add to balance
     */
    private function calculateBalanceChange(string $accountType, float $debit, float $credit): float
    {
        // Assets, Expenses: Debit increases, Credit decreases
        // Liabilities, Equity, Income: Credit increases, Debit decreases
        
        $debitAccounts = [ChartOfAccounts::TYPE_ASSET, ChartOfAccounts::TYPE_EXPENSE];
        $creditAccounts = [ChartOfAccounts::TYPE_LIABILITY, ChartOfAccounts::TYPE_EQUITY, ChartOfAccounts::TYPE_INCOME];

        if (in_array($accountType, $debitAccounts)) {
            return $debit - $credit;
        } elseif (in_array($accountType, $creditAccounts)) {
            return $credit - $debit;
        }

        return 0;
    }

    /**
     * Void a journal entry
     * 
     * @param string $entryId Entry ID
     * @param string $reason Void reason
     * @return bool
     */
    public function voidEntry(string $entryId, string $reason = ''): bool
    {
        try {
            $entry = $this->getEntry($entryId);
            
            if (!$entry) {
                throw new \Exception("Entry not found");
            }

            if ($entry['status'] === self::STATUS_VOID) {
                throw new \Exception("Entry is already voided");
            }

            // If entry was posted, reverse the account balances
            if ($entry['status'] === self::STATUS_POSTED) {
                foreach ($entry['lines'] as $line) {
                    $account = $this->chartOfAccounts->getAccountByCode($line['account_code']);
                    $accountId = (string)$account['_id'];
                    
                    $debit = floatval($line['debit'] ?? 0);
                    $credit = floatval($line['credit'] ?? 0);

                    // Reverse the balance change
                    $amount = $this->calculateBalanceChange($account['account_type'], $debit, $credit);
                    $this->chartOfAccounts->updateBalance($accountId, -$amount);
                }
            }

            // Update entry status
            $this->collection->updateOne(
                ['_id' => new ObjectId($entryId)],
                [
                    '$set' => [
                        'status' => self::STATUS_VOID,
                        'void_reason' => $reason,
                        'voided_at' => new \MongoDB\BSON\UTCDateTime(),
                        'updated_at' => new \MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );

            return true;
        } catch (\Exception $e) {
            error_log("Error voiding entry: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update journal entry
     * 
     * @param string $entryId Entry ID
     * @param array $entryData Entry data
     * @param array $lines Entry lines
     * @return array Result with success status
     */
    public function updateEntry(string $entryId, array $entryData, array $lines): array
    {
        try {
            $entry = $this->getEntry($entryId);
            
            if (!$entry) {
                return [
                    'success' => false,
                    'message' => 'Entry not found'
                ];
            }

            // Only draft entries can be updated
            if ($entry['status'] !== self::STATUS_DRAFT) {
                return [
                    'success' => false,
                    'message' => 'Only draft entries can be updated'
                ];
            }

            // Validate entry lines
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($lines as &$line) {
                $account = $this->chartOfAccounts->getAccountByCode($line['account_code']);
                
                if (!$account) {
                    return [
                        'success' => false,
                        'message' => "Invalid account code: {$line['account_code']}"
                    ];
                }

                $line['account_name'] = $account['account_name'];
                $debit = floatval($line['debit'] ?? 0);
                $credit = floatval($line['credit'] ?? 0);

                if ($debit > 0 && $credit > 0) {
                    return [
                        'success' => false,
                        'message' => 'A line cannot have both debit and credit'
                    ];
                }

                if ($debit === 0 && $credit === 0) {
                    return [
                        'success' => false,
                        'message' => 'Each line must have either a debit or credit amount'
                    ];
                }

                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            if (abs($totalDebit - $totalCredit) > 0.01) {
                return [
                    'success' => false,
                    'message' => 'Debits and credits must be balanced'
                ];
            }

            // Update entry
            $updateData = [
                'entry_date' => $entryData['entry_date'],
                'entry_type' => $entryData['entry_type'],
                'description' => $entryData['description'],
                'reference' => $entryData['reference'],
                'lines' => $lines,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'tags' => $entryData['tags'] ?? [],
                'is_recurring' => $entryData['is_recurring'] ?? false,
                'recurring_frequency' => $entryData['recurring_frequency'] ?? null,
                'requires_approval' => $entryData['requires_approval'] ?? false,
                'auto_post' => $entryData['auto_post'] ?? false,
                'updated_at' => new \MongoDB\BSON\UTCDateTime(),
                'modified_by' => $entryData['created_by'],
                'modified_at' => new \MongoDB\BSON\UTCDateTime(),
                'modified_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ];

            $result = $this->collection->updateOne(
                ['_id' => new ObjectId($entryId)],
                ['$set' => $updateData]
            );

            if ($result->getModifiedCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Entry updated successfully',
                    'entry_id' => $entryId
                ];
            }

            return [
                'success' => false,
                'message' => 'No changes were made'
            ];
        } catch (\Exception $e) {
            error_log("Error updating entry: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete journal entry
     * 
     * @param string $entryId Entry ID
     * @return bool
     */
    public function deleteEntry(string $entryId): bool
    {
        try {
            $entry = $this->getEntry($entryId);
            
            if (!$entry) {
                throw new \Exception("Entry not found");
            }

            // Only draft entries can be deleted
            if ($entry['status'] !== self::STATUS_DRAFT) {
                throw new \Exception("Only draft entries can be deleted. Posted entries must be voided.");
            }

            // Delete all attachments associated with this entry
            if (!empty($entry['attachments'])) {
                foreach ($entry['attachments'] as $attachment) {
                    try {
                        $this->gridFSBucket->delete(new ObjectId($attachment['file_id']));
                    } catch (\Exception $e) {
                        error_log("Error deleting attachment: " . $e->getMessage());
                    }
                }
            }

            // Delete the entry
            $result = $this->collection->deleteOne(['_id' => new ObjectId($entryId)]);

            return $result->getDeletedCount() > 0;
        } catch (\Exception $e) {
            error_log("Error deleting entry: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get journal entry by ID
     * 
     * @param string $entryId Entry ID
     * @return array|null
     */
    public function getEntry(string $entryId): ?array
    {
        try {
            $entry = $this->collection->findOne(['_id' => new ObjectId($entryId)]);
            return $entry ? (array)$entry : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get all journal entries
     * 
     * @param array $filter Optional filter
     * @param int $limit Limit results
     * @return array
     */
    public function getAllEntries(array $filter = [], int $limit = 100): array
    {
        $options = [
            'sort' => ['entry_date' => -1, 'entry_number' => -1],
            'limit' => $limit
        ];
        
        $entries = $this->collection->find($filter, $options)->toArray();
        
        return array_map(function($entry) {
            return (array)$entry;
        }, $entries);
    }

    /**
     * Get entries by date range
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array
     */
    public function getEntriesByDateRange(string $startDate, string $endDate): array
    {
        $start = new \MongoDB\BSON\UTCDateTime(strtotime($startDate) * 1000);
        $end = new \MongoDB\BSON\UTCDateTime(strtotime($endDate . ' 23:59:59') * 1000);

        $filter = [
            'entry_date' => [
                '$gte' => $start,
                '$lte' => $end
            ],
            'status' => self::STATUS_POSTED
        ];

        return $this->getAllEntries($filter, 1000);
    }

    /**
     * Generate unique entry number
     * 
     * @return string
     */
    private function generateEntryNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        
        // Find the highest entry number for this month
        $lastEntry = $this->collection->findOne(
            ['entry_number' => ['$regex' => "^JE-{$year}{$month}"]],
            ['sort' => ['entry_number' => -1]]
        );

        if ($lastEntry) {
            $lastNumber = intval(substr($lastEntry['entry_number'], -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return "JE-{$year}{$month}-" . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get general ledger for an account
     * 
     * @param string $accountCode Account code
     * @param string|null $startDate Start date
     * @param string|null $endDate End date
     * @return array
     */
    public function getGeneralLedger(string $accountCode, ?string $startDate = null, ?string $endDate = null): array
    {
        $filter = [
            'status' => self::STATUS_POSTED,
            'lines.account_code' => $accountCode
        ];

        if ($startDate && $endDate) {
            $start = new \MongoDB\BSON\UTCDateTime(strtotime($startDate) * 1000);
            $end = new \MongoDB\BSON\UTCDateTime(strtotime($endDate . ' 23:59:59') * 1000);
            $filter['entry_date'] = ['$gte' => $start, '$lte' => $end];
        }

        $entries = $this->getAllEntries($filter, 10000);
        
        // Extract lines for this account
        $ledger = [];
        foreach ($entries as $entry) {
            foreach ($entry['lines'] as $line) {
                if ($line['account_code'] === $accountCode) {
                    $ledger[] = [
                        'date' => $entry['entry_date'],
                        'entry_number' => $entry['entry_number'],
                        'description' => $line['description'] ?? $entry['description'],
                        'debit' => $line['debit'] ?? 0,
                        'credit' => $line['credit'] ?? 0,
                        'reference' => $entry['reference'] ?? ''
                    ];
                }
            }
        }

        return $ledger;
    }

    /**
     * Upload attachment to journal entry
     * 
     * @param string $entryId Entry ID
     * @param array $file File from $_FILES
     * @return array Result with success status
     */
    public function addAttachment(string $entryId, array $file): array
    {
        try {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception("File upload failed");
            }

            // Validate file size (max 10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                throw new \Exception("File size exceeds 10MB limit");
            }

            // Upload to GridFS
            $stream = fopen($file['tmp_name'], 'r');
            $fileId = $this->gridFSBucket->uploadFromStream(
                $file['name'],
                $stream,
                ['metadata' => [
                    'entry_id' => $entryId,
                    'original_name' => $file['name'],
                    'mime_type' => $file['type'],
                    'size' => $file['size'],
                    'uploaded_at' => new \MongoDB\BSON\UTCDateTime()
                ]]
            );
            fclose($stream);

            // Add attachment reference to entry
            $attachment = [
                'file_id' => (string)$fileId,
                'filename' => $file['name'],
                'mime_type' => $file['type'],
                'size' => $file['size'],
                'uploaded_at' => new \MongoDB\BSON\UTCDateTime()
            ];

            $this->collection->updateOne(
                ['_id' => new ObjectId($entryId)],
                [
                    '$push' => ['attachments' => $attachment],
                    '$set' => ['updated_at' => new \MongoDB\BSON\UTCDateTime()]
                ]
            );

            return [
                'success' => true,
                'file_id' => (string)$fileId,
                'filename' => $file['name']
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete attachment from journal entry
     * 
     * @param string $entryId Entry ID
     * @param string $fileId GridFS file ID
     * @return bool
     */
    public function deleteAttachment(string $entryId, string $fileId): bool
    {
        try {
            // Delete from GridFS
            $this->gridFSBucket->delete(new ObjectId($fileId));

            // Remove from entry
            $this->collection->updateOne(
                ['_id' => new ObjectId($entryId)],
                [
                    '$pull' => ['attachments' => ['file_id' => $fileId]],
                    '$set' => ['updated_at' => new \MongoDB\BSON\UTCDateTime()]
                ]
            );

            return true;
        } catch (\Exception $e) {
            error_log("Error deleting attachment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Download attachment
     * 
     * @param string $fileId GridFS file ID
     * @return array|null File data with stream
     */
    public function downloadAttachment(string $fileId): ?array
    {
        try {
            $file = $this->gridFSBucket->findOne(['_id' => new ObjectId($fileId)]);
            if (!$file) {
                return null;
            }

            return [
                'filename' => $file->filename,
                'mime_type' => $file->metadata['mime_type'] ?? 'application/octet-stream',
                'size' => $file->length,
                'stream' => $this->gridFSBucket->openDownloadStream(new ObjectId($fileId))
            ];
        } catch (\Exception $e) {
            error_log("Error downloading attachment: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create recurring entry template
     * 
     * @param string $entryId Entry ID to use as template
     * @return bool
     */
    public function createRecurringTemplate(string $entryId): bool
    {
        try {
            $entry = $this->getEntry($entryId);
            if (!$entry) {
                throw new \Exception("Entry not found");
            }

            $this->collection->updateOne(
                ['_id' => new ObjectId($entryId)],
                [
                    '$set' => [
                        'is_recurring' => true,
                        'entry_type' => self::TYPE_RECURRING,
                        'updated_at' => new \MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );

            return true;
        } catch (\Exception $e) {
            error_log("Error creating recurring template: " . $e->getMessage());
            return false;
        }
    }
}
