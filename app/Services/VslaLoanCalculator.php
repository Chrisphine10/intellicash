<?php

namespace App\Services;

use App\Models\LoanProduct;

/**
 * Centralized VSLA Loan Calculation Service
 * FIXED: Standardized loan calculation methods to ensure consistency
 */
class VslaLoanCalculator
{
    /**
     * Calculate total payable amount for a loan
     * FIXED: Consistent calculation method across all VSLA operations
     *
     * @param float $amount Principal loan amount
     * @param LoanProduct $loanProduct Loan product configuration
     * @return float Total amount payable (principal + interest)
     */
    public static function calculateTotalPayable($amount, $loanProduct)
    {
        $interestRate = $loanProduct->interest_rate / 100; // Convert percentage to decimal
        $term = $loanProduct->term;
        
        switch ($loanProduct->interest_type) {
            case 'flat_rate':
                // Flat rate: Interest calculated on original amount for entire term
                $interestAmount = $amount * $interestRate * $term;
                return $amount + $interestAmount;
                
            case 'reducing_amount':
                // Reducing balance: Interest calculated on remaining balance
                // For VSLA, we'll use a simplified monthly calculation
                $monthlyRate = $interestRate / 12; // Monthly interest rate
                $interestAmount = $amount * $monthlyRate * $term;
                return $amount + $interestAmount;
                
            case 'one_time':
                // One-time interest payment
                $interestAmount = $amount * $interestRate;
                return $amount + $interestAmount;
                
            case 'fixed_rate':
                // Fixed rate: Same as flat rate for VSLA purposes
                $interestAmount = $amount * $interestRate * $term;
                return $amount + $interestAmount;
                
            case 'compound':
                // Compound interest calculation
                $interestAmount = $amount * pow(1 + $interestRate, $term) - $amount;
                return $amount + $interestAmount;
                
            default:
                // Default to simple interest calculation
                $interestAmount = $amount * $interestRate;
                return $amount + $interestAmount;
        }
    }

    /**
     * Calculate interest earned on a loan for a specific period
     * FIXED: Consistent with total payable calculation
     *
     * @param float $principalAmount Principal amount
     * @param float $interestRate Annual interest rate (as decimal)
     * @param int $daysActive Number of days loan was active
     * @param string $interestType Type of interest calculation
     * @param float $totalPaid Total amount already paid (for reducing balance)
     * @return float Interest earned for the period
     */
    public static function calculateInterestForPeriod($principalAmount, $interestRate, $daysActive, $interestType = 'flat_rate', $totalPaid = 0)
    {
        switch ($interestType) {
            case 'flat_rate':
            case 'fixed_rate':
                // Flat rate: interest calculated on original principal
                return ($principalAmount * $interestRate * $daysActive) / 365;
                
            case 'reducing_amount':
                // Reducing balance: calculate based on remaining principal
                $remainingPrincipal = max(0, $principalAmount - $totalPaid);
                return ($remainingPrincipal * $interestRate * $daysActive) / 365;
                
            case 'one_time':
                // One-time interest: only if fully repaid
                return 0; // Will be calculated separately when loan is fully repaid
                
            case 'compound':
                // Compound interest: daily compounding
                $dailyRate = $interestRate / 365;
                return $principalAmount * (pow(1 + $dailyRate, $daysActive) - 1);
                
            default:
                // Default to simple interest calculation
                return ($principalAmount * $interestRate * $daysActive) / 365;
        }
    }

    /**
     * Validate loan calculation parameters
     *
     * @param float $amount Loan amount
     * @param LoanProduct $loanProduct Loan product
     * @return array Validation errors (empty if valid)
     */
    public static function validateLoanParameters($amount, $loanProduct)
    {
        $errors = [];
        
        if ($amount <= 0) {
            $errors[] = 'Loan amount must be greater than zero';
        }
        
        if ($amount > 1000000) { // Reasonable upper limit
            $errors[] = 'Loan amount exceeds maximum limit';
        }
        
        if (!$loanProduct) {
            $errors[] = 'Loan product is required';
        } else {
            if ($loanProduct->interest_rate < 0 || $loanProduct->interest_rate > 100) {
                $errors[] = 'Interest rate must be between 0 and 100 percent';
            }
            
            if ($loanProduct->term <= 0 || $loanProduct->term > 120) { // Max 10 years
                $errors[] = 'Loan term must be between 1 and 120 months';
            }
        }
        
        return $errors;
    }

    /**
     * Get loan calculation summary
     *
     * @param float $amount Principal amount
     * @param LoanProduct $loanProduct Loan product
     * @return array Calculation summary
     */
    public static function getCalculationSummary($amount, $loanProduct)
    {
        $totalPayable = self::calculateTotalPayable($amount, $loanProduct);
        $totalInterest = $totalPayable - $amount;
        
        return [
            'principal_amount' => $amount,
            'total_payable' => $totalPayable,
            'total_interest' => $totalInterest,
            'interest_rate' => $loanProduct->interest_rate,
            'interest_type' => $loanProduct->interest_type,
            'term_months' => $loanProduct->term,
            'monthly_payment' => $loanProduct->term > 0 ? $totalPayable / $loanProduct->term : $totalPayable,
        ];
    }
}
