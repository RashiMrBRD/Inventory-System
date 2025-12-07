<?php

namespace App\Service;

use App\Model\BirForm;
use App\Service\DatabaseService;

/**
 * BIR Service
 * Handles Philippine Bureau of Internal Revenue compliance
 * - Form generation (RAMSAY 307, VAT, Withholding, ITR)
 * - eFPS integration
 * - Tax calculations
 */
class BIRService
{
    private const VAT_RATE = 0.12; // 12% VAT
    private const EWT_RATES = [
        'professional_services' => 0.10,
        'rental' => 0.10,
        'interest' => 0.20,
        'royalties' => 0.20,
        'supplies' => 0.01,
        'contractors' => 0.02
    ];
    
    private const PERCENTAGE_TAX_RATES = [
        'gross_receipts' => 0.03,
        'other_non_vat' => 0.03
    ];

    /**
     * Generate RAMSAY 307 form (Import Duties)
     */
    public static function generateRAMSAY307($containerData)
    {
        $db = DatabaseService::getInstance();
        
        // Calculate import duties based on container data
        $customsDuty = 0;
        $importVAT = 0;
        $otherTaxes = 0;
        
        // Get value of goods with fallback to insurance, total cost, or estimated value
        $valueOfGoods = 0;
        if (!empty($containerData['value_of_goods'])) {
            $valueOfGoods = floatval($containerData['value_of_goods']);
        } elseif (!empty($containerData['insurance_amount'])) {
            $valueOfGoods = floatval($containerData['insurance_amount']);
        } elseif (!empty($containerData['total_cost'])) {
            $valueOfGoods = floatval($containerData['total_cost']);
        } else {
            // For existing shipments without value_of_goods, estimate based on shipping cost
            $shippingCost = floatval($containerData['shipping_cost'] ?? 0);
            if ($shippingCost > 0) {
                // Estimate value as 10x shipping cost (common estimation)
                $valueOfGoods = $shippingCost * 10;
            } else {
                // Default fallback value for demonstration
                $valueOfGoods = 50000; // ₱50,000 default
            }
        }
        
        if ($valueOfGoods > 0) {
            $customsDuty = $valueOfGoods * 0.30; // 30% average duty
            $importVAT = ($valueOfGoods + $customsDuty) * self::VAT_RATE;
        }
        
        $totalDuties = $customsDuty + $importVAT + $otherTaxes;
        
        // Save to database
        $formData = [
            'form_type' => 'RAMSAY 307',
            'container_number' => $containerData['container_number'] ?? '',
            'arrival_date' => $containerData['arrival_date'] ?? date('Y-m-d'),
            'value_of_goods' => $valueOfGoods,
            'customs_duty' => $customsDuty,
            'import_vat' => $importVAT,
            'other_taxes' => $otherTaxes,
            'total_duties' => $totalDuties,
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? null
        ];
        
        $birForm = new BirForm();
        $formId = $birForm->create($formData);
        
        return array_merge($formData, ['id' => $formId]);
    }

    /**
     * Generate VAT Return (Form 2550M - Monthly)
     */
    public static function generateVATMonthly($month, $year)
    {
        $db = DatabaseService::getInstance();
        $period = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
        
        // Get transactions for the period
        $transactions = self::getVATTransactions($period);
        
        $vatCalculations = self::calculateVAT($transactions);
        
        // Additional VAT calculations
        $zeroRatedSales = self::getZeroRatedSales($period);
        $exemptSales = self::getExemptSales($period);
        $deferredOutputVAT = self::getDeferredOutputVAT($period);
        
        $formData = [
            'form_type' => '2550M',
            'period' => $month . '/' . $year,
            'output_vat' => $vatCalculations['output_vat'],
            'input_vat' => $vatCalculations['input_vat'],
            'vat_payable' => $vatCalculations['vat_payable'],
            'zero_rated_sales' => $zeroRatedSales,
            'exempt_sales' => $exemptSales,
            'deferred_output_vat' => $deferredOutputVAT,
            'total_sales' => self::getTotalSales($period),
            'total_purchases' => self::getTotalPurchases($period),
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? null
        ];
        
        $birForm = new BirForm();
        $formId = $birForm->create($formData);
        
        return array_merge($formData, ['id' => $formId]);
    }

    /**
     * Generate VAT Return (Form 2550Q - Quarterly)
     */
    public static function generateVATQuarterly($quarter, $year)
    {
        $db = DatabaseService::getInstance();
        
        // Get months for the quarter
        $months = self::getQuarterMonths($quarter);
        
        $totalOutputVAT = 0;
        $totalInputVAT = 0;
        $totalSales = 0;
        $totalPurchases = 0;
        $zeroRatedSales = 0;
        $exemptSales = 0;
        
        foreach ($months as $month) {
            $period = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
            $transactions = self::getVATTransactions($period);
            $vatCalculations = self::calculateVAT($transactions);
            
            $totalOutputVAT += $vatCalculations['output_vat'];
            $totalInputVAT += $vatCalculations['input_vat'];
            $totalSales += self::getTotalSales($period);
            $totalPurchases += self::getTotalPurchases($period);
            $zeroRatedSales += self::getZeroRatedSales($period);
            $exemptSales += self::getExemptSales($period);
        }
        
        $formData = [
            'form_type' => '2550Q',
            'period' => 'Q' . $quarter . ' ' . $year,
            'output_vat' => $totalOutputVAT,
            'input_vat' => $totalInputVAT,
            'vat_payable' => $totalOutputVAT - $totalInputVAT,
            'total_sales' => $totalSales,
            'total_purchases' => $totalPurchases,
            'zero_rated_sales' => $zeroRatedSales,
            'exempt_sales' => $exemptSales,
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? null
        ];
        
        $birForm = new BirForm();
        $formId = $birForm->create($formData);
        
        return array_merge($formData, ['id' => $formId]);
    }

    /**
     * Generate Withholding Tax (Form 1601C)
     */
    public static function generateWithholdingTax($month, $year)
    {
        $db = DatabaseService::getInstance();
        $period = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
        
        $withholdingData = self::getWithholdingData($period);
        
        $formData = [
            'form_type' => '1601C',
            'period' => $month . '/' . $year,
            'compensation_withheld' => $withholdingData['compensation'],
            'professional_withheld' => $withholdingData['professional'],
            'rental_withheld' => $withholdingData['rental'],
            'interest_withheld' => $withholdingData['interest'],
            'total_withheld' => array_sum($withholdingData),
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? null
        ];
        
        $birForm = new BirForm();
        $formId = $birForm->create($formData);
        
        return array_merge($formData, ['id' => $formId]);
    }

    /**
     * Generate Certificate of Creditable Tax (Form 2307)
     */
    public static function generate2307($paymentId)
    {
        $db = DatabaseService::getInstance();
        
        // Get payment details - simplified for now
        // $db = DatabaseService::getInstance();
        // TODO: Implement MongoDB query later
        $payment = [
            'payee_name' => 'Sample Payee',
            'payment_date' => date('Y-m-d'),
            'amount' => 10000,
            'withholding_type' => 'professional_services'
        ];
        
        if (!$payment) {
            throw new \Exception("Payment not found");
        }
        
        $withholdingType = $payment['withholding_type'] ?? 'professional_services';
        $rate = self::EWT_RATES[$withholdingType] ?? 0.10;
        $amountWithheld = $payment['amount'] * $rate;
        
        $formData = [
            'form_type' => '2307',
            'payment_id' => $paymentId,
            'payee_name' => $payment['payee_name'],
            'payment_date' => $payment['payment_date'],
            'gross_amount' => $payment['amount'],
            'withholding_type' => $withholdingType,
            'withholding_rate' => $rate,
            'amount_withheld' => $amountWithheld,
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? null
        ];
        
        $birForm = new BirForm();
        $formId = $birForm->create($formData);
        
        return array_merge($formData, ['id' => $formId]);
    }

    /**
     * Generate Annual Income Tax Return (Form 1702)
     */
    public static function generateITR($year)
    {
        $db = DatabaseService::getInstance();
        
        // Get yearly financial data
        $grossIncome = self::getAnnualGrossIncome($year);
        $allowableDeductions = self::getAllowableDeductions($year);
        $taxableIncome = $grossIncome - $allowableDeductions;
        
        // Calculate income tax based on tax brackets
        $taxDue = self::calculateIncomeTax($taxableIncome);
        
        $formData = [
            'form_type' => '1702',
            'year' => $year,
            'gross_income' => $grossIncome,
            'allowable_deductions' => $allowableDeductions,
            'taxable_income' => $taxableIncome,
            'tax_due' => $taxDue,
            'tax_withheld' => self::getTotalTaxWithheld($year),
            'tax_payable' => max(0, $taxDue - self::getTotalTaxWithheld($year)),
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? null
        ];
        
        $birForm = new BirForm();
        $formId = $birForm->create($formData);
        
        return array_merge($formData, ['id' => $formId]);
    }

    /**
     * Generate Percentage Tax Return (Form 2551Q)
     */
    public static function generatePercentageTax($quarter, $year)
    {
        $db = DatabaseService::getInstance();
        
        $months = self::getQuarterMonths($quarter);
        $totalGrossReceipts = 0;
        
        foreach ($months as $month) {
            $period = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
            $totalGrossReceipts += self::getGrossReceipts($period);
        }
        
        $percentageTax = $totalGrossReceipts * self::PERCENTAGE_TAX_RATES['gross_receipts'];
        
        $formData = [
            'form_type' => '2551Q',
            'period' => 'Q' . $quarter . ' ' . $year,
            'gross_receipts' => $totalGrossReceipts,
            'percentage_tax_rate' => self::PERCENTAGE_TAX_RATES['gross_receipts'],
            'percentage_tax_due' => $percentageTax,
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => $_SESSION['user_id'] ?? null
        ];
        
        $birForm = new BirForm();
        $formId = $birForm->create($formData);
        
        return array_merge($formData, ['id' => $formId]);
    }

    /**
     * Submit to eFPS (Electronic Filing and Payment System)
     */
    public static function submitToEFPS($formData)
    {
        // Simulate eFPS submission
        $referenceNumber = 'EFPS' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        
        // Update form status - simplified for now
        // $db = DatabaseService::getInstance();
        // TODO: Implement MongoDB update later
        
        return [
            'success' => true,
            'message' => 'Form successfully submitted to eFPS',
            'reference_number' => $referenceNumber
        ];
    }

    /**
     * Get all BIR forms for a period
     */
    public static function getFormsByPeriod($startDate, $endDate)
    {
        // Get forms - simplified for now
        // $db = DatabaseService::getInstance();
        // TODO: Implement MongoDB query later
        return [];
    }

    /**
     * Calculate VAT from transactions
     */
    public static function calculateVAT($transactions)
    {
        $outputVAT = 0;
        $inputVAT = 0;

        foreach ($transactions as $transaction) {
            if ($transaction['type'] === 'sales') {
                $outputVAT += $transaction['amount'] * self::VAT_RATE;
            } elseif ($transaction['type'] === 'purchase') {
                $inputVAT += $transaction['amount'] * self::VAT_RATE;
            }
        }

        return [
            'output_vat' => $outputVAT,
            'input_vat' => $inputVAT,
            'vat_payable' => $outputVAT - $inputVAT
        ];
    }

    /**
     * Update form status
     */
    public static function updateFormStatus($formId, $newStatus)
    {
        try {
            $db = DatabaseService::getInstance();
            $collection = $db->getCollection('bir_forms');
            
            $objectId = new \MongoDB\BSON\ObjectId($formId);
            $result = $collection->updateOne(
                ['_id' => $objectId],
                ['$set' => ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')]]
            );
            
            if ($result->getModifiedCount() > 0) {
                return ['success' => true, 'message' => 'Status updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Form not found or no changes made'];
            }
        } catch (\Exception $e) {
            error_log('Error updating form status: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update status'];
        }
    }
    
    /**
     * Delete a form
     */
    public static function deleteForm($formId)
    {
        try {
            $db = DatabaseService::getInstance();
            $collection = $db->getCollection('bir_forms');
            
            $objectId = new \MongoDB\BSON\ObjectId($formId);
            $result = $collection->deleteOne(['_id' => $objectId]);
            
            if ($result->getDeletedCount() > 0) {
                return ['success' => true, 'message' => 'Form deleted successfully'];
            } else {
                return ['success' => false, 'message' => 'Form not found'];
            }
        } catch (\Exception $e) {
            error_log('Error deleting form: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete form'];
        }
    }
    
    /**
     * Get a single form by ID
     */
    public static function getForm($formId)
    {
        try {
            $db = DatabaseService::getInstance();
            $collection = $db->getCollection('bir_forms');
            
            $objectId = new \MongoDB\BSON\ObjectId($formId);
            $form = $collection->findOne(['_id' => $objectId]);
            
            if ($form) {
                $formArray = (array)$form;
                $formArray['id'] = $formArray['_id'] ?? $formId;
                return ['success' => true, 'data' => $formArray];
            } else {
                return ['success' => false, 'message' => 'Form not found'];
            }
        } catch (\Exception $e) {
            error_log('Error getting form: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to retrieve form'];
        }
    }

    /**
     * Validate BIR form data
     */
    public static function validateForm($formType, $formData)
    {
        $errors = [];

        switch ($formType) {
            case '2550M':
            case '2550Q':
                if (empty($formData['period'])) {
                    $errors[] = 'Period is required';
                }
                if ($formData['output_vat'] < 0 || $formData['input_vat'] < 0) {
                    $errors[] = 'VAT amounts cannot be negative';
                }
                break;
                
            case '1601C':
                if (empty($formData['period'])) {
                    $errors[] = 'Period is required';
                }
                if ($formData['total_withheld'] < 0) {
                    $errors[] = 'Total withheld cannot be negative';
                }
                break;
                
            case '1702':
                if (empty($formData['year'])) {
                    $errors[] = 'Year is required';
                }
                if ($formData['gross_income'] < 0) {
                    $errors[] = 'Gross income cannot be negative';
                }
                break;
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    // Helper methods - simplified with mock data for now
    private static function getVATTransactions($period)
    {
        // Mock data - replace with real MongoDB queries later
        return [
            ['type' => 'sales', 'amount' => 100000],
            ['type' => 'purchase', 'amount' => 50000]
        ];
    }
    
    private static function getTotalSales($period)
    {
        return 100000; // Mock data
    }
    
    private static function getTotalPurchases($period)
    {
        return 50000; // Mock data
    }
    
    private static function getZeroRatedSales($period)
    {
        return 10000; // Mock data
    }
    
    private static function getExemptSales($period)
    {
        return 5000; // Mock data
    }
    
    private static function getDeferredOutputVAT($period)
    {
        return 2000; // Mock data
    }
    
    private static function getQuarterMonths($quarter)
    {
        $quarters = [
            1 => [1, 2, 3],
            2 => [4, 5, 6],
            3 => [7, 8, 9],
            4 => [10, 11, 12]
        ];
        return $quarters[$quarter] ?? [1, 2, 3];
    }
    
    private static function getWithholdingData($period)
    {
        // Mock data - replace with real MongoDB queries later
        return [
            'compensation' => 5000,
            'professional' => 3000,
            'rental' => 2000,
            'interest' => 1000
        ];
    }
    
    private static function getWithholdingByType($period, $type)
    {
        // Mock data - replace with real MongoDB queries later
        $rates = [
            'compensation' => 5000,
            'professional_services' => 3000,
            'rental' => 2000,
            'interest' => 1000
        ];
        return $rates[$type] ?? 1000;
    }
    
    private static function getAnnualGrossIncome($year)
    {
        return 1200000; // Mock data
    }
    
    private static function getAllowableDeductions($year)
    {
        return 200000; // Mock data
    }
    
    private static function calculateIncomeTax($taxableIncome)
    {
        // 2024 Philippine income tax brackets for individuals
        if ($taxableIncome <= 250000) return 0;
        if ($taxableIncome <= 400000) return ($taxableIncome - 250000) * 0.20;
        if ($taxableIncome <= 800000) return 30000 + ($taxableIncome - 400000) * 0.25;
        if ($taxableIncome <= 2000000) return 130000 + ($taxableIncome - 800000) * 0.30;
        if ($taxableIncome <= 8000000) return 490000 + ($taxableIncome - 2000000) * 0.32;
        return 2410000 + ($taxableIncome - 8000000) * 0.35;
    }
    
    private static function getTotalTaxWithheld($year)
    {
        return 50000; // Mock data
    }
    
    private static function getGrossReceipts($period)
    {
        return 80000; // Mock data
    }
}
