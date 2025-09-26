<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class VslaShareout extends Model
{
    use MultiTenant;

    protected $table = 'vsla_shareouts';

    protected $fillable = [
        'tenant_id',
        'cycle_id',
        'member_id',
        'total_shares_contributed',
        'total_welfare_contributed',
        'share_percentage',
        'share_value_payout',
        'profit_share',
        'welfare_refund',
        'total_payout',
        'outstanding_loan_balance',
        'net_payout',
        'payout_status',
        'notes',
        'savings_account_id',
        'transaction_id',
        'created_user_id',
        'approved_at',
        'paid_at',
    ];

    protected $casts = [
        'total_shares_contributed' => 'decimal:2',
        'total_welfare_contributed' => 'decimal:2',
        'share_percentage' => 'decimal:5',
        'share_value_payout' => 'decimal:2',
        'profit_share' => 'decimal:2',
        'welfare_refund' => 'decimal:2',
        'total_payout' => 'decimal:2',
        'outstanding_loan_balance' => 'decimal:2',
        'net_payout' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cycle()
    {
        return $this->belongsTo(VslaCycle::class, 'cycle_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function savingsAccount()
    {
        return $this->belongsTo(SavingsAccount::class, 'savings_account_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    public function scopeCalculated($query)
    {
        return $query->where('payout_status', 'calculated');
    }

    public function scopeApproved($query)
    {
        return $query->where('payout_status', 'approved');
    }

    public function scopePaid($query)
    {
        return $query->where('payout_status', 'paid');
    }

    public function scopeCancelled($query)
    {
        return $query->where('payout_status', 'cancelled');
    }

    /**
     * Calculate member's share-out for a specific cycle with improved logic
     */
    public static function calculateMemberShareOut($cycle, $member)
    {
        $startDate = $cycle->start_date;
        $endDate = $cycle->end_date ? $cycle->end_date->copy()->addDay() : now();

        // Calculate member's share contributions (use cycle_id if available, otherwise date range)
        $memberSharesQuery = VslaTransaction::where('tenant_id', $cycle->tenant_id)
            ->where('member_id', $member->id)
            ->where('transaction_type', 'share_purchase')
            ->where('status', 'approved');

        if ($cycle->id) {
            $memberSharesQuery->where('cycle_id', $cycle->id);
        } else {
            $memberSharesQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        $memberShares = $memberSharesQuery->sum('amount');

        // Calculate member's welfare contributions
        $memberWelfareQuery = VslaTransaction::where('tenant_id', $cycle->tenant_id)
            ->where('member_id', $member->id)
            ->where('transaction_type', 'welfare_contribution')
            ->where('status', 'approved');

        if ($cycle->id) {
            $memberWelfareQuery->where('cycle_id', $cycle->id);
        } else {
            $memberWelfareQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        $memberWelfare = $memberWelfareQuery->sum('amount');

        // Calculate share percentage based on actual share contributions
        $sharePercentage = $cycle->total_shares_contributed > 0 
            ? ($memberShares / $cycle->total_shares_contributed) 
            : 0;

        // Calculate profit share (percentage of total available profit)
        // FIXED: Corrected profit calculation logic
        // Profit = Loan interest earned (this is the actual VSLA profit)
        $totalProfit = $cycle->total_loan_interest_earned;
        
        $profitShare = max(0, $totalProfit * $sharePercentage);

        // Calculate outstanding loan balance (actual remaining balance)
        $outstandingLoans = Loan::where('tenant_id', $cycle->tenant_id)
            ->where('borrower_id', $member->id)
            ->whereIn('status', [1, 2]) // Active and disbursed loans
            ->get()
            ->sum(function($loan) {
                return max(0, $loan->total_payable - $loan->total_paid);
            });

        // Calculate payouts
        $shareValuePayout = $memberShares; // Member gets back their shares
        $welfareRefund = $memberWelfare;   // Member gets back their welfare
        $totalPayout = $shareValuePayout + $welfareRefund + $profitShare;
        $netPayout = max(0, $totalPayout - $outstandingLoans);

        return [
            'total_shares_contributed' => $memberShares,
            'total_welfare_contributed' => $memberWelfare,
            'share_percentage' => $sharePercentage,
            'share_value_payout' => $shareValuePayout,
            'profit_share' => $profitShare,
            'welfare_refund' => $welfareRefund,
            'total_payout' => $totalPayout,
            'outstanding_loan_balance' => $outstandingLoans,
            'net_payout' => $netPayout,
        ];
    }

    /**
     * Get formatted share percentage
     */
    public function getFormattedSharePercentage()
    {
        return number_format($this->share_percentage * 100, 3) . '%';
    }

    /**
     * Check if member has outstanding loans
     */
    public function hasOutstandingLoans()
    {
        return $this->outstanding_loan_balance > 0;
    }

    /**
     * Get the effective payout amount (considering loans)
     */
    public function getEffectivePayout()
    {
        return $this->net_payout;
    }
}
