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

class VslaCycleController extends Controller
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
        
        return view('backend.customer.vsla.cycle.index', compact('cycles', 'member', 'hasVslaActivity'));
    }

    /**
     * Show VSLA cycle report for a specific cycle
     */
    public function show($tenant, $cycle_id)
    {
        // Get the actual cycle_id from route parameters
        $actual_cycle_id = request()->route('cycle_id');
        
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
        
        // Get the cycle using the correct ID from route parameters
        $cycle = VslaCycle::where('tenant_id', $tenant->id)
            ->where('id', $actual_cycle_id)
            ->first();
            
        if (!$cycle) {
            return redirect()->route('customer.vsla.cycle.index')->with('error', _lang('Cycle not found. ID: ' . $actual_cycle_id));
        }
        
        // Get member's shareout data for this cycle
        $shareout = VslaShareout::where('cycle_id', $cycle->id)
            ->where('member_id', $member->id)
            ->first();
        
        // Get member's VSLA account balances
        $memberAccounts = $this->getMemberVslaAccounts($member, $tenant);
        
        // Get member's transaction summary for this cycle
        $transactionSummary = $this->getMemberTransactionSummary($member, $cycle, $tenant);
        
        // Calculate expected shareout if not yet processed
        $expectedShareout = $this->calculateExpectedShareout($member, $cycle, $tenant);
        
        // Get complete cycle report data
        $completeCycleReport = $this->getCompleteCycleReport($cycle, $tenant);
        
        // Get member's current loan status
        $currentLoanStatus = $this->getMemberCurrentLoanStatus($member, $cycle, $tenant);
        
        // Get cycle performance metrics
        $cyclePerformance = $this->getCyclePerformanceMetrics($cycle, $tenant);
        
        return view('backend.customer.vsla.cycle.show', compact(
            'cycle', 
            'member', 
            'shareout', 
            'memberAccounts', 
            'transactionSummary', 
            'expectedShareout',
            'completeCycleReport',
            'currentLoanStatus',
            'cyclePerformance'
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
     * Get complete cycle report data
     */
    private function getCompleteCycleReport($cycle, $tenant)
    {
        $endDate = $cycle->end_date ?? now();
        
        // Get all members who participated in this cycle
        $participatingMembers = VslaTransaction::where('tenant_id', $tenant->id)
            ->where('transaction_type', 'share_purchase')
            ->where('status', 'approved')
            ->whereBetween('created_at', [$cycle->start_date, $endDate])
            ->with('member')
            ->get()
            ->groupBy('member_id')
            ->map(function($transactions) {
                return $transactions->first()->member;
            });

        // Calculate group totals
        $groupTotals = [
            'total_members' => $participatingMembers->count(),
            'total_shares' => VslaTransaction::where('tenant_id', $tenant->id)
                ->where('transaction_type', 'share_purchase')
                ->where('status', 'approved')
                ->whereBetween('created_at', [$cycle->start_date, $endDate])
                ->sum('shares'),
            'total_share_amount' => VslaTransaction::where('tenant_id', $tenant->id)
                ->where('transaction_type', 'share_purchase')
                ->where('status', 'approved')
                ->whereBetween('created_at', [$cycle->start_date, $endDate])
                ->sum('amount'),
            'total_welfare' => VslaTransaction::where('tenant_id', $tenant->id)
                ->where('transaction_type', 'welfare_contribution')
                ->where('status', 'approved')
                ->whereBetween('created_at', [$cycle->start_date, $endDate])
                ->sum('amount'),
            'total_loans_issued' => VslaTransaction::where('tenant_id', $tenant->id)
                ->where('transaction_type', 'loan_issuance')
                ->where('status', 'approved')
                ->whereBetween('created_at', [$cycle->start_date, $endDate])
                ->sum('amount'),
            'total_loan_repayments' => VslaTransaction::where('tenant_id', $tenant->id)
                ->where('transaction_type', 'loan_repayment')
                ->where('status', 'approved')
                ->whereBetween('created_at', [$cycle->start_date, $endDate])
                ->sum('amount'),
        ];

        return [
            'participating_members' => $participatingMembers,
            'group_totals' => $groupTotals,
            'cycle_duration_days' => $cycle->start_date->diffInDays($endDate),
            'average_share_per_member' => $groupTotals['total_members'] > 0 ? 
                round($groupTotals['total_shares'] / $groupTotals['total_members'], 2) : 0,
            'average_contribution_per_member' => $groupTotals['total_members'] > 0 ? 
                round($groupTotals['total_share_amount'] / $groupTotals['total_members'], 2) : 0,
        ];
    }

    /**
     * Get member's current loan status
     */
    private function getMemberCurrentLoanStatus($member, $cycle, $tenant)
    {
        $endDate = $cycle->end_date ?? now();
        
        // Get active loans
        $activeLoans = VslaTransaction::where('tenant_id', $tenant->id)
            ->where('member_id', $member->id)
            ->where('transaction_type', 'loan_issuance')
            ->where('status', 'approved')
            ->whereBetween('created_at', [$cycle->start_date, $endDate])
            ->get();

        $totalBorrowed = $activeLoans->sum('amount');
        $totalRepaid = VslaTransaction::where('tenant_id', $tenant->id)
            ->where('member_id', $member->id)
            ->where('transaction_type', 'loan_repayment')
            ->where('status', 'approved')
            ->whereBetween('created_at', [$cycle->start_date, $endDate])
            ->sum('amount');

        return [
            'total_borrowed' => $totalBorrowed,
            'total_repaid' => $totalRepaid,
            'outstanding_balance' => $totalBorrowed - $totalRepaid,
            'repayment_rate' => $totalBorrowed > 0 ? round(($totalRepaid / $totalBorrowed) * 100, 2) : 0,
            'active_loans_count' => $activeLoans->count(),
        ];
    }

    /**
     * Get cycle performance metrics
     */
    private function getCyclePerformanceMetrics($cycle, $tenant)
    {
        $endDate = $cycle->end_date ?? now();
        
        // Calculate cycle efficiency
        $totalContributions = $cycle->total_shares_contributed + $cycle->total_welfare_contributed;
        $totalDistributed = $cycle->total_available_for_shareout;
        $efficiency = $totalContributions > 0 ? round(($totalDistributed / $totalContributions) * 100, 2) : 0;

        // Calculate loan performance
        $totalLoanInterest = $cycle->total_loan_interest_earned;
        $totalLoansIssued = $cycle->total_loans_issued ?? 0;
        $interestRate = $totalLoansIssued > 0 ? round(($totalLoanInterest / $totalLoansIssued) * 100, 2) : 0;

        return [
            'cycle_efficiency' => $efficiency,
            'average_interest_rate' => $interestRate,
            'total_contributions' => $totalContributions,
            'total_distributed' => $totalDistributed,
            'profit_margin' => $totalContributions > 0 ? 
                round((($totalDistributed - $totalContributions) / $totalContributions) * 100, 2) : 0,
            'cycle_status' => $cycle->status,
            'days_remaining' => $cycle->end_date ? max(0, now()->diffInDays($cycle->end_date, false)) : null,
        ];
    }

    /**
     * Send complete cycle report via email and SMS
     */
    public function sendCompleteCycleReport($cycle_id)
    {
        $tenant = app('tenant');
        $member = auth()->user()->member;
        
        if (!$member) {
            return response()->json(['error' => 'Member profile not found'], 404);
        }

        // Get the actual cycle_id from route parameters
        $actual_cycle_id = request()->route('cycle_id');
        
        $cycle = VslaCycle::where('tenant_id', $tenant->id)
            ->where('id', $actual_cycle_id)
            ->first();
            
        if (!$cycle) {
            return response()->json(['error' => 'Cycle not found. ID: ' . $actual_cycle_id . ', Tenant ID: ' . $tenant->id], 404);
        }

        // Get all the data for the complete report
        $shareout = VslaShareout::where('cycle_id', $cycle->id)
            ->where('member_id', $member->id)
            ->first();
        
        $memberAccounts = $this->getMemberVslaAccounts($member, $tenant);
        $transactionSummary = $this->getMemberTransactionSummary($member, $cycle, $tenant);
        $expectedShareout = $this->calculateExpectedShareout($member, $cycle, $tenant);
        $completeCycleReport = $this->getCompleteCycleReport($cycle, $tenant);
        $currentLoanStatus = $this->getMemberCurrentLoanStatus($member, $cycle, $tenant);
        $cyclePerformance = $this->getCyclePerformanceMetrics($cycle, $tenant);

        // Send email notification if enabled
        if ($member->email_notifications ?? true) {
            $this->sendCycleReportEmail($member, $cycle, [
                'shareout' => $shareout,
                'memberAccounts' => $memberAccounts,
                'transactionSummary' => $transactionSummary,
                'expectedShareout' => $expectedShareout,
                'completeCycleReport' => $completeCycleReport,
                'currentLoanStatus' => $currentLoanStatus,
                'cyclePerformance' => $cyclePerformance,
            ]);
        }

        // Send SMS notification if enabled
        if ($member->sms_notifications ?? true) {
            $this->sendCycleReportSMS($member, $cycle, $expectedShareout, $shareout);
        }

        return response()->json(['success' => 'Cycle report sent successfully']);
    }

    /**
     * Send cycle report via email
     */
    private function sendCycleReportEmail($member, $cycle, $reportData)
    {
        try {
            // Check if VSLA cycle report template exists
            $template = \App\Models\EmailTemplate::where('slug', 'VSLA_CYCLE_REPORT')
                ->where('template_type', 'tenant')
                ->where('email_status', 1)
                ->first();

            if ($template) {
                // Use notification template system
                $this->sendTemplateEmail($member, $cycle, $template, $reportData);
            } else {
                // Fallback to custom email template
                $data = [
                    'member' => $member,
                    'cycle' => $cycle,
                    'reportData' => $reportData,
                    'tenant' => app('tenant'),
                ];

                \Mail::send('emails.vsla.cycle_report', $data, function($message) use ($member, $cycle) {
                    $message->to($member->email, $member->first_name . ' ' . $member->last_name)
                            ->subject('VSLA Cycle Report - ' . $cycle->cycle_name);
                });
            }

            \Log::info('VSLA Cycle Report Email Sent', [
                'member_id' => $member->id,
                'cycle_id' => $cycle->id,
                'email' => $member->email
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to send VSLA Cycle Report Email', [
                'member_id' => $member->id,
                'cycle_id' => $cycle->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send template-based email
     */
    private function sendTemplateEmail($member, $cycle, $template, $reportData)
    {
        $subject = $template->subject;
        $emailBody = $template->email_body;

        // Replace placeholders
        $replacements = [
            '{member_name}' => $member->first_name . ' ' . $member->last_name,
            '{cycle_name}' => $cycle->cycle_name,
            '{cycle_status}' => ucfirst($cycle->status),
            '{member_shares}' => number_format($reportData['expectedShareout']['shares_owned']),
            '{expected_return}' => number_format($reportData['expectedShareout']['total_expected'], 2),
            '{currency}' => get_base_currency(),
            '{company_name}' => app('tenant')->name,
        ];

        foreach ($replacements as $placeholder => $value) {
            $subject = str_replace($placeholder, $value, $subject);
            $emailBody = str_replace($placeholder, $value, $emailBody);
        }

        \Mail::send([], [], function($message) use ($member, $subject, $emailBody) {
            $message->to($member->email, $member->first_name . ' ' . $member->last_name)
                    ->subject($subject)
                    ->setBody($emailBody, 'text/html');
        });
    }

    /**
     * Send cycle report via SMS
     */
    private function sendCycleReportSMS($member, $cycle, $expectedShareout, $shareout)
    {
        try {
            $phone = $member->mobile_phone ?? $member->phone;
            
            if (!$phone) {
                \Log::warning('No phone number found for member', ['member_id' => $member->id]);
                return;
            }

            // Check if VSLA cycle report template exists
            $template = \App\Models\EmailTemplate::where('slug', 'VSLA_CYCLE_REPORT')
                ->where('template_type', 'tenant')
                ->where('sms_status', 1)
                ->first();

            if ($template) {
                // Use template-based SMS
                $message = $this->getTemplateSMS($member, $cycle, $template, $expectedShareout, $shareout);
            } else {
                // Fallback to custom SMS
                $totalAmount = $shareout ? $shareout->net_payout : $expectedShareout['total_expected'];
                $status = $shareout ? 'Completed' : 'Pending';
                
                $message = "VSLA Cycle Report - {$cycle->cycle_name}\n";
                $message .= "Status: {$status}\n";
                $message .= "Your Share: " . number_format($totalAmount, 2) . " " . get_base_currency() . "\n";
                $message .= "Shares Owned: " . number_format($expectedShareout['shares_owned']) . "\n";
                $message .= "Check your email for complete details.";
            }

            // Send SMS using your SMS service
            $this->sendSMS($phone, $message);

            \Log::info('VSLA Cycle Report SMS Sent', [
                'member_id' => $member->id,
                'cycle_id' => $cycle->id,
                'phone' => $phone
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to send VSLA Cycle Report SMS', [
                'member_id' => $member->id,
                'cycle_id' => $cycle->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get template-based SMS message
     */
    private function getTemplateSMS($member, $cycle, $template, $expectedShareout, $shareout)
    {
        $message = $template->sms_body;

        $totalAmount = $shareout ? $shareout->net_payout : $expectedShareout['total_expected'];
        $status = $shareout ? 'Completed' : 'Pending';

        // Replace placeholders
        $replacements = [
            '{member_name}' => $member->first_name . ' ' . $member->last_name,
            '{cycle_name}' => $cycle->cycle_name,
            '{cycle_status}' => $status,
            '{member_shares}' => number_format($expectedShareout['shares_owned']),
            '{expected_return}' => number_format($totalAmount, 2),
            '{currency}' => get_base_currency(),
            '{company_name}' => app('tenant')->name,
        ];

        foreach ($replacements as $placeholder => $value) {
            $message = str_replace($placeholder, $value, $message);
        }

        return $message;
    }

    /**
     * Send SMS message
     */
    private function sendSMS($phone, $message)
    {
        // Placeholder for SMS service integration
        // Replace with your actual SMS service (Twilio, AWS SNS, etc.)
        \Log::info('SMS would be sent', [
            'phone' => $phone,
            'message' => $message
        ]);
    }

    /**
     * Update notification preferences
     */
    public function updateNotificationPreferences(Request $request)
    {
        $member = auth()->user()->member;
        
        if (!$member) {
            return response()->json(['error' => 'Member profile not found'], 404);
        }

        $request->validate([
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'cycle_report_notifications' => 'boolean',
        ]);

        // Update member preferences
        $member->update([
            'email_notifications' => $request->email_notifications ?? true,
            'sms_notifications' => $request->sms_notifications ?? true,
            'cycle_report_notifications' => $request->cycle_report_notifications ?? true,
        ]);

        return response()->json(['success' => 'Notification preferences updated successfully']);
    }

    /**
     * Get notification preferences
     */
    public function getNotificationPreferences()
    {
        $member = auth()->user()->member;
        
        if (!$member) {
            return response()->json(['error' => 'Member profile not found'], 404);
        }

        return response()->json([
            'email_notifications' => $member->email_notifications ?? true,
            'sms_notifications' => $member->sms_notifications ?? true,
            'cycle_report_notifications' => $member->cycle_report_notifications ?? true,
        ]);
    }
}
