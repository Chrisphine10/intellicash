<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\VslaCycle;
use App\Models\VslaTransaction;
use App\Models\VslaShareout;
use App\Models\Member;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Carbon\Carbon;
use DataTables;

class VslaCycleController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        date_default_timezone_set(get_timezone());
    }

    /**
     * Display a listing of VSLA cycles for admin
     */
    public function index()
    {
        $assets = ['datatable'];
        return view('backend.admin.vsla.cycle.index', compact('assets'));
    }

    /**
     * Get cycles data for DataTable
     */
    public function getTableData(Request $request)
    {
        $cycles = VslaCycle::with(['createdUser'])
            ->orderBy('created_at', 'desc');

        return DataTables::eloquent($cycles)
            ->editColumn('cycle_name', function ($cycle) {
                return '<a href="' . route('vsla.cycles.show', $cycle->id) . '" class="text-primary font-weight-bold">' . $cycle->cycle_name . '</a>';
            })
            ->editColumn('start_date', function ($cycle) {
                return $cycle->start_date->format('M d, Y');
            })
            ->editColumn('end_date', function ($cycle) {
                return $cycle->end_date ? $cycle->end_date->format('M d, Y') : '<span class="text-muted">Ongoing</span>';
            })
            ->editColumn('status', function ($cycle) {
                $badges = [
                    'active' => 'success',
                    'completed' => 'primary',
                    'share_out_in_progress' => 'warning',
                    'archived' => 'secondary'
                ];
                $badge = $badges[$cycle->status] ?? 'secondary';
                return '<span class="badge badge-' . $badge . '">' . ucfirst(str_replace('_', ' ', $cycle->status)) . '</span>';
            })
            ->editColumn('total_available_for_shareout', function ($cycle) {
                return number_format($cycle->total_available_for_shareout, 2) . ' ' . get_base_currency();
            })
            ->addColumn('participating_members', function ($cycle) {
                return $cycle->getParticipatingMembersCount();
            })
            ->addColumn('phase', function ($cycle) {
                $phase = $cycle->getCurrentPhase();
                $badges = [
                    'active' => 'success',
                    'ready_for_shareout' => 'info',
                    'share_out' => 'warning',
                    'completed' => 'primary',
                    'archived' => 'secondary'
                ];
                $badge = $badges[$phase] ?? 'secondary';
                return '<span class="badge badge-' . $badge . '">' . ucfirst(str_replace('_', ' ', $phase)) . '</span>';
            })
            ->addColumn('action', function ($cycle) {
                $actions = '<a href="' . route('vsla.cycles.show', $cycle->id) . '" class="btn btn-sm btn-info" data-toggle="tooltip" title="View Details">
                    <i class="fas fa-eye"></i>
                </a>';
                
                if ($cycle->status === 'active' && $cycle->isEligibleForShareOut()) {
                    $actions .= ' <a href="' . route('vsla.cycles.create') . '?cycle_id=' . $cycle->id . '" class="btn btn-sm btn-warning ml-1" data-toggle="tooltip" title="Start Share-out">
                        <i class="fas fa-share-alt"></i>
                    </a>';
                }
                
                return $actions;
            })
            ->rawColumns(['cycle_name', 'end_date', 'status', 'phase', 'action'])
            ->make(true);
    }

    /**
     * Show VSLA cycle details for admin (tenant view)
     */
    public function show($id)
    {
        
        $cycle = VslaCycle::with(['createdUser', 'shareouts.member'])
            ->findOrFail($id);

        // Get cycle statistics
        $stats = $this->getCycleStatistics($cycle);
        
        // Get participating members with their contribution details
        $participatingMembers = $this->getParticipatingMembersWithDetails($cycle);
        
        // Get transaction summary by type
        $transactionSummary = $this->getTransactionSummaryByType($cycle);
        
        // Get recent transactions
        $recentTransactions = VslaTransaction::where('tenant_id', $cycle->tenant_id)
            ->with(['member', 'createdUser'])
            ->whereBetween('created_at', [$cycle->start_date, $cycle->end_date->copy()->addDay()])
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Get financial integrity status
        $financialErrors = $cycle->validateFinancialIntegrity();

        return view('backend.admin.vsla.cycle.show', compact(
            'cycle',
            'stats',
            'participatingMembers', 
            'transactionSummary',
            'recentTransactions',
            'financialErrors'
        ));
    }

    /**
     * Get cycle statistics
     */
    private function getCycleStatistics($cycle)
    {
        $endDate = $cycle->end_date ? $cycle->end_date->copy()->addDay() : now();
        
        return [
            'total_members' => $cycle->getParticipatingMembersCount(),
            'total_meetings' => $cycle->meetings()->count(),
            'total_transactions' => VslaTransaction::where('tenant_id', $cycle->tenant_id)
                ->whereBetween('created_at', [$cycle->start_date, $endDate])
                ->where('status', 'approved')
                ->count(),
            'total_shares_sold' => VslaTransaction::where('tenant_id', $cycle->tenant_id)
                ->whereBetween('created_at', [$cycle->start_date, $endDate])
                ->where('transaction_type', 'share_purchase')
                ->where('status', 'approved')
                ->sum('shares'),
            'total_loans_issued' => VslaTransaction::where('tenant_id', $cycle->tenant_id)
                ->whereBetween('created_at', [$cycle->start_date, $endDate])
                ->where('transaction_type', 'loan_issuance')
                ->where('status', 'approved')
                ->count(),
            'total_loan_amount' => VslaTransaction::where('tenant_id', $cycle->tenant_id)
                ->whereBetween('created_at', [$cycle->start_date, $endDate])
                ->where('transaction_type', 'loan_issuance')
                ->where('status', 'approved')
                ->sum('amount'),
            'outstanding_loans' => $this->getOutstandingLoans($cycle),
            'shareouts_processed' => $cycle->shareouts()->count(),
            'total_shareout_amount' => $cycle->shareouts()->sum('net_payout')
        ];
    }

    /**
     * Get participating members with their contribution details (optimized)
     */
    private function getParticipatingMembersWithDetails($cycle)
    {
        $endDate = $cycle->end_date ? $cycle->end_date->copy()->addDay() : now();
        
        // Use cycle_id if available for better performance
        $memberIdsQuery = VslaTransaction::where('tenant_id', $cycle->tenant_id)
            ->where('transaction_type', 'share_purchase')
            ->where('status', 'approved');

        if ($cycle->id) {
            $memberIdsQuery->where('cycle_id', $cycle->id);
        } else {
            $memberIdsQuery->whereBetween('created_at', [$cycle->start_date, $endDate]);
        }

        $memberIds = $memberIdsQuery->distinct()->pluck('member_id');

        // Eager load all related data to avoid N+1 queries
        $members = Member::whereIn('id', $memberIds)
            ->with(['vslaShareouts' => function($query) use ($cycle) {
                $query->where('cycle_id', $cycle->id);
            }])
            ->get();

        // Get all transactions for these members in one query
        $allTransactions = VslaTransaction::where('tenant_id', $cycle->tenant_id)
            ->whereIn('member_id', $memberIds)
            ->where('status', 'approved');

        if ($cycle->id) {
            $allTransactions->where('cycle_id', $cycle->id);
        } else {
            $allTransactions->whereBetween('created_at', [$cycle->start_date, $endDate]);
        }

        $transactionsByMember = $allTransactions->get()->groupBy('member_id');

        return $members->map(function ($member) use ($transactionsByMember, $cycle) {
            $transactions = $transactionsByMember->get($member->id, collect());
            $shareout = $member->vslaShareouts->first();

            return [
                'member' => $member,
                'shares_purchased' => $transactions->where('transaction_type', 'share_purchase')->sum('shares'),
                'share_amount_paid' => $transactions->where('transaction_type', 'share_purchase')->sum('amount'),
                'welfare_contributed' => $transactions->where('transaction_type', 'welfare_contribution')->sum('amount'),
                'penalties_paid' => $transactions->where('transaction_type', 'penalty_fine')->sum('amount'),
                'loans_taken' => $transactions->where('transaction_type', 'loan_issuance')->sum('amount'),
                'loans_repaid' => $transactions->where('transaction_type', 'loan_repayment')->sum('amount'),
                'transaction_count' => $transactions->count(),
                'shareout' => $shareout,
                'expected_payout' => $this->calculateExpectedMemberPayout($member, $cycle)
            ];
        })->sortByDesc('shares_purchased');
    }

    /**
     * Get transaction summary by type
     */
    private function getTransactionSummaryByType($cycle)
    {
        $endDate = $cycle->end_date ? $cycle->end_date->copy()->addDay() : now();
        
        $transactions = VslaTransaction::where('tenant_id', $cycle->tenant_id)
            ->whereBetween('created_at', [$cycle->start_date, $endDate])
            ->where('status', 'approved')
            ->get();

        return [
            'share_purchases' => [
                'count' => $transactions->where('transaction_type', 'share_purchase')->count(),
                'amount' => $transactions->where('transaction_type', 'share_purchase')->sum('amount'),
                'shares' => $transactions->where('transaction_type', 'share_purchase')->sum('shares')
            ],
            'welfare_contributions' => [
                'count' => $transactions->where('transaction_type', 'welfare_contribution')->count(),
                'amount' => $transactions->where('transaction_type', 'welfare_contribution')->sum('amount')
            ],
            'loan_issuances' => [
                'count' => $transactions->where('transaction_type', 'loan_issuance')->count(),
                'amount' => $transactions->where('transaction_type', 'loan_issuance')->sum('amount')
            ],
            'loan_repayments' => [
                'count' => $transactions->where('transaction_type', 'loan_repayment')->count(),
                'amount' => $transactions->where('transaction_type', 'loan_repayment')->sum('amount')
            ],
            'penalties' => [
                'count' => $transactions->where('transaction_type', 'penalty_fine')->count(),
                'amount' => $transactions->where('transaction_type', 'penalty_fine')->sum('amount')
            ]
        ];
    }

    /**
     * Calculate expected member payout
     */
    private function calculateExpectedMemberPayout($member, $cycle)
    {
        $endDate = $cycle->end_date ? $cycle->end_date->copy()->addDay() : now();
        
        // Get member's shares in this cycle
        $memberShares = VslaTransaction::where('tenant_id', $cycle->tenant_id)
            ->where('member_id', $member->id)
            ->where('transaction_type', 'share_purchase')
            ->where('status', 'approved')
            ->whereBetween('created_at', [$cycle->start_date, $endDate])
            ->sum('shares');

        // Get total shares in the cycle
        $totalShares = VslaTransaction::where('tenant_id', $cycle->tenant_id)
            ->where('transaction_type', 'share_purchase')
            ->where('status', 'approved')
            ->whereBetween('created_at', [$cycle->start_date, $endDate])
            ->sum('shares');

        if ($totalShares == 0) {
            return 0;
        }

        $sharePercentage = $memberShares / $totalShares;
        
        return $cycle->total_available_for_shareout * $sharePercentage;
    }

    /**
     * Get outstanding loans count
     */
    private function getOutstandingLoans($cycle)
    {
        $endDate = $cycle->end_date ? $cycle->end_date->copy()->addDay() : now();
        
        $loanIssuances = VslaTransaction::where('tenant_id', $cycle->tenant_id)
            ->whereBetween('created_at', [$cycle->start_date, $endDate])
            ->where('transaction_type', 'loan_issuance')
            ->where('status', 'approved')
            ->sum('amount');

        $loanRepayments = VslaTransaction::where('tenant_id', $cycle->tenant_id)
            ->whereBetween('created_at', [$cycle->start_date, $endDate])
            ->where('transaction_type', 'loan_repayment')
            ->where('status', 'approved')
            ->sum('amount');

        return max(0, $loanIssuances - $loanRepayments);
    }

    /**
     * Create a new cycle
     */
    public function create()
    {
        return view('backend.admin.vsla.cycle.create');
    }

    /**
     * Store a new cycle
     */
    public function store(Request $request)
    {
        $request->validate([
            'cycle_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'notes' => 'nullable|string|max:1000'
        ]);

        $tenant = app('tenant');
        
        $cycle = VslaCycle::create([
            'tenant_id' => $tenant->id,
            'cycle_name' => $request->cycle_name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => 'active',
            'notes' => $request->notes,
            'created_user_id' => auth()->id(),
            'total_shares_contributed' => 0,
            'total_welfare_contributed' => 0,
            'total_penalties_collected' => 0,
            'total_loan_interest_earned' => 0,
            'total_available_for_shareout' => 0,
        ]);

        return redirect()->route('vsla.cycles.show', $cycle->id)
            ->with('success', _lang('VSLA cycle created successfully'));
    }

    /**
     * Update cycle totals
     */
    public function updateTotals($id)
    {
        $cycle = VslaCycle::findOrFail($id);
        $cycle->calculateTotals();
        
        return response()->json([
            'result' => 'success',
            'message' => _lang('Cycle totals updated successfully'),
            'data' => [
                'total_shares_contributed' => number_format($cycle->total_shares_contributed, 2),
                'total_welfare_contributed' => number_format($cycle->total_welfare_contributed, 2),
                'total_penalties_collected' => number_format($cycle->total_penalties_collected, 2),
                'total_loan_interest_earned' => number_format($cycle->total_loan_interest_earned, 2),
                'total_available_for_shareout' => number_format($cycle->total_available_for_shareout, 2)
            ]
        ]);
    }

    /**
     * End a cycle
     */
    public function endCycle($id)
    {
        $cycle = VslaCycle::findOrFail($id);
        
        if ($cycle->status !== 'active') {
            return response()->json([
                'result' => 'error',
                'message' => _lang('Only active cycles can be ended')
            ]);
        }

        // Update totals before ending
        $cycle->calculateTotals();
        
        // Validate financial integrity
        $errors = $cycle->validateFinancialIntegrity();
        if (!empty($errors)) {
            return response()->json([
                'result' => 'error',
                'message' => _lang('Cannot end cycle due to financial integrity issues'),
                'errors' => $errors
            ]);
        }

        $cycle->update([
            'end_date' => now(),
            'status' => 'ready_for_shareout'
        ]);

        return response()->json([
            'result' => 'success',
            'message' => _lang('Cycle ended successfully. Ready for share-out process.')
        ]);
    }
}
