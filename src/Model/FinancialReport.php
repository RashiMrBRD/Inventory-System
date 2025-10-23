<?php

namespace App\Model;

use App\Service\DatabaseService;

/**
 * Financial Report Model
 * Generates financial statements
 */
class FinancialReport
{
    private ChartOfAccounts $chartOfAccounts;
    private JournalEntry $journalEntry;

    public function __construct()
    {
        $this->chartOfAccounts = new ChartOfAccounts();
        $this->journalEntry = new JournalEntry();
    }

    /**
     * Generate Balance Sheet
     * Assets = Liabilities + Equity
     * 
     * @param string|null $asOfDate Date for balance sheet (Y-m-d)
     * @return array
     */
    public function generateBalanceSheet(?string $asOfDate = null): array
    {
        if (!$asOfDate) {
            $asOfDate = date('Y-m-d');
        }

        // Get all accounts
        $assets = $this->chartOfAccounts->getAccountsByType(ChartOfAccounts::TYPE_ASSET);
        $liabilities = $this->chartOfAccounts->getAccountsByType(ChartOfAccounts::TYPE_LIABILITY);
        $equity = $this->chartOfAccounts->getAccountsByType(ChartOfAccounts::TYPE_EQUITY);

        // Calculate totals
        $totalAssets = array_sum(array_column($assets, 'balance'));
        $totalLiabilities = array_sum(array_column($liabilities, 'balance'));
        $totalEquity = array_sum(array_column($equity, 'balance'));

        return [
            'report_name' => 'Balance Sheet',
            'as_of_date' => $asOfDate,
            'assets' => [
                'accounts' => $assets,
                'total' => $totalAssets
            ],
            'liabilities' => [
                'accounts' => $liabilities,
                'total' => $totalLiabilities
            ],
            'equity' => [
                'accounts' => $equity,
                'total' => $totalEquity
            ],
            'total_liabilities_equity' => $totalLiabilities + $totalEquity,
            'balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01
        ];
    }

    /**
     * Generate Income Statement (Profit & Loss)
     * Revenue - Expenses = Net Income
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array
     */
    public function generateIncomeStatement(string $startDate, string $endDate): array
    {
        // Get all income and expense accounts
        $incomeAccounts = $this->chartOfAccounts->getAccountsByType(ChartOfAccounts::TYPE_INCOME);
        $expenseAccounts = $this->chartOfAccounts->getAccountsByType(ChartOfAccounts::TYPE_EXPENSE);

        // For proper income statement, we need to calculate activity in the period
        // For now, using current balances (should be enhanced with date filtering)
        
        $totalIncome = 0;
        $totalExpenses = 0;

        // Calculate income (credits increase income)
        foreach ($incomeAccounts as &$account) {
            // Get activity for this account in the period
            $activity = $this->getAccountActivity($account['account_code'], $startDate, $endDate);
            $account['period_activity'] = $activity;
            $totalIncome += $activity;
        }

        // Calculate expenses (debits increase expenses)
        foreach ($expenseAccounts as &$account) {
            // Get activity for this account in the period
            $activity = $this->getAccountActivity($account['account_code'], $startDate, $endDate);
            $account['period_activity'] = $activity;
            $totalExpenses += $activity;
        }

        $netIncome = $totalIncome - $totalExpenses;

        return [
            'report_name' => 'Income Statement',
            'period_start' => $startDate,
            'period_end' => $endDate,
            'income' => [
                'accounts' => $incomeAccounts,
                'total' => $totalIncome
            ],
            'expenses' => [
                'accounts' => $expenseAccounts,
                'total' => $totalExpenses
            ],
            'net_income' => $netIncome
        ];
    }

    /**
     * Get account activity for a period
     * 
     * @param string $accountCode Account code
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return float Net activity amount
     */
    private function getAccountActivity(string $accountCode, string $startDate, string $endDate): float
    {
        $ledger = $this->journalEntry->getGeneralLedger($accountCode, $startDate, $endDate);
        
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($ledger as $entry) {
            $totalDebit += $entry['debit'];
            $totalCredit += $entry['credit'];
        }

        // Get account type to determine if we return debit or credit balance
        $account = $this->chartOfAccounts->getAccountByCode($accountCode);
        
        if ($account['account_type'] === ChartOfAccounts::TYPE_INCOME) {
            // Income: credits increase
            return $totalCredit - $totalDebit;
        } else {
            // Expenses: debits increase
            return $totalDebit - $totalCredit;
        }
    }

    /**
     * Generate Trial Balance
     * Lists all accounts with their debit/credit balances
     * 
     * @param string|null $asOfDate Date for trial balance
     * @return array
     */
    public function generateTrialBalance(?string $asOfDate = null): array
    {
        if (!$asOfDate) {
            $asOfDate = date('Y-m-d');
        }

        $allAccounts = $this->chartOfAccounts->getAllAccounts(['is_active' => true]);
        
        $totalDebit = 0;
        $totalCredit = 0;
        $trialBalanceLines = [];

        foreach ($allAccounts as $account) {
            $balance = $account['balance'];
            
            // Determine if balance is debit or credit
            $isDebitBalance = in_array($account['account_type'], [
                ChartOfAccounts::TYPE_ASSET,
                ChartOfAccounts::TYPE_EXPENSE
            ]);

            $line = [
                'account_code' => $account['account_code'],
                'account_name' => $account['account_name'],
                'account_type' => $account['account_type'],
                'debit' => $isDebitBalance ? abs($balance) : 0,
                'credit' => !$isDebitBalance ? abs($balance) : 0
            ];

            $totalDebit += $line['debit'];
            $totalCredit += $line['credit'];

            // Only include accounts with balance
            if ($balance != 0) {
                $trialBalanceLines[] = $line;
            }
        }

        return [
            'report_name' => 'Trial Balance',
            'as_of_date' => $asOfDate,
            'accounts' => $trialBalanceLines,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced' => abs($totalDebit - $totalCredit) < 0.01
        ];
    }

    /**
     * Generate Account Activity Report
     * Shows all transactions for a specific account
     * 
     * @param string $accountCode Account code
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array
     */
    public function generateAccountActivity(string $accountCode, string $startDate, string $endDate): array
    {
        $account = $this->chartOfAccounts->getAccountByCode($accountCode);
        
        if (!$account) {
            return [
                'success' => false,
                'message' => 'Account not found'
            ];
        }

        $ledger = $this->journalEntry->getGeneralLedger($accountCode, $startDate, $endDate);
        
        // Calculate running balance
        $runningBalance = 0;
        foreach ($ledger as &$entry) {
            $runningBalance += ($entry['debit'] - $entry['credit']);
            $entry['balance'] = $runningBalance;
        }

        return [
            'report_name' => 'Account Activity Report',
            'account_code' => $account['account_code'],
            'account_name' => $account['account_name'],
            'account_type' => $account['account_type'],
            'period_start' => $startDate,
            'period_end' => $endDate,
            'opening_balance' => $account['balance'] - $runningBalance,
            'transactions' => $ledger,
            'closing_balance' => $account['balance']
        ];
    }

    /**
     * Generate Cash Flow Statement (Simple version)
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array
     */
    public function generateCashFlowStatement(string $startDate, string $endDate): array
    {
        // Get all cash and bank accounts
        $cashAccounts = $this->chartOfAccounts->getAllAccounts([
            'account_type' => ChartOfAccounts::TYPE_ASSET,
            'account_code' => ['$regex' => '^10'] // Accounts starting with 10 (Cash, Bank)
        ]);

        $cashFlow = [];
        $totalCashChange = 0;

        foreach ($cashAccounts as $account) {
            $activity = $this->getAccountActivity($account['account_code'], $startDate, $endDate);
            $cashFlow[] = [
                'account_name' => $account['account_name'],
                'change' => $activity
            ];
            $totalCashChange += $activity;
        }

        return [
            'report_name' => 'Cash Flow Statement',
            'period_start' => $startDate,
            'period_end' => $endDate,
            'cash_accounts' => $cashFlow,
            'net_cash_change' => $totalCashChange
        ];
    }
}
