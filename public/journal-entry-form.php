<?php
/**
 * Journal Entry Form
 * Create new journal entry
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
use App\Controller\AccountingController;

$accountingController = new AccountingController();
$error = '';

// Get currency settings
$currency = $_SESSION['currency'] ?? 'PHP';
$currencySymbols = [
    'PHP' => '₱',
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'JPY' => '¥',
    'CNY' => '¥',
    'KRW' => '₩',
    'MYR' => 'RM',
    'SGD' => 'S$',
    'THB' => '฿',
    'IDR' => 'Rp',
    'VND' => '₫',
    'INR' => '₹',
    'AUD' => 'A$',
    'CAD' => 'C$'
];
$currencySymbol = $currencySymbols[$currency] ?? $currency . ' ';

// Get all accounts for dropdown
$accountsResult = $accountingController->getAllAccounts();
$accounts = $accountsResult['success'] ? $accountsResult['data'] : [];

// Handle edit mode
$editMode = false;
$editEntry = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $editMode = true;
    $entryResult = $accountingController->getJournalEntry($_GET['edit']);
    if ($entryResult['success']) {
        $editEntry = $entryResult['data'];
    }
}

// Handle duplicate/copy mode
$copyMode = false;
$sourceEntry = null;
if (isset($_GET['copy']) && !empty($_GET['copy'])) {
    $copyMode = true;
    $entryResult = $accountingController->getJournalEntry($_GET['copy']);
    if ($entryResult['success']) {
        $sourceEntry = $entryResult['data'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tags = !empty($_POST['tags']) ? array_map('trim', explode(',', $_POST['tags'])) : [];
    $tags = array_filter($tags); // Remove empty values
    
    $entryData = [
        'entry_date' => new \MongoDB\BSON\UTCDateTime(strtotime($_POST['entry_date'] ?? 'now') * 1000),
        'entry_type' => $_POST['entry_type'] ?? 'general',
        'description' => $_POST['description'] ?? '',
        'reference' => $_POST['reference'] ?? '',
        'tags' => $tags,
        'is_recurring' => isset($_POST['is_recurring']),
        'recurring_frequency' => $_POST['recurring_frequency'] ?? null,
        'requires_approval' => isset($_POST['requires_approval']),
        'auto_post' => isset($_POST['auto_post']),
        'created_by' => $_SESSION['user_id']
    ];

    // Parse entry lines from form
    $lines = [];
    $lineCount = intval($_POST['line_count'] ?? 0);
    
    for ($i = 0; $i < $lineCount; $i++) {
        $accountCode = $_POST["line_{$i}_account"] ?? '';
        $debit = floatval($_POST["line_{$i}_debit"] ?? 0);
        $credit = floatval($_POST["line_{$i}_credit"] ?? 0);
        $lineDesc = $_POST["line_{$i}_description"] ?? '';

        if ($accountCode && ($debit > 0 || $credit > 0)) {
            $lines[] = [
                'account_code' => $accountCode,
                'debit' => $debit,
                'credit' => $credit,
                'description' => $lineDesc
            ];
        }
    }

    if (empty($lines)) {
        $error = 'Please add at least one entry line';
    } else {
        // Check if we're updating an existing entry
        $isUpdate = isset($_POST['entry_id']) && !empty($_POST['entry_id']);
        
        if ($isUpdate) {
            // Update existing entry
            $entryId = $_POST['entry_id'];
            $result = $accountingController->updateJournalEntry($entryId, $entryData, $lines);
        } else {
            // Create new entry
            $result = $accountingController->createJournalEntry($entryData, $lines);
        }
        
        if ($result['success']) {
            $entryId = $isUpdate ? $entryId : $result['entry_id'];
            
            // Handle file attachments
            if (!empty($_FILES['attachments']['name'][0])) {
                $journalEntryModel = new \App\Model\JournalEntry();
                foreach ($_FILES['attachments']['name'] as $key => $filename) {
                    if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['attachments']['name'][$key],
                            'type' => $_FILES['attachments']['type'][$key],
                            'tmp_name' => $_FILES['attachments']['tmp_name'][$key],
                            'error' => $_FILES['attachments']['error'][$key],
                            'size' => $_FILES['attachments']['size'][$key]
                        ];
                        $journalEntryModel->addAttachment($entryId, $file);
                    }
                }
            }
            
            // Set appropriate success message
            if ($isUpdate) {
                $_SESSION['flash_message'] = 'Journal entry updated successfully';
            } else {
                $_SESSION['flash_message'] = 'Journal entry created successfully';
            }
            $_SESSION['flash_type'] = 'success';
            header('Location: journal-entries.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Set page variables
$pageTitle = 'New Journal Entry';

// Start output buffering for content
ob_start();
?>

<style>
.je-layout {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 1.5rem;
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 1rem;
}

.je-main-form {
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  overflow: hidden;
}

.je-form-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-secondary);
}

.je-form-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.je-form-body {
  padding: 1.5rem;
}

.je-section {
  margin-bottom: 1.5rem;
}

.je-section-title {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-secondary);
  margin-bottom: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.je-grid {
  display: grid;
  gap: 1rem;
}

.je-grid-2 { grid-template-columns: repeat(2, 1fr); }
.je-grid-3 { grid-template-columns: repeat(3, 1fr); }

.je-field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.je-label {
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--text-primary);
}

.je-input, .je-select, .je-textarea {
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  font-size: 0.875rem;
  background: var(--bg-primary);
  transition: border-color 0.15s, box-shadow 0.15s;
}

.je-input:focus, .je-select:focus, .je-textarea:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.je-input-sm {
  padding: 0.375rem 0.625rem;
  font-size: 0.8125rem;
}

.je-textarea {
  resize: vertical;
  min-height: 80px;
}

/* Input Group with Button (Shadcn-inspired) */
.je-input-group {
  position: relative;
  display: flex;
  align-items: stretch;
  width: 100%;
  border-radius: 6px;
  border: 1px solid var(--border-color);
  background: var(--bg-primary);
  transition: border-color 0.15s, box-shadow 0.15s;
}

.je-input-group:focus-within {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.je-input-with-button {
  flex: 1;
  border: none !important;
  border-radius: 6px 0 0 6px;
  box-shadow: none !important;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  background: transparent;
}

.je-input-with-button:focus {
  outline: none;
  box-shadow: none !important;
}

.je-input-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0 1rem;
  border: none;
  border-left: 1px solid var(--border-color);
  background: var(--bg-secondary);
  color: var(--text-primary);
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
  border-radius: 0 6px 6px 0;
  transition: all 0.15s;
  white-space: nowrap;
}

.je-input-button:hover {
  background: var(--bg-primary);
  color: var(--color-primary);
}

.je-input-button:active {
  transform: scale(0.98);
}

.je-input-button svg {
  flex-shrink: 0;
  opacity: 0.7;
  transition: opacity 0.15s;
}

.je-input-button:hover svg {
  opacity: 1;
}

.je-input-button-text {
  display: inline-block;
}

@media (max-width: 640px) {
  .je-input-button {
    padding: 0 0.75rem;
  }
  
  .je-input-button-text {
    display: none;
  }
}

.je-line {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 36px;
  gap: 0.625rem;
  padding: 0.625rem;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  margin-bottom: 0.5rem;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.je-line:hover {
  border-color: var(--color-primary);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.je-totals {
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  padding: 1rem;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-top: 1rem;
}

.je-total {
  text-align: center;
}

.je-total-label {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.25rem;
}

.je-total-value {
  font-size: 1.25rem;
  font-weight: 700;
  font-family: monospace;
  color: var(--text-primary);
}

.je-btn {
  padding: 0.5rem 1rem;
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 500;
  border: none;
  cursor: pointer;
  transition: all 0.15s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.je-btn-primary {
  background: var(--color-primary);
  color: white;
}

.je-btn-primary:hover:not(:disabled) {
  background: #2563eb;
}

.je-btn-secondary {
  background: var(--bg-secondary);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
}

.je-btn-secondary:hover {
  background: var(--bg-primary);
}

.je-btn-ghost {
  background: transparent;
  color: var(--text-secondary);
  padding: 0.375rem;
}

.je-btn-ghost:hover {
  background: var(--bg-secondary);
  color: var(--color-danger);
}

.je-btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.8125rem;
}

.je-alert {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.75rem;
  border-radius: 6px;
  font-size: 0.8125rem;
  margin-bottom: 1rem;
}

.je-alert-danger {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
}

.je-alert-warning {
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #92400e;
}

.je-divider {
  height: 1px;
  background: var(--border-color);
  margin: 1.25rem 0;
}

.je-helper {
  font-size: 0.75rem;
  color: var(--text-secondary);
  margin-top: 0.25rem;
}

.je-sidebar {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.je-panel {
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  overflow: hidden;
}

.je-panel-header {
  padding: 1rem;
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-secondary);
}

.je-panel-title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.je-panel-body {
  padding: 1rem;
}

.je-info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.625rem 0;
  border-bottom: 1px solid var(--border-color);
}

.je-info-row:last-child {
  border-bottom: none;
}

.je-info-label {
  font-size: 0.8125rem;
  color: var(--text-secondary);
}

.je-info-value {
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--text-primary);
  font-family: monospace;
}

.je-checkbox-wrapper {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  cursor: pointer;
}

.je-checkbox-wrapper:hover {
  background: var(--bg-primary);
}

.je-checkbox-wrapper label {
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  flex: 1;
}

.je-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.125rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.6875rem;
  font-weight: 500;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  color: var(--text-secondary);
  gap: 0.25rem;
}

.je-badge-primary {
  background: rgba(59, 130, 246, 0.1);
  border-color: rgba(59, 130, 246, 0.3);
  color: var(--color-primary);
}

.je-badge-success {
  background: rgba(34, 197, 94, 0.1);
  border-color: rgba(34, 197, 94, 0.3);
  color: var(--color-success);
}

.je-badge-warning {
  background: rgba(234, 179, 8, 0.1);
  border-color: rgba(234, 179, 8, 0.3);
  color: var(--color-warning);
}

.je-badge-danger {
  background: rgba(239, 68, 68, 0.1);
  border-color: rgba(239, 68, 68, 0.3);
  color: var(--color-danger);
}

.je-file-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem;
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: 6px;
  margin-bottom: 0.5rem;
  transition: all 0.15s;
}

.je-file-item:hover {
  border-color: var(--color-primary);
  background: var(--bg-secondary);
}

.je-file-actions {
  display: flex;
  gap: 0.375rem;
  margin-left: auto;
}

.je-file-icon {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg-primary);
  border-radius: 4px;
  border: 1px solid var(--border-color);
}

.je-file-info {
  flex: 1;
  min-width: 0;
}

.je-file-name {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.je-file-size {
  font-size: 0.6875rem;
  color: var(--text-secondary);
  margin-top: 0.125rem;
}

/* Dialog Styles (Shadcn-inspired) */
.je-dialog-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  animation: fadeIn 0.2s ease-in-out;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideIn {
  from { 
    opacity: 0;
    transform: translate(-50%, -48%) scale(0.95);
  }
  to { 
    opacity: 1;
    transform: translate(-50%, -50%) scale(1);
  }
}

.je-dialog-content {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: calc(100% - 2rem);
  max-width: 680px;
  max-height: calc(100vh - 4rem);
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: 12px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  display: flex;
  flex-direction: column;
  animation: slideIn 0.2s ease-out;
}

.je-dialog-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  flex-shrink: 0;
}

.je-dialog-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.je-dialog-close {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.15s;
}

.je-dialog-close:hover {
  background: var(--bg-secondary);
  color: var(--text-primary);
}

.je-dialog-search {
  position: relative;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  flex-shrink: 0;
}

.je-search-icon {
  position: absolute;
  left: 2rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-secondary);
  pointer-events: none;
}

.je-search-input {
  width: 100%;
  padding: 0.625rem 0.75rem 0.625rem 2.5rem;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  font-size: 0.875rem;
  background: var(--bg-primary);
  color: var(--text-primary);
  transition: border-color 0.15s, box-shadow 0.15s;
}

.je-search-input:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.je-search-input::placeholder {
  color: var(--text-secondary);
}

.je-dialog-body {
  overflow-y: auto;
  flex: 1;
  min-height: 0;
}

.je-template-item {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  cursor: pointer;
  transition: background-color 0.15s;
}

.je-template-item:last-child {
  border-bottom: none;
}

.je-template-item:hover {
  background: var(--bg-secondary);
}

.je-template-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.je-template-number {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-primary);
  font-family: monospace;
}

.je-template-date {
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.je-template-desc {
  font-size: 0.8125rem;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
  line-height: 1.4;
}

.je-template-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.je-template-type {
  font-size: 0.6875rem;
  padding: 0.125rem 0.5rem;
  border-radius: 9999px;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  color: var(--text-secondary);
  text-transform: capitalize;
}

.je-template-lines {
  font-size: 0.6875rem;
  color: var(--text-secondary);
}

.je-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1.5rem;
  color: var(--text-secondary);
}

.je-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--border-color);
  border-top-color: var(--color-primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.je-empty-templates {
  padding: 3rem 1.5rem;
  text-align: center;
  color: var(--text-secondary);
}

/* Tags Input System */
.je-tags-container {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
  padding: 0.5rem;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  min-height: 42px;
  background: var(--bg-primary);
  cursor: text;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.je-tags-container:focus-within {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.je-tag-box {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.25rem 0.5rem;
  background: rgba(59, 130, 246, 0.1);
  border: 1px solid rgba(59, 130, 246, 0.3);
  border-radius: 4px;
  font-size: 0.8125rem;
  color: var(--color-primary);
  font-weight: 500;
}

.je-tag-remove {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 14px;
  height: 14px;
  border: none;
  background: transparent;
  color: var(--color-primary);
  cursor: pointer;
  padding: 0;
  transition: color 0.15s;
}

.je-tag-remove:hover {
  color: var(--color-danger);
}

.je-tags-input {
  flex: 1;
  min-width: 120px;
  border: none;
  outline: none;
  background: transparent;
  font-size: 0.875rem;
  padding: 0.25rem;
  color: var(--text-primary);
}

.je-tags-input::placeholder {
  color: var(--text-secondary);
}

/* Entry Options Dialog */
.je-options-dialog {
  max-width: 420px !important;
}

.je-option-item {
  padding: 1rem;
  border: 1px solid var(--border-color);
  border-radius: 6px;
  margin-bottom: 0.75rem;
  transition: all 0.15s;
}

.je-option-item:hover {
  border-color: var(--color-primary);
  background: rgba(59, 130, 246, 0.02);
}

.je-option-checkbox {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  cursor: pointer;
}

.je-option-checkbox input[type="checkbox"] {
  width: 18px;
  height: 18px;
  margin-top: 2px;
  cursor: pointer;
  flex-shrink: 0;
}

.je-option-content {
  flex: 1;
}

.je-option-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-primary);
  display: block;
  margin-bottom: 0.25rem;
}

.je-option-desc {
  font-size: 0.75rem;
  color: var(--text-secondary);
  line-height: 1.4;
}

.je-recurring-select {
  margin-top: 0.75rem;
  width: 100%;
}

/* Audit Trail Styles (Shadcn-inspired) */
.je-audit-section {
  padding: 0.75rem 0;
}

.je-audit-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.025em;
  margin-bottom: 0.5rem;
}

.je-audit-value {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.je-audit-pending {
  font-style: italic;
  color: var(--text-secondary);
  font-weight: 400;
}

.je-audit-timestamp {
  font-size: 0.75rem;
  color: var(--text-secondary);
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.je-audit-timestamp::before {
  content: '•';
  opacity: 0.5;
}

.je-audit-separator {
  height: 1px;
  background: var(--border-color);
  margin: 0.75rem 0;
}

.je-audit-info {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  padding: 0.625rem;
  background: rgba(59, 130, 246, 0.05);
  border: 1px solid rgba(59, 130, 246, 0.1);
  border-radius: 6px;
  font-size: 0.75rem;
  color: var(--text-secondary);
  line-height: 1.4;
  margin-top: 0.5rem;
}

.je-audit-history-item {
  display: flex;
  gap: 0.75rem;
  padding: 0.75rem;
  border-left: 2px solid var(--border-color);
  margin-bottom: 0.5rem;
  transition: border-color 0.15s;
}

.je-audit-history-item:hover {
  border-left-color: var(--color-primary);
  background: var(--bg-secondary);
}

.je-audit-icon {
  flex-shrink: 0;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 50%;
  color: var(--text-secondary);
}

.je-audit-content {
  flex: 1;
  min-width: 0;
}

.je-audit-action {
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.je-audit-meta {
  font-size: 0.6875rem;
  color: var(--text-secondary);
}

/* Calendar Badge (Shadcn-inspired) */
.je-audit-btn {
  position: relative;
  padding-left: 2.5rem !important;
}

.je-calendar-badge {
  position: absolute;
  left: 0.5rem;
  width: 28px;
  height: 28px;
  background: white;
  border: 1.5px solid var(--color-primary);
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.je-calendar-number {
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--color-primary);
  line-height: 1;
}

/* Audit Panel (Above Button, Shadcn-inspired) */
.je-audit-panel {
  position: absolute;
  bottom: calc(100% + 0.75rem);
  right: 0;
  width: 340px;
  background: white;
  border: 1px solid var(--border-color);
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.05);
  z-index: 1000;
  animation: panel-slide-down 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes panel-slide-down {
  from {
    opacity: 0;
    transform: translateY(10px) scale(0.96);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.je-audit-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--border-color);
  background: var(--bg-primary);
  border-radius: 12px 12px 0 0;
}

.je-audit-panel-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.je-audit-panel-close {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: none;
  border-radius: 6px;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.15s;
  padding: 0;
}

.je-audit-panel-close:hover {
  background: var(--bg-secondary);
  color: var(--text-primary);
}

.je-audit-panel-body {
  padding: 1.25rem;
}

/* Arrow pointing to button */
.je-audit-panel::after {
  content: '';
  position: absolute;
  bottom: -8px;
  right: 2rem;
  width: 16px;
  height: 16px;
  background: white;
  border-right: 1px solid var(--border-color);
  border-bottom: 1px solid var(--border-color);
  transform: rotate(45deg);
  z-index: -1;
}

/* Responsive: Full width on mobile */
@media (max-width: 640px) {
  .je-audit-panel {
    position: fixed;
    bottom: 0;
    right: 0;
    left: 0;
    width: 100%;
    border-radius: 12px 12px 0 0;
    max-height: 80vh;
    overflow-y: auto;
  }
  
  .je-audit-panel::after {
    display: none;
  }
}

/* Enhanced interactions */
.je-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.je-input:disabled,
.je-select:disabled {
  background: var(--bg-secondary);
  cursor: not-allowed;
  opacity: 0.6;
}

/* Empty state for lines */
.je-empty-state {
  text-align: center;
  padding: 2rem;
  color: var(--text-secondary);
  border: 2px dashed var(--border-color);
  border-radius: 8px;
  margin-bottom: 1rem;
}

/* Focus ring for accessibility */
.je-input:focus-visible,
.je-select:focus-visible,
.je-btn:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}

/* Smooth transitions */
* {
  transition-property: background-color, border-color, color, box-shadow;
  transition-duration: 150ms;
  transition-timing-function: ease-in-out;
}

@media (max-width: 968px) {
  .je-layout {
    grid-template-columns: 1fr;
    padding: 0 0.5rem;
  }
  
  .je-sidebar {
    order: -1;
  }
  
  .je-grid-3 {
    grid-template-columns: 1fr;
  }
  
  .je-line {
    grid-template-columns: 1fr;
  }
  
  .je-totals {
    grid-template-columns: 1fr;
  }
  
  .je-dialog-content {
    max-width: calc(100% - 1rem);
    max-height: calc(100vh - 2rem);
  }
  
  .je-dialog-header,
  .je-dialog-search,
  .je-template-item {
    padding: 1rem;
  }
}

@media (max-width: 640px) {
  .je-layout {
    padding: 0 0.25rem;
  }
  
  .je-form-header {
    flex-direction: column;
    align-items: flex-start !important;
    gap: 0.75rem;
  }
  
  .je-form-header > div {
    width: 100%;
    flex-direction: column;
  }
  
  .je-form-header button {
    width: 100%;
  }
  
  .je-dialog-content {
    max-width: 100%;
    border-radius: 0;
    max-height: 100vh;
  }
  
  .je-option-checkbox {
    align-items: flex-start;
  }
}

/* Better select styling */
.je-select {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236B7280' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.75rem center;
  padding-right: 2.5rem;
}

/* Checkbox styling */
input[type="checkbox"] {
  width: 1.125rem;
  height: 1.125rem;
  cursor: pointer;
  accent-color: var(--color-primary);
}
</style>

<!-- Page Header -->
<div class="content-header">
  <div>
    <nav class="breadcrumb">
      <a href="dashboard.php" class="breadcrumb-link">Dashboard</a>
      <span class="breadcrumb-separator">/</span>
      <a href="#" class="breadcrumb-link">Accounting</a>
      <span class="breadcrumb-separator">/</span>
      <a href="journal-entries.php" class="breadcrumb-link">Journal Entries</a>
      <span class="breadcrumb-separator">/</span>
      <span class="breadcrumb-current">New Entry</span>
    </nav>
    <h1 class="content-title">
      <?php if ($editMode): ?>
      <span style="display: inline-flex; align-items: center; gap: 0.5rem;">
        Edit Journal Entry
        <span class="badge badge-primary" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">Edit Mode</span>
      </span>
      <?php elseif ($copyMode): ?>
      <span style="display: inline-flex; align-items: center; gap: 0.5rem;">
        Duplicate Journal Entry
        <span class="badge badge-warning" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">Copy Mode</span>
      </span>
      <?php else: ?>
      New Journal Entry
      <?php endif; ?>
    </h1>
  </div>
</div>

<?php if ($editMode && $editEntry): ?>
<div class="alert alert-info" style="margin-bottom: 1.5rem; display: flex; align-items: flex-start; gap: 0.75rem;">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="flex-shrink: 0; margin-top: 0.125rem;">
    <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
  </svg>
  <div>
    <strong>Editing Entry: <?php echo htmlspecialchars($editEntry['entry_number']); ?></strong>
    <p style="margin: 0.25rem 0 0 0; font-size: 0.875rem;">
      <?php 
        $status = ucfirst($editEntry['status']);
        $statusColor = $editEntry['status'] === 'draft' ? 'var(--color-warning)' : ($editEntry['status'] === 'posted' ? 'var(--color-success)' : 'var(--color-danger)');
      ?>
      Current Status: <span style="font-weight: 600; color: <?php echo $statusColor; ?>;"><?php echo $status; ?></span> • 
      Modify the details below and save to update this entry.
      <?php if ($editEntry['status'] === 'posted'): ?>
      <strong style="color: var(--color-danger);">Note:</strong> This entry is already posted. Changes may affect account balances.
      <?php endif; ?>
    </p>
  </div>
</div>
<?php elseif ($copyMode && $sourceEntry): ?>
<div class="alert alert-info" style="margin-bottom: 1.5rem; display: flex; align-items: flex-start; gap: 0.75rem;">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="flex-shrink: 0; margin-top: 0.125rem;">
    <path d="M8 16H6C4.89543 16 4 15.1046 4 14V6C4 4.89543 4.89543 4 6 4H14C15.1046 4 16 4.89543 16 6V8M10 12H18C19.1046 12 20 12.8954 20 14V18C20 19.1046 19.1046 20 18 20H10C8.89543 20 8 19.1046 8 18V14C8 12.8954 8.89543 12 10 12Z" stroke="currentColor" stroke-width="2"/>
  </svg>
  <div>
    <strong>Duplicating Entry: <?php echo htmlspecialchars($sourceEntry['entry_number']); ?></strong>
    <p style="margin: 0.25rem 0 0 0; font-size: 0.875rem;">
      All transaction lines, amounts, and details have been copied. Review and modify as needed before saving.
      A new entry number will be assigned when you save.
    </p>
  </div>
</div>
<?php endif; ?>

<div class="je-layout">
  <div class="je-main-form">
    <div class="je-form-header" style="display: flex; align-items: center; justify-content: space-between;">
      <h2 class="je-form-title">Journal Entry Details</h2>
      <div style="display: flex; gap: 0.5rem;">
        <button type="button" class="je-btn je-btn-secondary je-btn-sm" onclick="openOptionsDialog()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
            <path d="M12 15a3 3 0 100-6 3 3 0 000 6z" stroke="currentColor" stroke-width="2"/>
            <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z" stroke="currentColor" stroke-width="2"/>
          </svg>
          Entry Options
        </button>
        <button type="button" class="je-btn je-btn-secondary je-btn-sm" onclick="openTemplateDialog()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
            <path d="M9 2v4m6-4v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Use Template
        </button>
      </div>
    </div>
    <div class="je-form-body">
      <?php if ($error): ?>
      <div class="je-alert je-alert-danger">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" id="entry-form" enctype="multipart/form-data">
        <?php if ($editMode && $editEntry): ?>
        <!-- Hidden field for edit mode -->
        <input type="hidden" name="entry_id" value="<?php echo $editEntry['_id']; ?>">
        <?php endif; ?>
        
        <div class="je-section">
          <h3 class="je-section-title">Basic Information</h3>
          <div class="je-grid je-grid-3">
            <div class="je-field">
              <label for="entry_date" class="je-label">
                Entry Date <span style="color: var(--color-danger);">*</span>
              </label>
              <input 
                type="date" 
                id="entry_date" 
                name="entry_date" 
                class="je-input" 
                value="<?php 
                  if ($editMode && $editEntry) {
                    echo $editEntry['entry_date']->toDateTime()->format('Y-m-d');
                  } elseif ($copyMode && $sourceEntry) {
                    echo $sourceEntry['entry_date']->toDateTime()->format('Y-m-d');
                  } else {
                    echo date('Y-m-d');
                  }
                ?>"
                required
              >
            </div>

            <div class="je-field">
              <label for="entry_type" class="je-label">
                Entry Type
              </label>
              <select id="entry_type" name="entry_type" class="je-select">
                <?php 
                  $selectedType = 'general';
                  if ($editMode && $editEntry) {
                    $selectedType = $editEntry['entry_type'];
                  } elseif ($copyMode && $sourceEntry) {
                    $selectedType = $sourceEntry['entry_type'];
                  }
                ?>
                <option value="general" <?php echo $selectedType === 'general' ? 'selected' : ''; ?>>General Journal</option>
                <option value="sales" <?php echo $selectedType === 'sales' ? 'selected' : ''; ?>>Sales Transaction</option>
                <option value="purchase" <?php echo $selectedType === 'purchase' ? 'selected' : ''; ?>>Purchase Transaction</option>
                <option value="payment" <?php echo $selectedType === 'payment' ? 'selected' : ''; ?>>Payment Voucher</option>
                <option value="receipt" <?php echo $selectedType === 'receipt' ? 'selected' : ''; ?>>Receipt Voucher</option>
                <option value="adjustment" <?php echo $selectedType === 'adjustment' ? 'selected' : ''; ?>>Adjusting Entry</option>
              </select>
            </div>

            <div class="je-field">
              <label for="reference" class="je-label">
                Reference Number
              </label>
              <div class="je-input-group">
                <input 
                  type="text" 
                  id="reference" 
                  name="reference" 
                  class="je-input je-input-with-button" 
                  placeholder="Invoice #, Check #, etc."
                  value="<?php 
                    if ($editMode && $editEntry) {
                      echo htmlspecialchars($editEntry['reference'] ?? '');
                    } elseif ($copyMode && $sourceEntry) {
                      echo htmlspecialchars($sourceEntry['reference'] ?? '');
                    }
                  ?>"
                >
                <button 
                  type="button" 
                  class="je-input-button" 
                  onclick="generateReferenceNumber()"
                  title="Generate unique reference number"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="7.5 4.21 12 6.81 16.5 4.21"></polyline>
                    <polyline points="7.5 19.79 7.5 14.6 3 12"></polyline>
                    <polyline points="21 12 16.5 14.6 16.5 19.79"></polyline>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                  </svg>
                  <span class="je-input-button-text">Generate</span>
                </button>
              </div>
              <span class="je-helper">Auto-generate a unique reference ID</span>
            </div>
          </div>
        </div>

        <div class="je-section">
          <div class="je-field">
            <label for="description" class="je-label">
              Memo/Description <span style="color: var(--color-danger);">*</span>
            </label>
            <textarea 
              id="description" 
              name="description" 
              class="je-textarea" 
              placeholder="Enter a brief description or memo for this transaction"
              required
            ><?php 
              if ($editMode && $editEntry) {
                echo htmlspecialchars($editEntry['description'] ?? '');
              } elseif ($copyMode && $sourceEntry) {
                echo htmlspecialchars($sourceEntry['description'] ?? '');
              }
            ?></textarea>
          </div>
        </div>

        <div class="je-section">
          <div class="je-field">
            <label for="tags-input" class="je-label">
              Tags
            </label>
            <div class="je-tags-container" id="tags-container" onclick="document.getElementById('tags-input').focus()">
              <input 
                type="text" 
                id="tags-input" 
                class="je-tags-input" 
                placeholder="Type and press Enter to add tags..."
                onkeydown="handleTagInput(event)"
              >
            </div>
            <input type="hidden" name="tags" id="tags-hidden">
            <span class="je-helper">Press Enter to add each tag</span>
          </div>
        </div>

        <div class="je-divider"></div>

        <div class="je-section">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; position: relative;">
            <h3 class="je-section-title" style="margin: 0;">Transaction Lines</h3>
            <div style="display: flex; gap: 0.5rem; position: relative;">
              <button type="button" class="je-btn je-btn-secondary je-btn-sm je-audit-btn" onclick="toggleAuditPopover()" id="audit-trail-trigger">
                <div class="je-calendar-badge">
                  <span class="je-calendar-number" id="audit-line-count">0</span>
                </div>
                <span>Audit Trail</span>
              </button>
              
              <!-- Audit Trail Panel (Above Button) -->
              <div id="audit-panel" class="je-audit-panel" style="display: none;">
                <div class="je-audit-panel-header">
                  <h4 class="je-audit-panel-title">Audit Trail</h4>
                  <button type="button" class="je-audit-panel-close" onclick="toggleAuditPopover()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                      <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                  </button>
                </div>
                
                <div class="je-audit-panel-body">
                  <div class="je-audit-section">
                    <div class="je-audit-label">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="opacity: 0.6;">
                        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                      </svg>
                      CREATED BY
                    </div>
                    <div class="je-audit-value" id="audit-created-by-popup">
                      <?php echo htmlspecialchars($_SESSION['username'] ?? 'admin'); ?>
                    </div>
                    <div class="je-audit-timestamp" id="audit-created-at-popup">
                      <?php echo date('M d, Y \a\t g:i A'); ?>
                    </div>
                  </div>

                  <div class="je-audit-separator"></div>

                  <div class="je-audit-section">
                    <div class="je-audit-label">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="opacity: 0.6;">
                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                      </svg>
                      LAST MODIFIED BY
                    </div>
                    <div class="je-audit-value je-audit-pending" id="audit-modified-by-popup">
                      Not yet saved
                    </div>
                    <div class="je-audit-timestamp" id="audit-modified-at-popup" style="display: none;">
                    </div>
                  </div>

                  <div class="je-audit-separator"></div>

                  <div class="je-audit-info">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="flex-shrink: 0; opacity: 0.5;">
                      <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                      <path d="M12 16v-4M12 8h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span>Changes will be tracked after first save</span>
                  </div>
                </div>
              </div>
              
              <button type="button" class="je-btn je-btn-secondary je-btn-sm" onclick="addLine()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                  <path d="M12 5V19M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                Add Line
              </button>
            </div>
          </div>

          <div id="entry-lines">
            <!-- Lines will be added dynamically -->
          </div>

          <div class="je-totals">
            <div class="je-total">
              <div class="je-total-label">Total Debit</div>
              <div id="total-debit" class="je-total-value"><?php echo htmlspecialchars($currencySymbol); ?>0.00</div>
            </div>
            <div class="je-total">
              <div class="je-total-label">Total Credit</div>
              <div id="total-credit" class="je-total-value"><?php echo htmlspecialchars($currencySymbol); ?>0.00</div>
            </div>
          </div>

          <div id="balance-warning" class="je-alert je-alert-warning" style="display: none; margin-top: 0.75rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path d="M12 9V13M12 17H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
            </svg>
            <span>⚠️ Debits must equal credits! Entry is unbalanced.</span>
          </div>
        </div>

        <input type="hidden" name="line_count" id="line_count" value="0">
      </form>
    </div>
  </div>

  <aside class="je-sidebar">
    <div class="je-panel">
      <div class="je-panel-header">
        <h3 class="je-panel-title">Summary</h3>
      </div>
      <div class="je-panel-body">
        <div class="je-info-row">
          <span class="je-info-label">Entry Date</span>
          <span class="je-info-value" id="summary-date"><?php echo date('Y-m-d'); ?></span>
        </div>
        <div class="je-info-row">
          <span class="je-info-label">Entry Type</span>
          <span class="je-info-value" id="summary-type">General</span>
        </div>
        <div class="je-info-row">
          <span class="je-info-label">Reference</span>
          <span class="je-info-value" id="summary-ref">—</span>
        </div>
        <div class="je-info-row">
          <span class="je-info-label">Lines Count</span>
          <span class="je-info-value" id="summary-lines">0</span>
        </div>
        <div class="je-info-row">
          <span class="je-info-label">Status</span>
          <span class="je-badge je-badge-warning" id="summary-status">Unbalanced</span>
        </div>
      </div>
    </div>

    <div class="je-panel">
      <div class="je-panel-header">
        <h3 class="je-panel-title">Attachments</h3>
      </div>
      <div class="je-panel-body">
        <input type="file" name="attachments[]" id="file-input" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" style="display: none;" form="entry-form" onchange="handleFileSelect(this)">
        <button type="button" class="je-btn je-btn-secondary" style="width: 100%; justify-content: center;" onclick="document.getElementById('file-input').click()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Select Files
        </button>
        <p class="je-helper" style="margin-top: 0.5rem; text-align: center;">PDF, Word, Excel, Images (Max 10MB each)</p>
        <div id="file-list" style="margin-top: 1rem;"></div>
      </div>
    </div>

    <div class="je-panel">
      <div class="je-panel-header">
        <h3 class="je-panel-title">Actions</h3>
      </div>
      <div class="je-panel-body">
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
          <?php if ($editMode): ?>
          <!-- Edit Mode Buttons -->
          <button type="submit" form="entry-form" class="je-btn je-btn-primary" id="submit-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Save Changes
          </button>
          <button type="button" class="je-btn je-btn-danger" onclick="confirmDeleteEntry('<?php echo $editEntry['_id']; ?>')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2M10 11v6M14 11v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Delete Entry
          </button>
          <a href="journal-entries.php" class="je-btn je-btn-secondary" style="text-decoration: none;">Cancel</a>
          <?php else: ?>
          <!-- Create Mode Buttons -->
          <button type="submit" form="entry-form" class="je-btn je-btn-primary" id="submit-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Create Entry
          </button>
          <a href="journal-entries.php" class="je-btn je-btn-secondary" style="text-decoration: none;">Cancel</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </aside>
</div>

<!-- Entry Options Dialog -->
<div id="options-dialog" class="je-dialog-overlay" style="display: none;">
  <div class="je-dialog-content je-options-dialog">
    <div class="je-dialog-header">
      <h3 class="je-dialog-title">Entry Options</h3>
      <button type="button" class="je-dialog-close" onclick="closeOptionsDialog()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </button>
    </div>
    
    <div class="je-dialog-body" style="padding: 1.5rem;">
      <div class="je-option-item">
        <label class="je-option-checkbox">
          <input type="checkbox" name="auto_post" id="auto_post" form="entry-form">
          <div class="je-option-content">
            <span class="je-option-label">Auto-post to ledger</span>
            <span class="je-option-desc">Entry will be saved as draft if unchecked</span>
          </div>
        </label>
      </div>

      <div class="je-option-item">
        <label class="je-option-checkbox">
          <input type="checkbox" name="requires_approval" id="requires_approval" form="entry-form">
          <div class="je-option-content">
            <span class="je-option-label">Require approval</span>
            <span class="je-option-desc">Entry needs manager approval before posting</span>
          </div>
        </label>
      </div>

      <div class="je-option-item">
        <label class="je-option-checkbox">
          <input type="checkbox" name="is_recurring" id="is_recurring" form="entry-form" onchange="toggleRecurringSelect()">
          <div class="je-option-content">
            <span class="je-option-label">Recurring entry</span>
          </div>
        </label>
        <div id="recurring-select-wrapper" style="display: none; margin-top: 0.75rem; padding-left: 2rem;">
          <select name="recurring_frequency" class="je-select je-recurring-select" form="entry-form">
            <option value="daily">Daily</option>
            <option value="weekly">Weekly</option>
            <option value="monthly" selected>Monthly</option>
            <option value="quarterly">Quarterly</option>
            <option value="yearly">Yearly</option>
          </select>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- File Preview Dialog -->
<div id="file-preview-dialog" class="je-dialog-overlay" style="display: none;">
  <div class="je-dialog-content" style="max-width: 900px; max-height: 90vh;">
    <div class="je-dialog-header">
      <h3 class="je-dialog-title" id="preview-file-name">File Preview</h3>
      <button type="button" class="je-dialog-close" onclick="closeFilePreview()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </button>
    </div>
    
    <div class="je-dialog-body" style="padding: 0; overflow: auto;" id="file-preview-container">
      <div style="display: flex; align-items: center; justify-content: center; min-height: 400px; color: var(--text-secondary);">
        <p>Loading preview...</p>
      </div>
    </div>
  </div>
</div>

<!-- Template Selection Dialog -->
<div id="template-dialog" class="je-dialog-overlay" style="display: none;">
  <div class="je-dialog-content">
    <div class="je-dialog-header">
      <h3 class="je-dialog-title">Select Entry Template</h3>
      <button type="button" class="je-dialog-close" onclick="closeTemplateDialog()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </button>
    </div>
    
    <div class="je-dialog-search">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="je-search-icon">
        <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
        <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <input 
        type="text" 
        id="template-search" 
        class="je-search-input" 
        placeholder="Search by entry number, description, or type..."
        oninput="filterTemplates(this.value)"
      >
    </div>
    
    <div class="je-dialog-body" id="template-list">
      <div class="je-loading">
        <div class="je-spinner"></div>
        <p>Loading templates...</p>
      </div>
    </div>
  </div>
</div>

<script>
let lineCounter = 0;
const accounts = <?php echo json_encode($accounts); ?>;
const currencySymbol = <?php echo json_encode($currencySymbol); ?>;
let allTemplates = [];
let filteredTemplates = [];

document.addEventListener('DOMContentLoaded', function() {
  // Check if this is a new entry - clear all stored data
  const urlParams = new URLSearchParams(window.location.search);
  const isNewEntry = urlParams.has('new');
  const isEditMode = urlParams.has('edit');
  const isCopyMode = urlParams.has('copy');
  
  if (isNewEntry) {
    // Clear all localStorage data for fresh start
    clearFormDataFromStorage();
    clearStoredFiles();
    console.log('🆕 New Entry - Cleared all stored data');
  }
  
  // Check if we're in edit mode - populate from PHP entry data
  if (isEditMode) {
    console.log('✏️ Edit Mode - Loading entry data');
    <?php if ($editMode && $editEntry): ?>
    // Populate tags from entry
    const entryTags = <?php echo json_encode($editEntry['tags'] ?? []); ?>;
    entryTags.forEach(tag => {
      addTag(tag);
    });
    
    // Populate transaction lines from entry
    const entryLines = <?php echo json_encode($editEntry['lines'] ?? []); ?>;
    entryLines.forEach(line => {
      addLine();
      const lastLine = document.querySelectorAll('.entry-line');
      const currentLine = lastLine[lastLine.length - 1];
      
      // Find account select and set value
      const accountSelect = currentLine.querySelector('[name^="line_"][name$="_account"]');
      if (accountSelect) {
        accountSelect.value = line.account_code || '';
        accountSelect.dispatchEvent(new Event('change'));
      }
      
      // Set debit value
      const debitInput = currentLine.querySelector('[name^="line_"][name$="_debit"]');
      if (debitInput && line.debit > 0) {
        debitInput.value = line.debit.toFixed(2);
        debitInput.dispatchEvent(new Event('input'));
      }
      
      // Set credit value
      const creditInput = currentLine.querySelector('[name^="line_"][name$="_credit"]');
      if (creditInput && line.credit > 0) {
        creditInput.value = line.credit.toFixed(2);
        creditInput.dispatchEvent(new Event('input'));
      }
      
      // Set description
      const descInput = currentLine.querySelector('[name^="line_"][name$="_description"]');
      if (descInput && line.description) {
        descInput.value = line.description;
      }
    });
    
    // Load attachments from entry
    const entryAttachments = <?php echo json_encode($editEntry['attachments'] ?? []); ?>;
    if (entryAttachments && entryAttachments.length > 0) {
      // Clear any stored files first
      attachedFiles = [];
      
      // Add each attachment to the list
      entryAttachments.forEach(attachment => {
        attachedFiles.push({
          name: attachment.filename,
          size: attachment.size || 0,
          type: attachment.mimetype || 'application/octet-stream',
          file_id: attachment.file_id,
          isExisting: true // Mark as existing file from database
        });
      });
      
      renderFileList();
      console.log('✅ Loaded ' + entryAttachments.length + ' attachments');
    }
    
    // Update summary after populating
    updateSummary();
    calculateTotals();
    
    console.log('✅ Loaded ' + entryLines.length + ' lines and ' + entryTags.length + ' tags for editing');
    <?php endif; ?>
  }
  // Check if we're in copy mode - populate from PHP source data
  else if (isCopyMode) {
    console.log('📋 Copy Mode - Loading source entry data');
    <?php if ($copyMode && $sourceEntry): ?>
    // Populate tags from source entry
    const sourceTags = <?php echo json_encode($sourceEntry['tags'] ?? []); ?>;
    sourceTags.forEach(tag => {
      addTag(tag);
    });
    
    // Populate transaction lines from source entry
    const sourceLines = <?php echo json_encode($sourceEntry['lines'] ?? []); ?>;
    sourceLines.forEach(line => {
      addLine();
      const lastLine = document.querySelectorAll('.entry-line');
      const currentLine = lastLine[lastLine.length - 1];
      
      // Find account select and set value
      const accountSelect = currentLine.querySelector('[name^="line_"][name$="_account"]');
      if (accountSelect) {
        accountSelect.value = line.account_code || '';
        accountSelect.dispatchEvent(new Event('change'));
      }
      
      // Set debit value
      const debitInput = currentLine.querySelector('[name^="line_"][name$="_debit"]');
      if (debitInput && line.debit > 0) {
        debitInput.value = line.debit.toFixed(2);
        debitInput.dispatchEvent(new Event('input'));
      }
      
      // Set credit value
      const creditInput = currentLine.querySelector('[name^="line_"][name$="_credit"]');
      if (creditInput && line.credit > 0) {
        creditInput.value = line.credit.toFixed(2);
        creditInput.dispatchEvent(new Event('input'));
      }
      
      // Set description
      const descInput = currentLine.querySelector('[name^="line_"][name$="_description"]');
      if (descInput && line.description) {
        descInput.value = line.description;
      }
    });
    
    // Update summary after populating
    updateSummary();
    calculateTotals();
    
    console.log('✅ Copied ' + sourceLines.length + ' lines and ' + sourceTags.length + ' tags');
    <?php endif; ?>
  } else {
    // Load all stored data first (will be empty if new entry)
    loadFormDataFromStorage();
    
    // Add default lines only if no lines were loaded
    if (document.querySelectorAll('.entry-line').length === 0) {
      addLine();
      addLine();
    }
  }
  
  // Load stored files (will be empty if new entry)
  loadFilesFromStorage();
  
  // Update summary on field changes
  document.getElementById('entry_date').addEventListener('change', function() {
    updateSummary();
    saveFormDataToStorage();
  });
  document.getElementById('entry_type').addEventListener('change', function() {
    updateSummary();
    saveFormDataToStorage();
  });
  document.getElementById('reference').addEventListener('input', function() {
    updateSummary();
    saveFormDataToStorage();
  });
  document.getElementById('description').addEventListener('input', saveFormDataToStorage);
  
  updateSummary();
  
  // Close dialogs on escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeTemplateDialog();
      closeOptionsDialog();
      closeFilePreview();
    }
  });
  
  // Close dialogs on overlay click
  document.getElementById('template-dialog').addEventListener('click', function(e) {
    if (e.target === this) {
      closeTemplateDialog();
    }
  });
  
  document.getElementById('options-dialog').addEventListener('click', function(e) {
    if (e.target === this) {
      closeOptionsDialog();
    }
  });
  
  document.getElementById('file-preview-dialog').addEventListener('click', function(e) {
    if (e.target === this) {
      closeFilePreview();
    }
  });
  
  // Clear all stored data on successful form submission
  document.getElementById('entry-form').addEventListener('submit', function() {
    // Clear after a short delay to allow form submission
    setTimeout(function() {
      clearStoredFiles();
      clearFormDataFromStorage();
    }, 1000);
  });
});

// Generate Unique Reference Number
function generateReferenceNumber() {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  
  // Generate random alphanumeric string (6 characters)
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let randomStr = '';
  for (let i = 0; i < 6; i++) {
    randomStr += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  
  // Format: REF-YYYYMMDD-XXXXXX
  const refNumber = `REF-${year}${month}${day}-${randomStr}`;
  
  // Set the value with animation
  const input = document.getElementById('reference');
  input.value = refNumber;
  
  // Add visual feedback
  input.style.transition = 'all 0.3s ease';
  input.style.background = 'rgba(59, 130, 246, 0.1)';
  input.style.borderColor = 'var(--color-primary)';
  
  // Trigger events for form state
  input.dispatchEvent(new Event('input', { bubbles: true }));
  updateSummary();
  saveFormDataToStorage();
  
  // Reset style after animation
  setTimeout(() => {
    input.style.background = '';
    input.style.borderColor = '';
  }, 1000);
  
  // Show success toast (using unified Toast API)
  Toast.success('Reference number generated!');
}

function updateSummary() {
  const date = document.getElementById('entry_date').value;
  const type = document.getElementById('entry_type').selectedOptions[0].text;
  const ref = document.getElementById('reference').value || '—';
  
  document.getElementById('summary-date').textContent = date || '—';
  document.getElementById('summary-type').textContent = type;
  document.getElementById('summary-ref').textContent = ref;
}

// Entry Options Dialog Functions
function openOptionsDialog() {
  const dialog = document.getElementById('options-dialog');
  dialog.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeOptionsDialog() {
  const dialog = document.getElementById('options-dialog');
  dialog.style.display = 'none';
  document.body.style.overflow = '';
}

// Delete Entry Confirmation
function confirmDeleteEntry(entryId) {
  if (confirm('⚠️ DELETE THIS JOURNAL ENTRY?\n\nThis action cannot be undone!\n\nAre you sure you want to permanently delete this entry?')) {
    // Show loading state on button
    const deleteBtn = event.target.closest('button');
    const originalText = deleteBtn.innerHTML;
    deleteBtn.disabled = true;
    deleteBtn.innerHTML = `
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="animation: spin 1s linear infinite;">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-opacity="0.25"/>
        <path d="M12 2a10 10 0 0110 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      Deleting...
    `;
    
    // Create form to submit delete request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'journal-entries.php';
    form.innerHTML = `
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="entry_id" value="${entryId}">
    `;
    document.body.appendChild(form);
    form.submit();
  }
}

function toggleRecurringSelect() {
  const checkbox = document.getElementById('is_recurring');
  const wrapper = document.getElementById('recurring-select-wrapper');
  
  if (checkbox.checked) {
    wrapper.style.display = 'block';
  } else {
    wrapper.style.display = 'none';
  }
}

// Tags Management System
let tagsArray = [];

function handleTagInput(event) {
  const input = event.target;
  const value = input.value.trim();
  
  if (event.key === 'Enter' && value) {
    event.preventDefault();
    addTag(value);
    input.value = '';
  } else if (event.key === 'Backspace' && !value && tagsArray.length > 0) {
    removeTag(tagsArray.length - 1);
  }
}

function addTag(tagText) {
  // Prevent duplicates
  if (tagsArray.includes(tagText)) {
    return;
  }
  
  tagsArray.push(tagText);
  renderTags();
  updateTagsHidden();
  saveFormDataToStorage();
}

function removeTag(index) {
  tagsArray.splice(index, 1);
  renderTags();
  updateTagsHidden();
  saveFormDataToStorage();
}

function renderTags() {
  const container = document.getElementById('tags-container');
  const input = document.getElementById('tags-input');
  
  // Clear existing tags (except input)
  const existingTags = container.querySelectorAll('.je-tag-box');
  existingTags.forEach(tag => tag.remove());
  
  // Add tag boxes before input
  tagsArray.forEach((tag, index) => {
    const tagBox = document.createElement('span');
    tagBox.className = 'je-tag-box';
    tagBox.innerHTML = `
      ${tag}
      <button type="button" class="je-tag-remove" onclick="removeTag(${index})" title="Remove tag">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
          <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </button>
    `;
    container.insertBefore(tagBox, input);
  });
}

function updateTagsHidden() {
  document.getElementById('tags-hidden').value = tagsArray.join(',');
}

function setTags(tags) {
  tagsArray = tags.filter(tag => tag.trim());
  renderTags();
  updateTagsHidden();
}

// File Management with localStorage
let attachedFiles = [];

function handleFileSelect(input) {
  if (input.files.length > 0) {
    for (let i = 0; i < input.files.length; i++) {
      const file = input.files[i];
      addFileToList(file);
    }
    saveFilesToStorage();
  }
}

function addFileToList(file) {
  // Check if file already exists
  if (attachedFiles.some(f => f.name === file.name && f.size === file.size)) {
    return;
  }
  
  // Store file with data URL for persistence
  const reader = new FileReader();
  reader.onload = function(e) {
    const fileData = {
      name: file.name,
      size: file.size,
      type: file.type,
      dataUrl: e.target.result,
      timestamp: Date.now()
    };
    
    attachedFiles.push(fileData);
    renderFileList();
    saveFilesToStorage();
  };
  reader.readAsDataURL(file);
}

function renderFileList() {
  const fileList = document.getElementById('file-list');
  fileList.innerHTML = '';
  
  if (attachedFiles.length === 0) {
    return;
  }
  
  attachedFiles.forEach((file, index) => {
    const fileSizeKB = (file.size / 1024).toFixed(2);
    const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
    const sizeText = fileSizeKB < 1024 ? fileSizeKB + ' KB' : fileSizeMB + ' MB';
    
    const fileItem = document.createElement('div');
    fileItem.className = 'je-file-item';
    
    const iconWrapper = document.createElement('div');
    iconWrapper.className = 'je-file-icon';
    iconWrapper.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9l-7-7z" stroke="currentColor" stroke-width="2"/><path d="M13 2v7h7" stroke="currentColor" stroke-width="2"/></svg>';
    
    const info = document.createElement('div');
    info.className = 'je-file-info';
    info.innerHTML = `
      <div class="je-file-name">${file.name}</div>
      <div class="je-file-size">${sizeText}</div>
    `;
    
    const actions = document.createElement('div');
    actions.className = 'je-file-actions';
    
    // For existing files from database, show download button
    if (file.isExisting && file.file_id) {
      const downloadBtn = document.createElement('a');
      downloadBtn.href = `/api/download-attachment.php?file_id=${file.file_id}`;
      downloadBtn.target = '_blank';
      downloadBtn.className = 'je-btn je-btn-ghost je-btn-sm';
      downloadBtn.style.textDecoration = 'none';
      downloadBtn.innerHTML = `
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
          <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4m4-5l5 5m0 0l5-5m-5 5V3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Download
      `;
      actions.appendChild(downloadBtn);
    } else {
      // For newly uploaded files, show view button
      const viewBtn = document.createElement('button');
      viewBtn.type = 'button';
      viewBtn.className = 'je-btn je-btn-ghost je-btn-sm';
      viewBtn.onclick = () => viewFile(index);
      viewBtn.innerHTML = `
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2"/>
          <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
        </svg>
        View
      `;
      actions.appendChild(viewBtn);
    }
    
    // Remove button (always show)
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'je-btn je-btn-ghost je-btn-sm';
    removeBtn.onclick = () => removeFile(index);
    removeBtn.style.color = 'var(--color-danger)';
    removeBtn.innerHTML = `
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
        <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
    `;
    
    actions.appendChild(removeBtn);
    
    fileItem.appendChild(iconWrapper);
    fileItem.appendChild(info);
    fileItem.appendChild(actions);
    fileList.appendChild(fileItem);
  });
}

function viewFile(index) {
  const file = attachedFiles[index];
  const dialog = document.getElementById('file-preview-dialog');
  const container = document.getElementById('file-preview-container');
  const titleEl = document.getElementById('preview-file-name');
  
  titleEl.textContent = file.name;
  
  // Render preview based on file type
  if (file.type.startsWith('image/')) {
    container.innerHTML = `
      <img src="${file.dataUrl}" alt="${file.name}" style="width: 100%; height: auto; display: block;">
    `;
  } else if (file.type === 'application/pdf') {
    container.innerHTML = `
      <iframe src="${file.dataUrl}" style="width: 100%; height: 600px; border: none;"></iframe>
    `;
  } else {
    container.innerHTML = `
      <div style="padding: 2rem; text-align: center;">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" style="margin: 0 auto 1rem; opacity: 0.3;">
          <path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9l-7-7z" stroke="currentColor" stroke-width="2"/>
          <path d="M13 2v7h7" stroke="currentColor" stroke-width="2"/>
        </svg>
        <p style="color: var(--text-secondary); margin-bottom: 1rem;">Preview not available for this file type</p>
        <p style="font-size: 0.875rem; color: var(--text-secondary);">File: ${file.name}</p>
        <p style="font-size: 0.875rem; color: var(--text-secondary);">Size: ${(file.size / 1024).toFixed(2)} KB</p>
        <a href="${file.dataUrl}" download="${file.name}" class="je-btn je-btn-primary" style="margin-top: 1rem; display: inline-flex;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4m4-5l5 5m0 0l5-5m-5 5V3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Download
        </a>
      </div>
    `;
  }
  
  dialog.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeFilePreview() {
  const dialog = document.getElementById('file-preview-dialog');
  dialog.style.display = 'none';
  document.body.style.overflow = '';
}

function removeFile(index) {
  const file = attachedFiles[index];
  
  if (confirm('Remove this file?')) {
    // If it's an existing file from database, call API to delete it
    if (file.isExisting && file.file_id) {
      const urlParams = new URLSearchParams(window.location.search);
      const entryId = urlParams.get('edit');
      
      if (entryId) {
        fetch('/api/delete-attachment.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            entry_id: entryId,
            file_id: file.file_id
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            Toast.success('Attachment deleted successfully');
          } else {
            Toast.error('Failed to delete attachment: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Error deleting attachment:', error);
          Toast.error('Error deleting attachment');
        });
      }
    }
    
    // Remove from UI
    attachedFiles.splice(index, 1);
    renderFileList();
    saveFilesToStorage();
  }
}

function saveFilesToStorage() {
  try {
    localStorage.setItem('je_attachments', JSON.stringify(attachedFiles));
  } catch (e) {
    console.warn('Could not save files to localStorage:', e);
  }
}

function loadFilesFromStorage() {
  try {
    const stored = localStorage.getItem('je_attachments');
    if (stored) {
      attachedFiles = JSON.parse(stored);
      renderFileList();
    }
  } catch (e) {
    console.warn('Could not load files from localStorage:', e);
  }
}

function clearStoredFiles() {
  attachedFiles = [];
  renderFileList();
  localStorage.removeItem('je_attachments');
}

// Form Data Persistence
function saveFormDataToStorage() {
  try {
    const formData = {
      entry_date: document.getElementById('entry_date').value,
      entry_type: document.getElementById('entry_type').value,
      reference: document.getElementById('reference').value,
      description: document.getElementById('description').value,
      tags: tagsArray,
      lines: [],
      timestamp: Date.now(),
      last_modified_at: new Date().toISOString()
    };
    
    // Save all transaction lines
    const lines = document.querySelectorAll('.entry-line');
    lines.forEach(line => {
      const lineId = line.dataset.lineId;
      const accountSelect = line.querySelector(`select[name="line_${lineId}_account"]`);
      const debitInput = line.querySelector(`input[name="line_${lineId}_debit"]`);
      const creditInput = line.querySelector(`input[name="line_${lineId}_credit"]`);
      
      if (accountSelect && (debitInput.value || creditInput.value)) {
        formData.lines.push({
          account_code: accountSelect.value,
          debit: debitInput.value || '',
          credit: creditInput.value || ''
        });
      }
    });
    
    localStorage.setItem('je_form_data', JSON.stringify(formData));
    updateAuditTrail();
  } catch (e) {
    console.warn('Could not save form data to localStorage:', e);
  }
}

function updateAuditTrail() {
  try {
    const stored = localStorage.getItem('je_form_data');
    if (stored) {
      const formData = JSON.parse(stored);
      const modifiedByPopup = document.getElementById('audit-modified-by-popup');
      const modifiedAtPopup = document.getElementById('audit-modified-at-popup');
      
      if (formData.last_modified_at) {
        const date = new Date(formData.last_modified_at);
        const formatted = date.toLocaleDateString('en-US', { 
          month: 'short', 
          day: 'numeric', 
          year: 'numeric' 
        }) + ' at ' + date.toLocaleTimeString('en-US', { 
          hour: 'numeric', 
          minute: '2-digit' 
        });
        
        modifiedByPopup.textContent = '<?php echo htmlspecialchars($_SESSION['username'] ?? 'admin'); ?>';
        modifiedByPopup.classList.remove('je-audit-pending');
        modifiedAtPopup.textContent = formatted;
        modifiedAtPopup.style.display = 'flex';
      }
    }
  } catch (e) {
    console.warn('Could not update audit trail:', e);
  }
}

// Audit Panel Toggle
function toggleAuditPopover() {
  const panel = document.getElementById('audit-panel');
  if (panel.style.display === 'none') {
    panel.style.display = 'block';
    updateAuditTrail();
  } else {
    panel.style.display = 'none';
  }
}

// Close panel when clicking outside
document.addEventListener('click', function(e) {
  const panel = document.getElementById('audit-panel');
  const button = document.querySelector('.je-audit-btn');
  
  if (panel && button && panel.style.display === 'block') {
    if (!panel.contains(e.target) && !button.contains(e.target)) {
      panel.style.display = 'none';
    }
  }
});

function loadFormDataFromStorage() {
  try {
    const stored = localStorage.getItem('je_form_data');
    if (!stored) return;
    
    const formData = JSON.parse(stored);
    
    // Restore basic fields
    if (formData.entry_date) {
      document.getElementById('entry_date').value = formData.entry_date;
    }
    if (formData.entry_type) {
      document.getElementById('entry_type').value = formData.entry_type;
    }
    if (formData.reference) {
      document.getElementById('reference').value = formData.reference;
    }
    if (formData.description) {
      document.getElementById('description').value = formData.description;
    }
    
    // Restore tags
    if (formData.tags && formData.tags.length > 0) {
      setTags(formData.tags);
    }
    
    // Restore transaction lines
    if (formData.lines && formData.lines.length > 0) {
      formData.lines.forEach(lineData => {
        addLineWithData(lineData);
      });
      updateTotals();
    }
    
    updateSummary();
    updateAuditTrail();
  } catch (e) {
    console.warn('Could not load form data from localStorage:', e);
  }
}

function clearFormDataFromStorage() {
  localStorage.removeItem('je_form_data');
  
  // Also clear form fields
  document.getElementById('entry_date').value = new Date().toISOString().split('T')[0];
  document.getElementById('entry_type').value = 'general';
  document.getElementById('reference').value = '';
  document.getElementById('description').value = '';
  
  // Clear tags
  tagsArray = [];
  renderTags();
  updateTagsHidden();
  
  // Clear lines
  const container = document.getElementById('entry-lines');
  container.innerHTML = '';
  lineCounter = 0;
}

function addLine() {
  const container = document.getElementById('entry-lines');
  const lineId = lineCounter++;
  
  const lineHTML = `
    <div class="je-line entry-line" data-line-id="${lineId}">
      <div>
        <select name="line_${lineId}_account" class="je-input je-input-sm" required onchange="updateTotals(); saveFormDataToStorage();">
          <option value="">Select Account</option>
          ${accounts.map(acc => `
            <option value="${acc.account_code}">
              ${acc.account_code} - ${acc.account_name}
            </option>
          `).join('')}
        </select>
      </div>
      <div>
        <input 
          type="number" 
          name="line_${lineId}_debit" 
          class="je-input je-input-sm debit-input" 
          placeholder="Debit (${currencySymbol})"
          step="0.01"
          min="0"
          style="text-align: right; font-family: monospace;"
          oninput="updateTotals(); checkCredit(this, ${lineId}); saveFormDataToStorage();"
        >
      </div>
      <div>
        <input 
          type="number" 
          name="line_${lineId}_credit" 
          class="je-input je-input-sm credit-input" 
          placeholder="Credit (${currencySymbol})"
          step="0.01"
          min="0"
          style="text-align: right; font-family: monospace;"
          oninput="updateTotals(); checkDebit(this, ${lineId}); saveFormDataToStorage();"
        >
      </div>
      <div style="display: flex; align-items: center; justify-content: center;">
        <button type="button" class="je-btn je-btn-ghost" onclick="removeLine(${lineId})" title="Remove Line">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
            <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
    </div>
  `;
  
  container.insertAdjacentHTML('beforeend', lineHTML);
  document.getElementById('line_count').value = lineCounter;
  updateTotals();
}

function removeLine(lineId) {
  const line = document.querySelector(`[data-line-id="${lineId}"]`);
  if (line) {
    line.remove();
    updateTotals();
    saveFormDataToStorage();
  }
}

function checkDebit(creditInput, lineId) {
  if (parseFloat(creditInput.value) > 0) {
    const debitInput = document.querySelector(`input[name="line_${lineId}_debit"]`);
    if (debitInput) {
      debitInput.value = '';
    }
  }
}

function checkCredit(debitInput, lineId) {
  if (parseFloat(debitInput.value) > 0) {
    const creditInput = document.querySelector(`input[name="line_${lineId}_credit"]`);
    if (creditInput) {
      creditInput.value = '';
    }
  }
}

function updateTotals() {
  const debitInputs = document.querySelectorAll('.debit-input');
  const creditInputs = document.querySelectorAll('.credit-input');
  
  let totalDebit = 0;
  let totalCredit = 0;
  
  debitInputs.forEach(input => {
    totalDebit += parseFloat(input.value) || 0;
  });
  
  creditInputs.forEach(input => {
    totalCredit += parseFloat(input.value) || 0;
  });
  
  document.getElementById('total-debit').textContent = currencySymbol + totalDebit.toFixed(2);
  document.getElementById('total-credit').textContent = currencySymbol + totalCredit.toFixed(2);
  
  // Update sidebar lines count and audit badge
  const linesCount = document.querySelectorAll('.entry-line').length;
  document.getElementById('summary-lines').textContent = linesCount;
  document.getElementById('audit-line-count').textContent = linesCount;
  
  // Show warning if unbalanced
  const warning = document.getElementById('balance-warning');
  const submitBtn = document.getElementById('submit-btn');
  const summaryStatus = document.getElementById('summary-status');
  
  const isBalanced = Math.abs(totalDebit - totalCredit) < 0.01 && (totalDebit > 0 || totalCredit > 0);
  
  if (!isBalanced && (totalDebit > 0 || totalCredit > 0)) {
    warning.style.display = 'flex';
    submitBtn.disabled = true;
    summaryStatus.textContent = 'Unbalanced';
    summaryStatus.className = 'je-badge je-badge-danger';
  } else if (isBalanced) {
    warning.style.display = 'none';
    submitBtn.disabled = false;
    summaryStatus.textContent = 'Balanced';
    summaryStatus.className = 'je-badge je-badge-success';
  } else {
    warning.style.display = 'none';
    submitBtn.disabled = false;
    summaryStatus.textContent = 'Draft';
    summaryStatus.className = 'je-badge je-badge-warning';
  }
}

// Template Dialog Functions
async function openTemplateDialog() {
  const dialog = document.getElementById('template-dialog');
  dialog.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  
  // Fetch templates if not already loaded
  if (allTemplates.length === 0) {
    await fetchTemplates();
  }
}

function closeTemplateDialog() {
  const dialog = document.getElementById('template-dialog');
  dialog.style.display = 'none';
  document.body.style.overflow = '';
  document.getElementById('template-search').value = '';
}

async function fetchTemplates() {
  const templateList = document.getElementById('template-list');
  
  try {
    const response = await fetch('/api/get-recent-entries.php');
    const data = await response.json();
    
    if (data.success) {
      allTemplates = data.templates;
      filteredTemplates = allTemplates;
      renderTemplates(filteredTemplates);
    } else {
      templateList.innerHTML = `
        <div class="je-empty-templates">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" style="margin: 0 auto 1rem; opacity: 0.3;">
            <path d="M9 2v4m6-4v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z" stroke="currentColor" stroke-width="2"/>
          </svg>
          <p style="font-size: 0.875rem; margin: 0;">No templates found</p>
          <p style="font-size: 0.75rem; margin-top: 0.5rem; opacity: 0.7;">Create a journal entry to use as a template</p>
        </div>
      `;
    }
  } catch (error) {
    console.error('Error fetching templates:', error);
    templateList.innerHTML = `
      <div class="je-empty-templates">
        <p style="color: var(--color-danger);">Error loading templates</p>
        <p style="font-size: 0.75rem; margin-top: 0.5rem;">Please try again</p>
      </div>
    `;
  }
}

function renderTemplates(templates) {
  const templateList = document.getElementById('template-list');
  
  if (templates.length === 0) {
    templateList.innerHTML = `
      <div class="je-empty-templates">
        <p style="font-size: 0.875rem;">No templates match your search</p>
      </div>
    `;
    return;
  }
  
  templateList.innerHTML = templates.map(template => `
    <div class="je-template-item" onclick='applyTemplate(${JSON.stringify(template).replace(/'/g, "&apos;")})'>
      <div class="je-template-header">
        <span class="je-template-number">${template.entry_number}</span>
        <span class="je-template-date">${template.created_at}</span>
      </div>
      <div class="je-template-desc">${template.description}</div>
      <div class="je-template-meta">
        <span class="je-template-type">${template.entry_type.replace('_', ' ')}</span>
        <span class="je-template-lines">${template.lines.length} line(s)</span>
        ${template.reference ? `<span class="je-template-lines">Ref: ${template.reference}</span>` : ''}
        ${template.tags.length > 0 ? `<span class="je-template-lines">${template.tags.join(', ')}</span>` : ''}
      </div>
    </div>
  `).join('');
}

function filterTemplates(searchQuery) {
  const query = searchQuery.toLowerCase().trim();
  
  if (!query) {
    filteredTemplates = allTemplates;
  } else {
    filteredTemplates = allTemplates.filter(template => {
      return template.entry_number.toLowerCase().includes(query) ||
             template.description.toLowerCase().includes(query) ||
             template.entry_type.toLowerCase().includes(query) ||
             (template.reference && template.reference.toLowerCase().includes(query)) ||
             template.tags.some(tag => tag.toLowerCase().includes(query));
    });
  }
  
  renderTemplates(filteredTemplates);
}

function applyTemplate(template) {
  // Show confirmation
  if (!confirm(`Apply template "${template.entry_number}"?\n\nThis will replace current form data.`)) {
    return;
  }
  
  // Set basic fields
  document.getElementById('entry_date').value = new Date().toISOString().split('T')[0];
  document.getElementById('entry_type').value = template.entry_type;
  document.getElementById('description').value = template.description;
  document.getElementById('reference').value = template.reference || '';
  
  // Set tags using the new tag system
  setTags(template.tags || []);
  
  // Clear existing lines
  document.getElementById('entry-lines').innerHTML = '';
  lineCounter = 0;
  
  // Add lines from template
  template.lines.forEach(line => {
    addLineWithData(line);
  });
  
  // Update totals and summary
  updateTotals();
  updateSummary();
  
  // Close dialog
  closeTemplateDialog();
  
  // Scroll to top
  window.scrollTo({ top: 0, behavior: 'smooth' });
  
  // Show success message
  const alert = document.createElement('div');
  alert.className = 'je-alert je-alert-success';
  alert.style.cssText = 'position: fixed; top: 1rem; right: 1rem; z-index: 10000; animation: slideInRight 0.3s ease-out; min-width: 300px;';
  alert.innerHTML = `
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
      <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <span>Template applied successfully!</span>
  `;
  document.body.appendChild(alert);
  
  setTimeout(() => {
    alert.style.animation = 'fadeOut 0.3s ease-out';
    setTimeout(() => alert.remove(), 300);
  }, 3000);
}

function addLineWithData(lineData) {
  const container = document.getElementById('entry-lines');
  const lineId = lineCounter++;
  
  const lineHTML = `
    <div class="je-line entry-line" data-line-id="${lineId}">
      <div>
        <select name="line_${lineId}_account" class="je-input je-input-sm" required onchange="updateTotals(); saveFormDataToStorage();">
          <option value="">Select Account</option>
          ${accounts.map(acc => `
            <option value="${acc.account_code}" ${acc.account_code === lineData.account_code ? 'selected' : ''}>
              ${acc.account_code} - ${acc.account_name}
            </option>
          `).join('')}
        </select>
      </div>
      <div>
        <input 
          type="number" 
          name="line_${lineId}_debit" 
          class="je-input je-input-sm debit-input" 
          placeholder="Debit (${currencySymbol})"
          step="0.01"
          min="0"
          value="${lineData.debit || ''}"
          style="text-align: right; font-family: monospace;"
          oninput="updateTotals(); checkCredit(this, ${lineId}); saveFormDataToStorage();"
        >
      </div>
      <div>
        <input 
          type="number" 
          name="line_${lineId}_credit" 
          class="je-input je-input-sm credit-input" 
          placeholder="Credit (${currencySymbol})"
          step="0.01"
          min="0"
          value="${lineData.credit || ''}"
          style="text-align: right; font-family: monospace;"
          oninput="updateTotals(); checkDebit(this, ${lineId}); saveFormDataToStorage();"
        >
      </div>
      <div style="display: flex; align-items: center; justify-content: center;">
        <button type="button" class="je-btn je-btn-ghost" onclick="removeLine(${lineId})" title="Remove Line">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
            <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
    </div>
  `;
  
  container.insertAdjacentHTML('beforeend', lineHTML);
  document.getElementById('line_count').value = lineCounter;
}

// Add animation styles for success message
const style = document.createElement('style');
style.textContent = `
  @keyframes slideInRight {
    from {
      transform: translateX(100%);
      opacity: 0;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }
  @keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
  }
  .je-alert-success {
    background: #f0fdf4;
    border: 1px solid #86efac;
    color: #166534;
  }
`;
document.head.appendChild(style);
</script>

<?php
// Get page content
$pageContent = ob_get_clean();

// Include layout
include __DIR__ . '/components/layout.php';
?>
