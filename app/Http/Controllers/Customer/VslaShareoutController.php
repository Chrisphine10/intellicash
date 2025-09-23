<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\VslaCycle;
use App\Models\VslaShareout;
use App\Models\VslaTransaction;
use App\Models\Member;
use App\Models\SavingsAccount;
use Illuminate\Http\Request;
use Carbon\Carbon;

class VslaShareoutController extends Controller
{
    /**
     * Display a listing of VSLA cycles for the member
     */
    public function index()
    {
        $tenant = app('tenant');
        
        // Check if tenant has VSLA enabled
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('dashboard.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Get current member
        $member = auth()->user()->member;
        if (!$member) {
            return redirect()->route('dashboard.index')->with('error', _lang('Member profile not found'));
        }
        
        // Get all cycles for this tenant
        $cycles = VslaCycle::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Check if member has any VSLA activity
        $hasVslaActivity = VslaTransaction::where('tenant_id', $tenant->id)
            ->where('member_id', $member->id)
            ->exists();
        
        return view('backend.customer.vsla.shareout.index', compact('cycles', 'member', 'hasVslaActivity'));
    }

    /**
     * Show share out report for a specific cycle
     */
    public function show($cycle)
    {
        $tenant = app('tenant');
        
        // Check if tenant has VSLA enabled
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('dashboard.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Get current member
        $member = auth()->user()->member;
        if (!$member) {
            return redirect()->route('dashboard.index')->with('error', _lang('Member profile not found'));
        }
        
        // Get the cycle
        $cycleModel = VslaCycle::where('tenant_id', $tenant->id)
            ->where('id', $cycle)
            ->firstOrFail();
        
        // Get member's shareout data for this cycle
        $shareout = VslaShareout::where('cycle_id', $cycleModel->id)
            ->where('member_id', $member->id)
            ->first();
        
        // Get member's VSLA account balances
        $memberAccounts = $this->getMemberVslaAccounts($member, $tenant);
        
        // Get member's transaction summary for this cycle
        $transactionSummary = $this->getMemberTransactionSummary($member, $cycleModel, $tenant);
        
        // Calculate expected shareout if not yet processed
        $expectedShareout = $this->calculateExpectedShareout($member, $cycleModel, $tenant);
        
        // Get group summary data
        $groupSummary = $this->getGroupSummary($cycleModel, $tenant);
        
        // Get participating members summary
        $participatingMembers = $this->getParticipatingMembersOverview($cycleModel, $tenant);
        
        return view('backend.customer.vsla.shareout.show', compact(
            'cycleModel', 
            'member', 
            'shareout', 
            'memberAccounts', 
            'transactionSummary', 
            'expectedShareout',
            'groupSummary',
            'participatingMembers'
        ));
    }

    /**
     * Get member's VSLA account balances
     */
    private function getMemberVslaAccounts($member, $tenant)
    {
        $accounts = [];
        
        $vslaAccountTypes = ['VSLA Shares', 'VSLA Welfare', 'VSLA Projects', 'VSLA Others'];
        
        foreach ($vslaAccountTypes as $accountType) {
            $account = SavingsAccount::where('tenant_id', $tenant->id)
                ->where('member_id', $member->id)
                ->whereHas('savings_type', function($q) use ($accountType) {
                    $q->where('name', $accountType);
                })
                ->first();
            
            if ($account) {
                $balance = get_account_balance($account->id, $member->id);
                $accounts[$accountType] = [
                    'account' => $account,
                    'balance' => $balance
                ];
            }
        }
        
        return $accounts;
    }

    /**
     * Get member's transaction summary for a cycle
     */
    private function getMemberTransactionSummary($member, $cycle, $tenant)
    {
        $summary = [
            'total_shares_purchased' => 0,
            'total_shares_amount' => 0,
            'total_welfare_contributed' => 0,
            'total_penalties_paid' => 0,
            'total_loans_taken' => 0,
            'total_loans_repaid' => 0,
            'transaction_count' => 0
        ];
        
        // Get transactions within the cycle period
        $endDate = $cycle->end_date ?? now();
        $transactions = VslaTransaction::where('tenant_id', $tenant->id)
            ->where('member_id', $member->id)
            ->where('status', 'approved')
            ->whereBetween('created_at', [$cycle->start_date, $endDate])
            ->get();
        
        foreach ($transactions as $transaction) {
            $summary['transaction_count']++;
            
            switch ($transaction->transaction_type) {
                case 'share_purchase':
                    $summary['total_shares_purchased'] += $transaction->shares ?? 0;
                    $summary['total_shares_amount'] += $transaction->amount;
                    break;
                case 'welfare_contribution':
                    $summary['total_welfare_contributed'] += $transaction->amount;
                    break;
                case 'penalty_fine':
                    $summary['total_penalties_paid'] += $transaction->amount;
                    break;
                case 'loan_issuance':
                    $summary['total_loans_taken'] += $transaction->amount;
                    break;
                case 'loan_repayment':
                    $summary['total_loans_repaid'] += $transaction->amount;
                    break;
            }
        }
        
        return $summary;
    }

    /**
     * Calculate expected shareout for member
     */
    private function calculateExpectedShareout($member, $cycle, $tenant)
    {
        $expected = [
            'share_value' => 0,
            'welfare_return' => 0,
            'interest_earnings' => 0,
            'total_expected' => 0,
            'shares_owned' => 0
        ];
        
        // Get total shares in the cycle
        $endDate = $cycle->end_date ?? now();
        $totalShares = VslaTransaction::where('tenant_id', $tenant->id)
            ->where('transaction_type', 'share_purchase')
            ->where('status', 'approved')
            ->whereBetween('created_at', [$cycle->start_date, $endDate])
            ->sum('shares');
        
        // Get member's shares in this cycle
        $memberShares = VslaTransaction::where('tenant_id', $tenant->id)
            ->where('member_id', $member->id)
            ->where('transaction_type', 'share_purchase')
            ->where('status', 'approved')
            ->whereBetween('created_at', [$cycle->start_date, $endDate])
            ->sum('shares');
        
        $expected['shares_owned'] = $memberShares;
        
        if ($totalShares > 0 && $memberShares > 0) {
            // Calculate share percentage
            $sharePercentage = $memberShares / $totalShares;
            
            // Calculate expected returns based on cycle totals
            $expected['share_value'] = $cycle->total_shares_contributed * $sharePercentage;
            $expected['interest_earnings'] = $cycle->total_loan_interest_earned * $sharePercentage;
            $expected['welfare_return'] = $cycle->total_welfare_contributed * $sharePercentage;
            
            $expected['total_expected'] = $expected['share_value'] + 
                                        $expected['interest_earnings'] + 
                                        $expected['welfare_return'];
        }
        
        return $expected;
    }

    /**
     * Get group summary data for the cycle
     */
    private function getGroupSummary($cycle, $tenant)
    {
        $endDate = $cycle->end_date ?? now();
        
        // Get all transactions for this cycle
        $transactions = VslaTransaction::where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$cycle->start_date, $endDate])
            ->where('status', 'approved')
            ->get();
            
        // Get total participating members
        $totalMembers = VslaTransaction::where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$cycle->start_date, $endDate])
            ->where('transaction_type', 'share_purchase')
            ->where('status', 'approved')
            ->distinct('member_id')
            ->count('member_id');
        
        return [
            'total_members' => $totalMembers,
            'total_transactions' => $transactions->count(),
            'total_shares_sold' => $transactions->where('transaction_type', 'share_purchase')->sum('shares'),
            'total_share_value' => $transactions->where('transaction_type', 'share_purchase')->sum('amount'),
            'total_welfare_contributed' => $transactions->where('transaction_type', 'welfare_contribution')->sum('amount'),
            'total_penalties_collected' => $transactions->where('transaction_type', 'penalty_fine')->sum('amount'),
            'total_loans_issued' => $transactions->where('transaction_type', 'loan_issuance')->count(),
            'total_loan_amount' => $transactions->where('transaction_type', 'loan_issuance')->sum('amount'),
            'total_loan_repayments' => $transactions->where('transaction_type', 'loan_repayment')->sum('amount'),
            'net_loan_interest' => $transactions->where('transaction_type', 'loan_repayment')->sum('amount') - 
                                  $transactions->where('transaction_type', 'loan_issuance')->sum('amount'),
            'cycle_totals' => [
                'shares' => $cycle->total_shares_contributed,
                'welfare' => $cycle->total_welfare_contributed,
                'penalties' => $cycle->total_penalties_collected,
                'interest' => $cycle->total_loan_interest_earned,
                'total_fund' => $cycle->total_available_for_shareout
            ]
        ];
    }

    /**
     * Get participating members overview
     */
    private function getParticipatingMembersOverview($cycle, $tenant)
    {
        $endDate = $cycle->end_date ?? now();
        
        // Get member IDs who participated
        $memberIds = VslaTransaction::where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$cycle->start_date, $endDate])
            ->where('transaction_type', 'share_purchase')
            ->where('status', 'approved')
            ->distinct()
            ->pluck('member_id');

        // Get member data with their contributions
        $members = \App\Models\Member::whereIn('id', $memberIds)
            ->get()
            ->map(function ($member) use ($cycle, $endDate, $tenant) {
                $memberTransactions = VslaTransaction::where('tenant_id', $tenant->id)
                    ->where('member_id', $member->id)
                    ->whereBetween('created_at', [$cycle->start_date, $endDate])
                    ->where('status', 'approved')
                    ->get();

                $shareout = VslaShareout::where('cycle_id', $cycle->id)
                    ->where('member_id', $member->id)
                    ->first();

                $shares = $memberTransactions->where('transaction_type', 'share_purchase')->sum('shares');
                $shareAmount = $memberTransactions->where('transaction_type', 'share_purchase')->sum('amount');
                
                return [
                    'member' => $member,
                    'shares' => $shares,
                    'share_amount' => $shareAmount,
                    'welfare' => $memberTransactions->where('transaction_type', 'welfare_contribution')->sum('amount'),
                    'loans_taken' => $memberTransactions->where('transaction_type', 'loan_issuance')->sum('amount'),
                    'loans_repaid' => $memberTransactions->where('transaction_type', 'loan_repayment')->sum('amount'),
                    'penalties' => $memberTransactions->where('transaction_type', 'penalty_fine')->sum('amount'),
                    'shareout' => $shareout,
                    'share_percentage' => $shares > 0 && $cycle->total_shares_contributed > 0 ? ($shares / $cycle->total_shares_contributed * 100) : 0
                ];
            })
            ->sortByDesc('shares');

        return $members->take(10); // Show top 10 contributors
    }
}
