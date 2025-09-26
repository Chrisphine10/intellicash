<?php

namespace App\Models;

use App\Services\VslaLoanCalculator;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VslaCycle extends Model
{
    use MultiTenant;

    protected $table = 'vsla_cycles';

    protected $fillable = [
        'tenant_id',
        'cycle_name',
        'start_date',
        'end_date',
        'status',
        'total_shares_contributed',
        'total_welfare_contributed',
        'total_penalties_collected',
        'total_loan_interest_earned',
        'total_available_for_shareout',
        'notes',
        'created_user_id',
        'share_out_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'share_out_date' => 'datetime',
        'total_shares_contributed' => 'decimal:2',
        'total_welfare_contributed' => 'decimal:2',
        'total_penalties_collected' => 'decimal:2',
        'total_loan_interest_earned' => 'decimal:2',
        'total_available_for_shareout' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        // Ensure only one active cycle per tenant
        static::creating(function ($cycle) {
            static::ensureOnlyOneActiveCycle($cycle);
        });

        static::updating(function ($cycle) {
            if ($cycle->isDirty('status') && $cycle->status === 'active') {
                static::ensureOnlyOneActiveCycle($cycle);
            }
        });
    }

    /**
     * Ensure only one active cycle per tenant
     */
    protected static function ensureOnlyOneActiveCycle($newCycle)
    {
        // Find any existing active cycles for the same tenant
        $existingActiveCycles = static::where('tenant_id', $newCycle->tenant_id)
            ->where('status', 'active')
            ->where('id', '!=', $newCycle->id ?? 0)
            ->get();

        if ($existingActiveCycles->isNotEmpty()) {
            throw new \Exception('A tenant can only have one active cycle at a time. Please complete or archive the existing active cycle first.');
        }
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    public function shareouts()
    {
        return $this->hasMany(VslaShareout::class, 'cycle_id');
    }

    public function transactions()
    {
        return $this->hasMany(VslaTransaction::class, 'cycle_id');
    }

    public function meetings()
    {
        return $this->hasMany(VslaMeeting::class, 'cycle_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeShareOutInProgress($query)
    {
        return $query->where('status', 'share_out_in_progress');
    }

    /**
     * Get the current active cycle for a tenant
     */
    public static function getActiveCycleForTenant($tenantId)
    {
        return static::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Check if there's an active cycle for a tenant
     */
    public static function hasActiveCycle($tenantId)
    {
        return static::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Check if cycle is eligible for share-out
     */
    public function isEligibleForShareOut()
    {
        return $this->status === 'active' && 
               Carbon::now()->greaterThanOrEqualTo($this->end_date);
    }

    /**
     * Check if cycle is in share-out phase
     */
    public function isInShareOutPhase()
    {
        return in_array($this->status, ['share_out_in_progress', 'completed']);
    }

    /**
     * Get current phase of the cycle
     */
    public function getCurrentPhase()
    {
        if ($this->status === 'completed') {
            return 'completed';
        }
        
        if ($this->status === 'share_out_in_progress') {
            return 'share_out';
        }
        
        if ($this->status === 'active') {
            if ($this->isEligibleForShareOut()) {
                return 'ready_for_shareout';
            }
            return 'active';
        }
        
        return 'archived';
    }

    /**
     * Get phase description
     */
    public function getPhaseDescription()
    {
        switch ($this->getCurrentPhase()) {
            case 'active':
                return 'Cycle is active - members can make contributions and take loans';
            case 'ready_for_shareout':
                return 'Cycle has ended - ready to begin share-out process';
            case 'share_out':
                return 'Share-out is in progress - calculating and processing member payouts';
            case 'completed':
                return 'Cycle completed - share-out has been distributed to all members';
            default:
                return 'Cycle archived';
        }
    }

    /**
     * Get total members who participated in this cycle
     */
    public function getParticipatingMembersCount()
    {
        return VslaTransaction::where('cycle_id', $this->id)
            ->where('transaction_type', 'share_purchase')
            ->where('status', 'approved')
            ->distinct('member_id')
            ->count('member_id');
    }

    /**
     * Get members who participated in this cycle
     */
    public function getParticipatingMembers()
    {
        $memberIds = VslaTransaction::where('cycle_id', $this->id)
            ->where('transaction_type', 'share_purchase')
            ->where('status', 'approved')
            ->distinct()
            ->pluck('member_id');

        return Member::whereIn('id', $memberIds)->get();
    }

    /**
     * Calculate cycle totals with proper financial logic
     */
    public function calculateTotals()
    {
        // Use database transaction to ensure consistency
        DB::transaction(function () {
            // Total shares contributed
            $this->total_shares_contributed = VslaTransaction::where('cycle_id', $this->id)
                ->where('transaction_type', 'share_purchase')
                ->where('status', 'approved')
                ->sum('amount');

            // Total welfare contributed
            $this->total_welfare_contributed = VslaTransaction::where('cycle_id', $this->id)
                ->where('transaction_type', 'welfare_contribution')
                ->where('status', 'approved')
                ->sum('amount');

            // Total penalties collected
            $this->total_penalties_collected = VslaTransaction::where('cycle_id', $this->id)
                ->where('transaction_type', 'penalty_fine')
                ->where('status', 'approved')
                ->sum('amount');

            // Calculate loan interest earned properly
            $this->total_loan_interest_earned = $this->calculateActualLoanInterest();

            // Calculate total available for share-out (all funds belong to members)
            $this->total_available_for_shareout = 
                $this->total_shares_contributed + 
                $this->total_welfare_contributed + 
                $this->total_penalties_collected + 
                $this->total_loan_interest_earned;

            $this->save();
        });
    }

    /**
     * Calculate actual loan interest earned during the cycle
     * FIXED: Improved performance with eager loading and better error handling
     */
    private function calculateActualLoanInterest()
    {
        $cycleStart = $this->start_date;
        $cycleEnd = $this->end_date ?? now();

        // FIXED: Use eager loading to prevent N+1 queries
        $cycleLoans = Loan::with('loan_product')
            ->where('tenant_id', $this->tenant_id)
            ->where('release_date', '>=', $cycleStart)
            ->where('release_date', '<=', $cycleEnd)
            ->where('status', 2) // Active loans
            ->get();

        $totalInterestEarned = 0;

        foreach ($cycleLoans as $loan) {
            try {
                // Calculate interest earned on this loan during the cycle
                $loanInterest = $this->calculateLoanInterestForPeriod($loan, $cycleStart, $cycleEnd);
                $totalInterestEarned += $loanInterest;
            } catch (\Exception $e) {
                // Log error but continue processing other loans
                \Log::error('VSLA Loan Interest Calculation Error', [
                    'loan_id' => $loan->id,
                    'cycle_id' => $this->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Continue with next loan instead of failing entire calculation
                continue;
            }
        }

        return $totalInterestEarned;
    }

    /**
     * Calculate interest earned on a specific loan for a given period
     * FIXED: Corrected loan end date calculation and improved logic
     */
    private function calculateLoanInterestForPeriod($loan, $startDate, $endDate)
    {
        if (!$loan->loan_product) {
            return 0;
        }

        $interestRate = $loan->loan_product->interest_rate / 100;
        $principalAmount = $loan->applied_amount;
        
        // Calculate the actual loan period within the cycle
        $loanStartDate = max($loan->release_date, $startDate);
        
        // FIXED: Use proper loan maturity date instead of first_payment_date
        $loanMaturityDate = $loan->maturity_date ?? 
                           ($loan->first_payment_date ? $loan->first_payment_date->addDays($loan->loan_product->term * 30) : null) ??
                           $loan->release_date->addDays($loan->loan_product->term * 30);
        
        $loanEndDate = min($loanMaturityDate, $endDate);
        
        if ($loanStartDate >= $loanEndDate) {
            return 0;
        }

        $daysActive = $loanStartDate->diffInDays($loanEndDate);
        
        // FIXED: Use centralized loan calculator for consistency
        return VslaLoanCalculator::calculateInterestForPeriod(
            $principalAmount, 
            $interestRate, 
            $daysActive, 
            $loan->loan_product->interest_type,
            $loan->total_paid
        );
    }

    /**
     * Validate cycle financial integrity before share-out
     */
    public function validateFinancialIntegrity()
    {
        $errors = [];
        
        // Ensure totals are calculated
        if ($this->total_shares_contributed == 0 && $this->total_welfare_contributed == 0) {
            $this->calculateTotals();
        }
        
        // Check if any members have contributions
        if ($this->total_shares_contributed <= 0) {
            $errors[] = 'No share contributions found for this cycle';
        }
        
        // Check if available amount is reasonable
        if ($this->total_available_for_shareout <= 0) {
            $errors[] = 'No funds available for share-out (total: ' . number_format($this->total_available_for_shareout, 2) . ')';
        }
        
        // Check participating members
        $participatingCount = $this->getParticipatingMembersCount();
        if ($participatingCount <= 0) {
            $errors[] = 'No participating members found for this cycle';
        }
        
        // Validate VSLA cashbox has sufficient balance
        $cashboxAccount = BankAccount::where('tenant_id', $this->tenant_id)
            ->where('bank_name', 'VSLA Internal')
            ->where('account_name', 'VSLA Main Account')
            ->first();
            
        if (!$cashboxAccount) {
            $errors[] = 'VSLA Main Cashbox Account not found';
        } elseif ($cashboxAccount->current_balance < $this->total_available_for_shareout) {
            $errors[] = 'Insufficient cashbox balance. Available: ' . 
                       number_format($cashboxAccount->current_balance, 2) . 
                       ', Required: ' . number_format($this->total_available_for_shareout, 2);
        }
        
        return $errors;
    }

    /**
     * Get cycle duration in days
     */
    public function getDurationInDays()
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDuration()
    {
        $days = $this->getDurationInDays();
        $months = floor($days / 30);
        $remainingDays = $days % 30;

        if ($months > 0) {
            return $months . ' month' . ($months > 1 ? 's' : '') . 
                   ($remainingDays > 0 ? ' and ' . $remainingDays . ' day' . ($remainingDays > 1 ? 's' : '') : '');
        }
        
        return $days . ' day' . ($days > 1 ? 's' : '');
    }
}
