<?php

namespace App\Http\Controllers;

use App\Models\VslaCycle;
use App\Models\VslaShareout;
use App\Models\VslaTransaction;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\SavingsAccount;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VslaShareOutController extends Controller
{
    /**
     * Display a listing of VSLA cycles
     */
    public function index(Request $request)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        $query = VslaCycle::where('tenant_id', $tenant->id)
            ->with(['createdUser', 'shareouts']);
        
        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        $cycles = $query->orderBy('created_at', 'desc')->paginate(20);
        
        return view('backend.admin.vsla.shareout.index', compact('cycles'));
    }

    /**
     * Show the form for creating a new cycle
     */
    public function create()
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Check if there's already an active cycle
        $activeCycle = VslaCycle::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->first();
        
        if ($activeCycle) {
            return redirect()->route('vsla.cycles.show', $activeCycle->id)
                ->with('info', _lang('There is already an active cycle. Complete or close the current cycle before creating a new one.'));
        }
        
        return view('backend.admin.vsla.shareout.create');
    }

    /**
     * Store a newly created cycle
     */
    public function store(Request $request)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        $validator = Validator::make($request->all(), [
            'cycle_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Check if there's already an active cycle
        $activeCycle = VslaCycle::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->first();
        
        if ($activeCycle) {
            return back()->with('error', _lang('There is already an active cycle. Complete or close the current cycle before creating a new one.'))->withInput();
        }

        DB::beginTransaction();

        try {
            $cycle = VslaCycle::create([
                'tenant_id' => $tenant->id,
                'cycle_name' => $request->cycle_name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'notes' => $request->notes,
                'status' => 'active',
                'created_user_id' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('vsla.cycles.show', $cycle->id)
                ->with('success', _lang('VSLA cycle created successfully'));

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()->with('error', _lang('An error occurred while creating the cycle: ') . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified cycle
     */
    public function show($tenant, $id)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Get the actual cycle_id from route parameters
        $actual_cycle_id = request()->route('id');
        
        $cycle = VslaCycle::where('tenant_id', $tenant->id)
            ->with(['createdUser', 'shareouts.member'])
            ->where('id', $actual_cycle_id)
            ->first();
            
        if (!$cycle) {
            return redirect()->route('vsla.cycles.index')->with('error', _lang('Cycle not found. ID: ' . $actual_cycle_id));
        }
        
        // Calculate totals if not already calculated
        if ($cycle->total_shares_contributed == 0) {
            $cycle->calculateTotals();
        }
        
        $participatingMembers = $cycle->getParticipatingMembers();
        
        // Get comprehensive cycle statistics
        $cycleStats = $this->getCycleStatistics($cycle, $tenant);
        
        // Get transaction breakdown by type
        $transactionBreakdown = $this->getTransactionBreakdown($cycle, $tenant);
        
        // Get member participation summary
        $memberParticipation = $this->getMemberParticipationSummary($cycle, $tenant);
        
        return view('backend.admin.vsla.shareout.show', compact(
            'cycle', 
            'participatingMembers', 
            'cycleStats', 
            'transactionBreakdown', 
            'memberParticipation'
        ));
    }

    /**
     * Calculate share-out for a cycle
     */
    public function calculate($tenant, $id)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Get the actual cycle_id from route parameters
        $actual_cycle_id = request()->route('id');
        
        $cycle = VslaCycle::where('tenant_id', $tenant->id)
            ->where('id', $actual_cycle_id)
            ->first();
            
        if (!$cycle) {
            return back()->with('error', _lang('Cycle not found. ID: ' . $actual_cycle_id));
        }
        
        if ($cycle->status !== 'active') {
            return back()->with('error', _lang('Can only calculate share-out for active cycles'));
        }
        
        if (!$cycle->isEligibleForShareOut()) {
            return back()->with('error', _lang('Cycle is not yet eligible for share-out. End date must have passed.'));
        }

        DB::beginTransaction();

        try {
            // Calculate cycle totals first
            $cycle->calculateTotals();
            
            // Validate financial integrity before proceeding
            $validationErrors = $cycle->validateFinancialIntegrity();
            if (!empty($validationErrors)) {
                DB::rollback();
                return back()->with('error', _lang('Cannot proceed with share-out: ') . implode(', ', $validationErrors));
            }
            
            // Update cycle status
            $cycle->update(['status' => 'share_out_in_progress']);
            
            // Get participating members
            $participatingMembers = $cycle->getParticipatingMembers();
            
            if ($participatingMembers->isEmpty()) {
                DB::rollback();
                return back()->with('error', _lang('No participating members found for this cycle'));
            }
            
            // Clear existing shareout calculations (if any)
            VslaShareout::where('cycle_id', $cycle->id)->delete();
            
            // Calculate share-out for each member
            foreach ($participatingMembers as $member) {
                $calculation = VslaShareout::calculateMemberShareOut($cycle, $member);
                
                VslaShareout::create([
                    'tenant_id' => $tenant->id,
                    'cycle_id' => $cycle->id,
                    'member_id' => $member->id,
                    'total_shares_contributed' => $calculation['total_shares_contributed'],
                    'total_welfare_contributed' => $calculation['total_welfare_contributed'],
                    'share_percentage' => $calculation['share_percentage'],
                    'share_value_payout' => $calculation['share_value_payout'],
                    'profit_share' => $calculation['profit_share'],
                    'welfare_refund' => $calculation['welfare_refund'],
                    'total_payout' => $calculation['total_payout'],
                    'outstanding_loan_balance' => $calculation['outstanding_loan_balance'],
                    'net_payout' => $calculation['net_payout'],
                    'payout_status' => 'calculated',
                    'created_user_id' => auth()->id(),
                ]);
            }

            DB::commit();

            return redirect()->route('vsla.cycles.show', $cycle->id)
                ->with('success', _lang('Share-out calculated successfully for') . ' ' . $participatingMembers->count() . ' ' . _lang('members'));

        } catch (\Exception $e) {
            DB::rollback();
            
            // Reset cycle status
            $cycle->update(['status' => 'active']);
            
            return back()->with('error', _lang('An error occurred while calculating share-out: ') . $e->getMessage());
        }
    }

    /**
     * Approve share-out calculations
     */
    public function approve($tenant, $id)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Get the actual cycle_id from route parameters
        $actual_cycle_id = request()->route('id');
        
        $cycle = VslaCycle::where('tenant_id', $tenant->id)
            ->where('id', $actual_cycle_id)
            ->first();
            
        if (!$cycle) {
            return back()->with('error', _lang('Cycle not found. ID: ' . $actual_cycle_id));
        }
        
        if ($cycle->status !== 'share_out_in_progress') {
            return back()->with('error', _lang('Cycle must be in share-out progress status to approve'));
        }

        DB::beginTransaction();

        try {
            // Update all shareout records to approved
            VslaShareout::where('cycle_id', $cycle->id)
                ->where('payout_status', 'calculated')
                ->update([
                    'payout_status' => 'approved',
                    'approved_at' => now(),
                ]);

            DB::commit();

            return back()->with('success', _lang('Share-out calculations approved successfully'));

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()->with('error', _lang('An error occurred while approving share-out: ') . $e->getMessage());
        }
    }

    /**
     * Process payouts (create transactions)
     */
    public function processPayout($tenant, $id)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Get the actual cycle_id from route parameters
        $actual_cycle_id = request()->route('id');
        
        $cycle = VslaCycle::where('tenant_id', $tenant->id)
            ->where('id', $actual_cycle_id)
            ->first();
            
        if (!$cycle) {
            return back()->with('error', _lang('Cycle not found. ID: ' . $actual_cycle_id));
        }
        
        if ($cycle->status !== 'share_out_in_progress') {
            return back()->with('error', _lang('Cycle must be in share-out progress status to process payouts'));
        }
        
        $approvedShareouts = VslaShareout::where('cycle_id', $cycle->id)
            ->where('payout_status', 'approved')
            ->with('member')
            ->get();
        
        if ($approvedShareouts->isEmpty()) {
            return back()->with('error', _lang('No approved share-out records found. Please approve calculations first.'));
        }

        DB::beginTransaction();

        try {
            $processedCount = 0;
            $errors = [];

            foreach ($approvedShareouts as $shareout) {
                try {
                    if ($shareout->net_payout > 0) {
                        $this->createPayoutTransaction($shareout, $tenant);
                    }
                    
                    $shareout->update([
                        'payout_status' => 'paid',
                        'paid_at' => now(),
                    ]);
                    
                    $processedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Member {$shareout->member->first_name} {$shareout->member->last_name}: " . $e->getMessage();
                }
            }

            if (!empty($errors)) {
                DB::rollback();
                return back()->with('error', _lang('Some payouts failed: ') . implode(', ', $errors));
            }

            // Update cycle status to completed
            $cycle->update([
                'status' => 'completed',
                'share_out_date' => now(),
            ]);

            DB::commit();

            return back()->with('success', _lang('Payouts processed successfully for') . ' ' . $processedCount . ' ' . _lang('members. Cycle completed.'));

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()->with('error', _lang('An error occurred while processing payouts: ') . $e->getMessage());
        }
    }

    /**
     * Create payout transaction for a member
     */
    private function createPayoutTransaction($shareout, $tenant)
    {
        // Get member's VSLA Shares Account
        $memberShareAccount = SavingsAccount::where('tenant_id', $tenant->id)
            ->where('member_id', $shareout->member_id)
            ->whereHas('savings_type', function($q) {
                $q->where('name', 'VSLA Shares');
            })
            ->first();
        
        if (!$memberShareAccount) {
            throw new \Exception('VSLA Shares Account not found for member. Please sync VSLA accounts first.');
        }

        // Get member's VSLA Welfare Account
        $memberWelfareAccount = SavingsAccount::where('tenant_id', $tenant->id)
            ->where('member_id', $shareout->member_id)
            ->whereHas('savings_type', function($q) {
                $q->where('name', 'VSLA Welfare');
            })
            ->first();
        
        // Get VSLA Main Cashbox Account
        $cashboxAccount = BankAccount::where('tenant_id', $tenant->id)
            ->where('bank_name', 'VSLA Internal')
            ->where('account_name', 'VSLA Main Account')
            ->first();
        
        if (!$cashboxAccount) {
            throw new \Exception('VSLA Main Cashbox Account not found');
        }

        // Validate cashbox has sufficient balance
        if ($cashboxAccount->current_balance < $shareout->net_payout) {
            throw new \Exception('Insufficient balance in VSLA cashbox. Available: ' . 
                number_format($cashboxAccount->current_balance, 2) . 
                ', Required: ' . number_format($shareout->net_payout, 2));
        }

        // Handle outstanding loans by automatic loan payments if applicable
        if ($shareout->outstanding_loan_balance > 0) {
            $this->processLoanDeductions($shareout, $tenant);
        }

        // Create detailed breakdown of payout transactions
        $transactionIds = [];

        // 1. Share value payout (if any after loan deductions)
        if ($shareout->share_value_payout > 0) {
            $shareTransaction = $this->createSharePayoutTransaction($shareout, $memberShareAccount, $tenant, 'shares');
            $transactionIds[] = $shareTransaction->id;
        }

        // 2. Welfare refund (if any after loan deductions)
        if ($shareout->welfare_refund > 0 && $memberWelfareAccount) {
            $welfareTransaction = $this->createSharePayoutTransaction($shareout, $memberWelfareAccount, $tenant, 'welfare');
            $transactionIds[] = $welfareTransaction->id;
        }

        // 3. Profit share payout (if any after loan deductions)
        if ($shareout->profit_share > 0) {
            $profitTransaction = $this->createSharePayoutTransaction($shareout, $memberShareAccount, $tenant, 'profit');
            $transactionIds[] = $profitTransaction->id;
        }

        // Create bank transaction (debit from cashbox) - only for net amount actually paid
        if ($shareout->net_payout > 0) {
            $bankTransaction = BankTransaction::create([
                'tenant_id' => $tenant->id,
                'trans_date' => now(),
                'bank_account_id' => $cashboxAccount->id,
                'amount' => $shareout->net_payout,
                'dr_cr' => 'dr',
                'type' => 'VSLA Share-Out Payout',
                'status' => BankTransaction::STATUS_APPROVED,
                'description' => 'VSLA Share-Out Payout - ' . $shareout->cycle->cycle_name . 
                               ' (Member: ' . $shareout->member->first_name . ' ' . $shareout->member->last_name . ')',
                'created_user_id' => auth()->id(),
            ]);

            // Update cashbox balance
            $cashboxAccount->current_balance -= $shareout->net_payout;
            $cashboxAccount->last_balance_update = now();
            $cashboxAccount->save();
        }

        // Update shareout record with transaction references
        $shareout->update([
            'savings_account_id' => $memberShareAccount->id,
            'transaction_id' => !empty($transactionIds) ? $transactionIds[0] : null,
        ]);
    }

    /**
     * Create individual component payout transaction
     */
    private function createSharePayoutTransaction($shareout, $savingsAccount, $tenant, $type)
    {
        $amounts = [
            'shares' => $shareout->share_value_payout,
            'welfare' => $shareout->welfare_refund,
            'profit' => $shareout->profit_share
        ];

        $descriptions = [
            'shares' => 'Share Value Return',
            'welfare' => 'Welfare Contribution Refund', 
            'profit' => 'Profit Share Distribution'
        ];

        $amount = $amounts[$type];
        $description = $descriptions[$type];

        if ($amount <= 0) {
            return null;
        }

        // Adjust for outstanding loans proportionally
        if ($shareout->outstanding_loan_balance > 0) {
            $adjustmentRatio = $shareout->net_payout / $shareout->total_payout;
            $amount = $amount * $adjustmentRatio;
        }

        if ($amount <= 0) {
            return null;
        }

        return Transaction::create([
            'tenant_id' => $tenant->id,
            'trans_date' => now(),
            'member_id' => $shareout->member_id,
            'savings_account_id' => $savingsAccount->id,
            'amount' => $amount,
            'dr_cr' => 'cr',
            'type' => 'VSLA Share-Out',
            'method' => 'Internal Transfer',
            'status' => 2, // Approved
            'note' => 'VSLA Share-Out: ' . $description . ' - ' . $shareout->cycle->cycle_name,
            'description' => $description . ' (Cycle: ' . $shareout->cycle->cycle_name . 
                           ($shareout->outstanding_loan_balance > 0 ? ', After loan deduction' : '') . ')',
            'created_user_id' => auth()->id(),
            'branch_id' => auth()->user()->branch_id ?? null,
        ]);
    }

    /**
     * Process loan deductions from share-out
     */
    private function processLoanDeductions($shareout, $tenant)
    {
        // Get member's active loans
        $activeLoans = Loan::where('tenant_id', $tenant->id)
            ->where('borrower_id', $shareout->member_id)
            ->whereIn('status', [1, 2]) // Active and disbursed loans
            ->get();

        $totalDeduction = 0;
        
        foreach ($activeLoans as $loan) {
            $remainingBalance = max(0, $loan->total_payable - $loan->total_paid);
            
            if ($remainingBalance > 0) {
                // Create loan payment transaction
                $paymentAmount = min($remainingBalance, $shareout->outstanding_loan_balance - $totalDeduction);
                
                if ($paymentAmount > 0) {
                    Transaction::create([
                        'tenant_id' => $tenant->id,
                        'trans_date' => now(),
                        'member_id' => $shareout->member_id,
                        'loan_id' => $loan->id,
                        'amount' => $paymentAmount,
                        'dr_cr' => 'cr',
                        'type' => 'Loan Payment',
                        'method' => 'Share-Out Deduction',
                        'status' => 2, // Approved
                        'note' => 'Automatic loan payment from share-out - ' . $shareout->cycle->cycle_name,
                        'description' => 'Loan payment deducted from VSLA share-out (Loan ID: ' . $loan->loan_id . ')',
                        'created_user_id' => auth()->id(),
                        'branch_id' => auth()->user()->branch_id ?? null,
                    ]);

                    // Update loan balance
                    $loan->total_paid += $paymentAmount;
                    $loan->save();

                    $totalDeduction += $paymentAmount;

                    if ($totalDeduction >= $shareout->outstanding_loan_balance) {
                        break;
                    }
                }
            }
        }
    }

    /**
     * Get comprehensive cycle statistics
     */
    private function getCycleStatistics($cycle, $tenant)
    {
        $startDate = $cycle->start_date;
        $endDate = $cycle->end_date ?? now();
        
        $stats = [
            'duration_days' => $startDate->diffInDays($endDate),
            'total_transactions' => 0,
            'unique_members' => 0,
            'total_meetings' => 0,
            'average_transaction_amount' => 0,
            'most_active_member' => null,
            'transaction_frequency' => 0,
        ];
        
        // Get all transactions for this cycle
        $transactions = VslaTransaction::where('tenant_id', $tenant->id)
            ->where('status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('member')
            ->get();
        
        $stats['total_transactions'] = $transactions->count();
        $stats['unique_members'] = $transactions->pluck('member_id')->unique()->count();
        $stats['average_transaction_amount'] = $transactions->count() > 0 ? $transactions->avg('amount') : 0;
        
        // Calculate transaction frequency (transactions per week)
        $weeks = max(1, $startDate->diffInWeeks($endDate));
        $stats['transaction_frequency'] = $transactions->count() / $weeks;
        
        // Find most active member
        $memberActivity = $transactions->groupBy('member_id')
            ->map(function ($memberTransactions) {
                return [
                    'member' => $memberTransactions->first()->member,
                    'transaction_count' => $memberTransactions->count(),
                    'total_amount' => $memberTransactions->sum('amount'),
                ];
            })
            ->sortByDesc('transaction_count');
        
        $stats['most_active_member'] = $memberActivity->first();
        
        // Get meetings count
        $stats['total_meetings'] = \App\Models\VslaMeeting::where('tenant_id', $tenant->id)
            ->whereBetween('meeting_date', [$startDate, $endDate])
            ->count();
        
        return $stats;
    }
    
    /**
     * Get transaction breakdown by type
     */
    private function getTransactionBreakdown($cycle, $tenant)
    {
        $startDate = $cycle->start_date;
        $endDate = $cycle->end_date ?? now();
        
        $breakdown = VslaTransaction::where('tenant_id', $tenant->id)
            ->where('status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('transaction_type, COUNT(*) as count, SUM(amount) as total_amount, SUM(shares) as total_shares')
            ->groupBy('transaction_type')
            ->get()
            ->keyBy('transaction_type');
        
        return $breakdown;
    }
    
    /**
     * Get member participation summary
     */
    private function getMemberParticipationSummary($cycle, $tenant)
    {
        $startDate = $cycle->start_date;
        $endDate = $cycle->end_date ?? now();
        
        $memberSummary = VslaTransaction::where('tenant_id', $tenant->id)
            ->where('status', 'approved')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('member')
            ->get()
            ->groupBy('member_id')
            ->map(function ($transactions) use ($startDate, $endDate, $tenant) {
                $member = $transactions->first()->member;
                $summary = [
                    'member' => $member,
                    'total_transactions' => $transactions->count(),
                    'total_amount' => $transactions->sum('amount'),
                    'total_shares' => $transactions->where('transaction_type', 'share_purchase')->sum('shares'),
                    'share_amount' => $transactions->where('transaction_type', 'share_purchase')->sum('amount'),
                    'welfare_amount' => $transactions->where('transaction_type', 'welfare_contribution')->sum('amount'),
                    'penalty_amount' => $transactions->where('transaction_type', 'penalty_fine')->sum('amount'),
                    'loans_taken' => $transactions->where('transaction_type', 'loan_issuance')->sum('amount'),
                    'loans_repaid' => $transactions->where('transaction_type', 'loan_repayment')->sum('amount'),
                ];
                
                // Calculate share percentage
                $totalShares = VslaTransaction::where('tenant_id', $tenant->id)
                    ->where('transaction_type', 'share_purchase')
                    ->where('status', 'approved')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('shares');
                
                $summary['share_percentage'] = $totalShares > 0 ? ($summary['total_shares'] / $totalShares) * 100 : 0;
                
                return $summary;
            })
            ->sortByDesc('total_shares');
        
        return $memberSummary;
    }

    /**
     * Cancel share-out calculations
     */
    public function cancel($tenant, $id)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Get the actual cycle_id from route parameters
        $actual_cycle_id = request()->route('id');
        
        $cycle = VslaCycle::where('tenant_id', $tenant->id)
            ->where('id', $actual_cycle_id)
            ->first();
            
        if (!$cycle) {
            return back()->with('error', _lang('Cycle not found. ID: ' . $actual_cycle_id));
        }
        
        if ($cycle->status !== 'share_out_in_progress') {
            return back()->with('error', _lang('Can only cancel share-out for cycles in progress'));
        }

        DB::beginTransaction();

        try {
            // Delete shareout calculations
            VslaShareout::where('cycle_id', $cycle->id)->delete();
            
            // Reset cycle status to active
            $cycle->update(['status' => 'active']);

            DB::commit();

            return back()->with('success', _lang('Share-out calculations cancelled. Cycle is now active again.'));

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()->with('error', _lang('An error occurred while cancelling share-out: ') . $e->getMessage());
        }
    }

    /**
     * Export share-out report
     */
    public function exportReport($tenant, $id)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Get the actual cycle_id from route parameters
        $actual_cycle_id = request()->route('id');
        
        $cycle = VslaCycle::where('tenant_id', $tenant->id)
            ->with(['shareouts.member'])
            ->where('id', $actual_cycle_id)
            ->first();
            
        if (!$cycle) {
            return back()->with('error', _lang('Cycle not found. ID: ' . $actual_cycle_id));
        }
        
        return view('backend.admin.vsla.shareout.report', compact('cycle'));
    }
}
