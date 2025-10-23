<?php

namespace App\Controller;

use App\Model\ChartOfAccounts;
use App\Model\JournalEntry;
use App\Model\FinancialReport;

/**
 * Accounting Controller
 * Handles all accounting-related operations
 */
class AccountingController
{
    private ChartOfAccounts $chartOfAccounts;
    private JournalEntry $journalEntry;
    private FinancialReport $financialReport;

    public function __construct()
    {
        $this->chartOfAccounts = new ChartOfAccounts();
        $this->journalEntry = new JournalEntry();
        $this->financialReport = new FinancialReport();
    }

    /**
     * Get all accounts
     * 
     * @return array
     */
    public function getAllAccounts(): array
    {
        try {
            $accounts = $this->chartOfAccounts->getAllAccounts();
            return [
                'success' => true,
                'data' => $accounts
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get account hierarchy
     * 
     * @return array
     */
    public function getAccountHierarchy(): array
    {
        try {
            $hierarchy = $this->chartOfAccounts->getAccountHierarchy();
            return [
                'success' => true,
                'data' => $hierarchy
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Create new account
     * 
     * @param array $accountData Account data
     * @return array
     */
    public function createAccount(array $accountData): array
    {
        try {
            $accountId = $this->chartOfAccounts->createAccount($accountData);
            
            if ($accountId) {
                return [
                    'success' => true,
                    'account_id' => $accountId,
                    'message' => 'Account created successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to create account'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update account
     * 
     * @param string $accountId Account ID
     * @param array $accountData Updated data
     * @return array
     */
    public function updateAccount(string $accountId, array $accountData): array
    {
        try {
            $updated = $this->chartOfAccounts->updateAccount($accountId, $accountData);
            
            if ($updated) {
                return [
                    'success' => true,
                    'message' => 'Account updated successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to update account'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete account
     * 
     * @param string $accountId Account ID
     * @return array
     */
    public function deleteAccount(string $accountId): array
    {
        try {
            $deleted = $this->chartOfAccounts->deleteAccount($accountId);
            
            if ($deleted) {
                return [
                    'success' => true,
                    'message' => 'Account deleted successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to delete account'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Initialize default chart of accounts
     * 
     * @return array
     */
    public function initializeDefaultAccounts(): array
    {
        try {
            $initialized = $this->chartOfAccounts->initializeDefaultAccounts();
            
            if ($initialized) {
                return [
                    'success' => true,
                    'message' => 'Default accounts initialized successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to initialize accounts'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Create journal entry
     * 
     * @param array $entryData Entry header data
     * @param array $lines Entry lines
     * @return array
     */
    public function createJournalEntry(array $entryData, array $lines): array
    {
        try {
            return $this->journalEntry->createEntry($entryData, $lines);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Post journal entry
     * 
     * @param string $entryId Entry ID
     * @return array
     */
    public function postJournalEntry(string $entryId): array
    {
        try {
            $posted = $this->journalEntry->postEntry($entryId);
            
            if ($posted) {
                return [
                    'success' => true,
                    'message' => 'Entry posted successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to post entry'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Void journal entry
     * 
     * @param string $entryId Entry ID
     * @param string $reason Void reason
     * @return array
     */
    public function voidJournalEntry(string $entryId, string $reason): array
    {
        try {
            $voided = $this->journalEntry->voidEntry($entryId, $reason);
            
            if ($voided) {
                return [
                    'success' => true,
                    'message' => 'Entry voided successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to void entry'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get all journal entries
     * 
     * @param array $filter Optional filter
     * @return array
     */
    public function getAllJournalEntries(array $filter = []): array
    {
        try {
            $entries = $this->journalEntry->getAllEntries($filter);
            return [
                'success' => true,
                'data' => $entries
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get journal entry by ID
     * 
     * @param string $entryId Entry ID
     * @return array
     */
    public function getJournalEntry(string $entryId): array
    {
        try {
            $entry = $this->journalEntry->getEntry($entryId);
            
            if ($entry) {
                return [
                    'success' => true,
                    'data' => $entry
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Entry not found'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate Balance Sheet
     * 
     * @param string|null $asOfDate As of date
     * @return array
     */
    public function getBalanceSheet(?string $asOfDate = null): array
    {
        try {
            $balanceSheet = $this->financialReport->generateBalanceSheet($asOfDate);
            return [
                'success' => true,
                'data' => $balanceSheet
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate Income Statement
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array
     */
    public function getIncomeStatement(string $startDate, string $endDate): array
    {
        try {
            $incomeStatement = $this->financialReport->generateIncomeStatement($startDate, $endDate);
            return [
                'success' => true,
                'data' => $incomeStatement
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate Trial Balance
     * 
     * @param string|null $asOfDate As of date
     * @return array
     */
    public function getTrialBalance(?string $asOfDate = null): array
    {
        try {
            $trialBalance = $this->financialReport->generateTrialBalance($asOfDate);
            return [
                'success' => true,
                'data' => $trialBalance
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
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
        try {
            $ledger = $this->journalEntry->getGeneralLedger($accountCode, $startDate, $endDate);
            return [
                'success' => true,
                'data' => $ledger
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update journal entry
     * 
     * @param string $entryId Entry ID
     * @param array $entryData Entry data
     * @param array $lines Entry lines
     * @return array
     */
    public function updateJournalEntry(string $entryId, array $entryData, array $lines): array
    {
        try {
            return $this->journalEntry->updateEntry($entryId, $entryData, $lines);
        } catch (\Exception $e) {
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
     * @return array
     */
    public function deleteJournalEntry(string $entryId): array
    {
        try {
            $deleted = $this->journalEntry->deleteEntry($entryId);
            
            if ($deleted) {
                return [
                    'success' => true,
                    'message' => 'Entry deleted successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to delete entry'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
