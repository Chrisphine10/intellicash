<?php
namespace App\Utilities;

class LoanCalculator
{
    public $payable_amount;
    private $amount;
    private $first_payment_date;
    private $interest_rate;
    private $term;
    private $term_period;
    private $late_payment_penalties;
    private $loan_amount;

    public function __construct($amount, $first_payment_date, $interest_rate, $term, $term_period, $late_payment_penalties, $loan_amount = null)
    {
        $this->amount                 = $amount;
        $this->first_payment_date     = $first_payment_date;
        $this->interest_rate          = $interest_rate;
        $this->term                   = $term;
        $this->term_period            = $term_period;
        $this->late_payment_penalties = $late_payment_penalties;
        $this->loan_amount            = $loan_amount ?? $amount; //It's used for flat rate and fixed rate
    }

    private function getDurationInYears(): float
    {
        // Parse the term_period, e.g. "+7 day", "+3 month", "+1 year"
        // Remove the leading "+" if present
        $term_period_clean = ltrim($this->term_period, '+');

        // Split into number and unit
        preg_match('/(\d+)\s*(day|month|year)s?/', $term_period_clean, $matches);

        if (! $matches) {
            throw new \Exception("Invalid term_period format: " . $this->term_period);
        }

        $intervalCount = (int) $matches[1];
        $intervalUnit  = strtolower($matches[2]);

        // Calculate total duration in years
        switch ($intervalUnit) {
            case 'day':
                // Convert days to years (approximate with 365 days)
                $totalDays = $intervalCount * $this->term;
                return $totalDays / 365;
            case 'month':
                // Convert months to years
                $totalMonths = $intervalCount * $this->term;
                return $totalMonths / 12;
            case 'year':
                // Years directly
                $totalYears = $intervalCount * $this->term;
                return $totalYears;
            default:
                throw new \Exception("Unsupported interval unit: " . $intervalUnit);
        }
    }

    public function get_flat_rate()
    {
        $principal   = $this->amount;
        $rate        = $this->interest_rate / 100;
        $loan_amount = $this->loan_amount ?? $principal;

        $duration_in_years = $this->getDurationInYears(); // accurate duration
        $total_interest    = $loan_amount * $rate * $duration_in_years;
        $total_payable     = $principal + $total_interest;
        $installment       = $total_payable / $this->term;

        $principal_per_term = $principal / $this->term;
        $interest_per_term  = $total_interest / $this->term;
        $penalty            = ($this->late_payment_penalties / 100) * $principal_per_term;
        $balance            = $principal;
        $date               = $this->first_payment_date;

        $this->payable_amount = $total_payable;

        $schedule = [];

        for ($i = 0; $i < $this->term; $i++) {
            $balance -= $principal_per_term;

            $schedule[] = [
                'date'             => $date,
                'amount_to_pay'    => $installment,
                'penalty'          => $penalty,
                'principal_amount' => $principal_per_term,
                'interest'         => $interest_per_term,
                'balance'          => max($balance, 0),
            ];

            $date = date("Y-m-d", strtotime($this->term_period, strtotime($date)));
        }

        return $schedule;
    }

    // public function get_flat_rate() {
    //     $this->payable_amount = (($this->interest_rate / 100) * $this->amount) + $this->amount;

    //     $date             = $this->first_payment_date;
    //     $principal_amount = $this->amount / $this->term;
    //     $amount_to_pay    = $principal_amount + (($this->interest_rate / 100) * $principal_amount);
    //     $interest         = (($this->interest_rate / 100) * $this->loan_amount) / $this->term;
    //     $balance          = $this->amount;
    //     $penalty          = ($this->late_payment_penalties / 100) * $principal_amount;
    //     //$balance          = $this->payable_amount;
    //     //$interest         = (($this->interest_rate / 100) * $this->amount) / $this->term;
    //     //$penalty          = (($this->late_payment_penalties / 100) * $this->amount);

    //     $data = [];
    //     for ($i = 0; $i < $this->term; $i++) {
    //         $balance = $balance - $principal_amount;
    //         $data[]  = [
    //             'date'             => $date,
    //             'amount_to_pay'    => $amount_to_pay,
    //             'penalty'          => $penalty,
    //             'principal_amount' => $principal_amount,
    //             'interest'         => $interest,
    //             'balance'          => $balance,
    //         ];

    //         $date = date("Y-m-d", strtotime($this->term_period, strtotime($date)));
    //     }

    //     return $data;
    // }

    public function get_fixed_rate()
    {
        $this->payable_amount = ((($this->interest_rate / 100) * $this->amount) * $this->term) + $this->amount;
        $date                 = $this->first_payment_date;
        $principal_amount     = $this->amount / $this->term;
        $amount_to_pay        = $principal_amount + (($this->interest_rate / 100) * $this->amount);
        $interest             = (($this->interest_rate / 100) * $this->loan_amount);
        $balance              = $this->amount;
        $penalty              = ($this->late_payment_penalties / 100) * $principal_amount;
        //$balance              = $this->payable_amount;
        //$interest             = (($this->interest_rate / 100) * $this->amount);
        //$penalty              = (($this->late_payment_penalties / 100) * $this->amount);

        $data = [];
        for ($i = 0; $i < $this->term; $i++) {
            $balance = $balance - $principal_amount;
            $data[]  = [
                'date'             => $date,
                'amount_to_pay'    => $amount_to_pay,
                'penalty'          => $penalty,
                'principal_amount' => $principal_amount,
                'interest'         => $interest,
                'balance'          => $balance,
            ];

            $date = date("Y-m-d", strtotime($this->term_period, strtotime($date)));
        }

        return $data;
    }

    public function get_mortgage()
    {
        $interestRate = $this->interest_rate / 100;

        //Calculate the per month interest rate
        $monthlyRate = $interestRate / 12;

        //Calculate the payment
        $payment = $this->amount * ($monthlyRate / (1 - pow(1 + $monthlyRate, -$this->term)));

        $this->payable_amount = $payment * $this->term;

        $date    = $this->first_payment_date;
        $balance = $this->amount;

        $data = [];
        for ($count = 0; $count < $this->term; $count++) {
            $interest         = $balance * $monthlyRate;
            $monthlyPrincipal = $payment - $interest;
            $amount_to_pay    = $interest + $monthlyPrincipal;
            $penalty          = ($this->late_payment_penalties / 100) * $monthlyPrincipal;

            $balance = $balance - $monthlyPrincipal;
            $data[]  = [
                'date'             => $date,
                'amount_to_pay'    => $amount_to_pay,
                'penalty'          => $penalty,
                'principal_amount' => $monthlyPrincipal,
                'interest'         => $interest,
                'balance'          => $balance,
            ];

            $date = date("Y-m-d", strtotime($this->term_period, strtotime($date)));
        }

        return $data;
    }

    public function get_one_time()
    {
        $this->payable_amount = (($this->interest_rate / 100) * $this->amount) + $this->amount;
        $date                 = $this->first_payment_date;
        $principal_amount     = $this->amount;
        $amount_to_pay        = $principal_amount + (($this->interest_rate / 100) * $principal_amount);
        $interest             = (($this->interest_rate / 100) * $this->amount);
        $balance              = $this->payable_amount;
        //$penalty              = (($this->late_payment_penalties / 100) * $this->amount);
        $penalty = ($this->late_payment_penalties / 100) * $principal_amount;

        $data    = [];
        $balance = $balance - $amount_to_pay;
        $data[]  = [
            'date'             => $date,
            'amount_to_pay'    => $amount_to_pay,
            'penalty'          => $penalty,
            'principal_amount' => $principal_amount,
            'interest'         => $interest,
            'balance'          => $balance,
        ];

        $date = date("Y-m-d", strtotime($this->term_period, strtotime($date)));

        return $data;
    }

    public function get_reducing_amount()
    {
        $interestRate = $this->interest_rate / 100;

        //Calculate the per month interest rate
        $monthlyRate = $interestRate / 12;

        //Calculate the payment
        $payment          = $this->amount * ($monthlyRate / (1 - pow(1 + $monthlyRate, -$this->term)));
        $monthlyPrincipal = $this->amount / $this->term;

        $this->payable_amount = $payment * $this->term;

        $date    = $this->first_payment_date;
        $balance = $this->amount;
        //$penalty = (($this->late_payment_penalties / 100) * $this->amount);
        $penalty = ($this->late_payment_penalties / 100) * $monthlyPrincipal;

        $data = [];
        for ($count = 0; $count < $this->term; $count++) {
            $interest      = $balance * $monthlyRate;
            $amount_to_pay = $interest + $monthlyPrincipal;

            $balance = $balance - $monthlyPrincipal;
            $data[]  = [
                'date'             => $date,
                'amount_to_pay'    => $amount_to_pay,
                'penalty'          => $penalty,
                'principal_amount' => $monthlyPrincipal,
                'interest'         => $interest,
                'balance'          => $balance,
            ];

            $date = date("Y-m-d", strtotime($this->term_period, strtotime($date)));
        }

        return $data;
    }

    public function get_compound()
    {
        $principal = $this->amount;
        $rate = $this->interest_rate / 100;
        $duration_in_years = $this->getDurationInYears();
        
        // Determine compounding frequency based on term period
        $compounding_frequency = $this->getCompoundingFrequency();
        
        // Calculate compound interest using the existing method
        $total_amount = $this->calculateCompoundInterest($principal, $rate, $duration_in_years, $compounding_frequency);
        $total_interest = $total_amount - $principal;
        
        $this->payable_amount = $total_amount;
        
        // Calculate installment amount (total amount divided by number of terms)
        $installment = $total_amount / $this->term;
        
        // Calculate principal and interest per installment
        $principal_per_term = $principal / $this->term;
        $interest_per_term = $total_interest / $this->term;
        $penalty = ($this->late_payment_penalties / 100) * $principal_per_term;
        
        $balance = $principal;
        $date = $this->first_payment_date;
        
        $schedule = [];
        
        for ($i = 0; $i < $this->term; $i++) {
            $balance -= $principal_per_term;
            
            $schedule[] = [
                'date'             => $date,
                'amount_to_pay'    => $installment,
                'penalty'          => $penalty,
                'principal_amount' => $principal_per_term,
                'interest'         => $interest_per_term,
                'balance'          => max($balance, 0),
            ];
            
            $date = date("Y-m-d", strtotime($this->term_period, strtotime($date)));
        }
        
        return $schedule;
    }
    
    /**
     * Get compounding frequency based on term period
     * 
     * @return int Compounding frequency per year
     */
    private function getCompoundingFrequency()
    {
        // Parse the term_period to determine compounding frequency
        $term_period_clean = ltrim($this->term_period, '+');
        preg_match('/(\d+)\s*(day|month|year)s?/', $term_period_clean, $matches);
        
        if (!$matches) {
            return 12; // Default to monthly compounding
        }
        
        $intervalCount = (int) $matches[1];
        $intervalUnit = strtolower($matches[2]);
        
        switch ($intervalUnit) {
            case 'day':
                // Daily compounding
                return 365;
            case 'month':
                // Monthly compounding
                return 12;
            case 'year':
                // Annual compounding
                return 1;
            default:
                return 12; // Default to monthly
        }
    }

    /**
     * Calculate EMI (Equated Monthly Installment) - Banking Standard
     * Based on standard banking formula: EMI = P × r × (1+r)^n / ((1+r)^n - 1)
     * 
     * @param float $principal Loan principal amount
     * @param float $annualRate Annual interest rate (as decimal, e.g., 0.12 for 12%)
     * @param int $months Number of months
     * @return float EMI amount
     */
    public function calculateEMI($principal, $annualRate, $months)
    {
        if ($principal <= 0 || $months <= 0) {
            return 0;
        }

        // If interest rate is 0, return simple division
        if ($annualRate == 0) {
            return $principal / $months;
        }

        $monthlyRate = $annualRate / 12;
        
        // EMI = P × r × (1+r)^n / ((1+r)^n - 1)
        $emi = $principal * $monthlyRate * pow(1 + $monthlyRate, $months) / 
               (pow(1 + $monthlyRate, $months) - 1);
        
        return round($emi, 2);
    }

    /**
     * Calculate compound interest - Banking Standard
     * Formula: A = P(1 + r/n)^(nt)
     * 
     * @param float $principal Principal amount
     * @param float $rate Annual interest rate (as decimal)
     * @param float $time Time in years
     * @param int $compoundingFrequency Number of times interest compounds per year
     * @return float Final amount after compound interest
     */
    public function calculateCompoundInterest($principal, $rate, $time, $compoundingFrequency = 12)
    {
        if ($principal <= 0 || $time <= 0) {
            return $principal;
        }

        if ($rate == 0) {
            return $principal;
        }

        // A = P(1 + r/n)^(nt)
        $amount = $principal * pow(1 + ($rate / $compoundingFrequency), $compoundingFrequency * $time);
        
        return round($amount, 2);
    }

    /**
     * Calculate daily compound interest - Banking Standard
     * Used for savings accounts with daily interest accrual
     * 
     * @param float $principal Principal amount
     * @param float $annualRate Annual interest rate (as decimal)
     * @param int $days Number of days
     * @return float Final amount after daily compounding
     */
    public function calculateDailyCompoundInterest($principal, $annualRate, $days)
    {
        if ($principal <= 0 || $days <= 0) {
            return $principal;
        }

        if ($annualRate == 0) {
            return $principal;
        }

        // Daily rate = annual rate / 365
        // A = P(1 + r/365)^365 (for 1 year)
        $amount = $principal * pow(1 + ($annualRate / 365), $days);
        
        return round($amount, 2);
    }

    /**
     * Calculate late payment penalty - Banking Standard
     * Based on principal amount, penalty rate, and days late
     * 
     * @param float $principal Principal amount
     * @param float $penaltyRate Monthly penalty rate (as decimal, e.g., 0.02 for 2%)
     * @param int $daysLate Number of days late
     * @return float Penalty amount
     */
    public function calculateLatePenalty($principal, $penaltyRate, $daysLate)
    {
        if ($principal <= 0 || $daysLate <= 0 || $penaltyRate <= 0) {
            return 0;
        }

        // Convert monthly rate to daily rate and calculate penalty
        $dailyRate = $penaltyRate / 30; // Assuming 30 days per month
        $penalty = $principal * $dailyRate * $daysLate;
        
        return round($penalty, 2);
    }

    /**
     * Calculate loan-to-value ratio (LTV) - Banking Standard
     * Used for risk assessment in lending
     * 
     * @param float $loanAmount Loan amount
     * @param float $collateralValue Collateral value
     * @return float LTV ratio (as decimal)
     */
    public function calculateLTV($loanAmount, $collateralValue)
    {
        if ($collateralValue <= 0) {
            return 1; // 100% LTV if no collateral
        }

        return round($loanAmount / $collateralValue, 4);
    }

    /**
     * Calculate debt-to-income ratio (DTI) - Banking Standard
     * Used for borrower qualification
     * 
     * @param float $monthlyDebt Total monthly debt payments
     * @param float $monthlyIncome Monthly income
     * @return float DTI ratio (as decimal)
     */
    public function calculateDTI($monthlyDebt, $monthlyIncome)
    {
        if ($monthlyIncome <= 0) {
            return 1; // 100% DTI if no income
        }

        return round($monthlyDebt / $monthlyIncome, 4);
    }

    /**
     * Calculate annual percentage rate (APR) - Banking Standard
     * Including fees and other costs
     * 
     * @param float $principal Principal amount
     * @param float $totalInterest Total interest over loan term
     * @param float $fees Total fees
     * @param float $time Loan term in years
     * @return float APR (as decimal)
     */
    public function calculateAPR($principal, $totalInterest, $fees, $time)
    {
        if ($principal <= 0 || $time <= 0) {
            return 0;
        }

        $totalCost = $totalInterest + $fees;
        $apr = ($totalCost / $principal) / $time;
        
        return round($apr, 6);
    }

    /**
     * Validate calculation inputs - Banking Standard
     * Ensures inputs are within reasonable banking standards
     * 
     * @param array $inputs Array of input values to validate
     * @return array Validation results
     */
    public function validateInputs($inputs)
    {
        $errors = [];
        
        foreach ($inputs as $key => $value) {
            switch ($key) {
                case 'principal':
                case 'amount':
                    if (!is_numeric($value) || $value < 0) {
                        $errors[] = "Invalid principal amount: {$value}";
                    }
                    break;
                    
                case 'rate':
                case 'interest_rate':
                    if (!is_numeric($value) || $value < 0 || $value > 1) {
                        $errors[] = "Invalid interest rate: {$value} (must be between 0 and 1)";
                    }
                    break;
                    
                case 'time':
                case 'months':
                case 'years':
                    if (!is_numeric($value) || $value <= 0) {
                        $errors[] = "Invalid time period: {$value}";
                    }
                    break;
                    
                case 'days':
                    if (!is_numeric($value) || $value < 0) {
                        $errors[] = "Invalid number of days: {$value}";
                    }
                    break;
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

}
