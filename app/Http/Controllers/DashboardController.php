<?php
namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller {
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_timezone());
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index() {
        $user           = auth()->user();
        $user_type      = $user->user_type;
        $tenant_id      = $user->tenant_id;
        $date           = date('Y-m-d');
        $data           = [];
        $data['assets'] = ['datatable', 'chart'];

        // Security: Ensure tenant isolation for all queries
        if (!$tenant_id && $user_type !== 'superadmin') {
            abort(403, 'Tenant access required');
        }

        if ($user_type == 'customer') {
            // Secure customer queries with tenant validation
            $data['recent_transactions'] = Transaction::where('member_id', $user->member->id)
                ->where('tenant_id', $tenant_id)
                ->limit('10')
                ->orderBy('trans_date', 'desc')
                ->get();
            $data['loans'] = Loan::where('status', 1)
                ->where('borrower_id', $user->member->id)
                ->where('tenant_id', $tenant_id)
                ->get();

            // Check if mobile PWA request
            if (request()->get('mobile') == '1' || request()->header('X-Mobile-App') == '1') {
                return view("backend.customer.mobile-dashboard", $data);
            }

            return view("backend.customer.dashboard-$user_type", $data);
        } else {
            // Get date range from request or default to current month
            $dateRange = $this->getDateRange();
            $startDate = $dateRange['start'];
            $endDate = $dateRange['end'];

            // Secure admin queries with tenant isolation
            $data['recent_transactions'] = Transaction::where('tenant_id', $tenant_id)
                ->limit('10')
                ->orderBy('trans_date', 'desc')
                ->get();

            $data['due_repayments'] = LoanRepayment::selectRaw('loan_repayments.loan_id, MAX(repayment_date) as repayment_date, COUNT(id) as total_due_repayment, SUM(principal_amount) as total_due')
                ->with('loan')
                ->where('tenant_id', $tenant_id)
                ->where('repayment_date', '<', $date)
                ->where('status', 0)
                ->groupBy('loan_id')
                ->get();

            $data['loan_balances'] = Loan::where('status', 1)
                ->where('tenant_id', $tenant_id)
                ->selectRaw('currency_id, SUM(applied_amount) as total_amount, SUM(total_paid) as total_paid')
                ->with('currency')
                ->groupBy('currency_id')
                ->get();

            // Enhanced Analytics Data - OPTIMIZED with tenant security
            $cacheKey = 'dashboard_stats_' . $tenant_id;
            
            // Get basic stats in a single cached query with tenant isolation
            $basicStats = Cache::remember($cacheKey, 300, function () use ($tenant_id) { // 5 minutes cache
                // Get member stats in single query with tenant validation
                $memberStats = Member::where('tenant_id', $tenant_id)
                    ->selectRaw('
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active
                    ')->first();

                // Get loan stats in single query with tenant validation
                $loanStats = Loan::where('tenant_id', $tenant_id)
                    ->selectRaw('
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active
                    ')->first();

                // Get transaction count with tenant validation
                $transactionCount = Transaction::where('tenant_id', $tenant_id)->count();

                return [
                    'members' => $memberStats,
                    'loans' => $loanStats,
                    'transactions' => $transactionCount
                ];
            });

            $data['total_customer'] = $basicStats['members']->total;
            $data['active_members'] = $basicStats['members']->active;
            $data['total_loans'] = $basicStats['loans']->total;
            $data['active_loans'] = $basicStats['loans']->active;
            $data['total_transactions'] = $basicStats['transactions'];
            $data['pending_loans'] = $basicStats['loans']->pending;
            
            // Get remaining analytics data (already optimized with caching)
            $data['overdue_loans'] = $this->getOverdueLoans();
            $data['monthly_revenue'] = $this->getMonthlyRevenue($startDate, $endDate);
            $data['member_growth'] = $this->getMemberGrowth($startDate, $endDate);
            $data['loan_performance'] = $this->getLoanPerformance($startDate, $endDate);
            $data['transaction_volume'] = $this->getTransactionVolume($startDate, $endDate);
            $data['top_members'] = $this->getTopMembers();
            $data['branch_performance'] = $this->getBranchPerformance($startDate, $endDate);
            $data['currency_breakdown'] = $this->getCurrencyBreakdown();
            $data['date_range'] = $dateRange;

            // Load module-specific analytics only if modules are enabled
            $data['asset_summary'] = is_module_enabled('asset_management') ? $this->getAssetSummary() : null;
            $data['employee_summary'] = is_module_enabled('payroll') ? $this->getEmployeeSummary() : null;
            $data['vsla_summary'] = is_module_enabled('vsla') ? $this->getVslaSummary() : null;
            $data['voting_summary'] = is_module_enabled('voting') ? $this->getVotingSummary() : null;
            $data['esignature_summary'] = is_module_enabled('esignature') ? $this->getESignatureSummary() : null;

            return view("backend.admin.dashboard-$user_type", $data);
        }
    }

    public function dashboard_widget() {
        return redirect()->route('dashboard.index');
    }

    public function json_expense_by_category() {
        $transactions = Expense::selectRaw('expense_category_id, IFNULL(SUM(amount), 0) as amount')
            ->with('expense_category')
            ->whereRaw('MONTH(expense_date) = ?', [date('m')])
            ->whereRaw('YEAR(expense_date) = ?', [date('Y')])
            ->groupBy('expense_category_id')
            ->get();
        $category = [];
        $colors   = [];
        $amounts  = [];
        $data     = [];

        foreach ($transactions as $transaction) {
            array_push($category, $transaction->expense_category->name);
            array_push($colors, $transaction->expense_category->color);
            array_push($amounts, (double) $transaction->amount);
        }

        return response()->json([
            'amounts' => $amounts, 
            'category' => $category, 
            'colors' => $colors
        ]);

    }

    public function json_deposit_withdraw_analytics($currency_id) {
        $months       = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        $transactions = Transaction::whereHas('account.savings_type', function (Builder $query) use ($currency_id) {
            $query->where('currency_id', $currency_id);
        })
            ->selectRaw('MONTH(trans_date) as td, type, IFNULL(SUM(amount), 0) as amount')
            ->whereRaw("(type = 'Deposit' OR type = 'Withdraw') AND status = 2")
            ->whereRaw('YEAR(trans_date) = ?', [date('Y')])
            ->groupBy('td', 'type')
            ->get();

        $deposit  = [];
        $withdraw = [];

        foreach ($transactions as $transaction) {
            if ($transaction->type == 'Deposit') {
                $deposit[$transaction->td] = $transaction->amount;
            } else if ($transaction->type == 'Withdraw') {
                $withdraw[$transaction->td] = $transaction->amount;
            }
        }

        return response()->json([
            'month' => $months, 
            'deposit' => $deposit, 
            'withdraw' => $withdraw
        ]);
    }

    /**
     * Get date range for analytics filtering
     */
    private function getDateRange() {
        $range = request()->get('range', 'this_month');
        
        switch ($range) {
            case 'today':
                return [
                    'start' => date('Y-m-d'),
                    'end' => date('Y-m-d'),
                    'label' => 'Today'
                ];
            case 'yesterday':
                return [
                    'start' => date('Y-m-d', strtotime('-1 day')),
                    'end' => date('Y-m-d', strtotime('-1 day')),
                    'label' => 'Yesterday'
                ];
            case 'last_7_days':
                return [
                    'start' => date('Y-m-d', strtotime('-7 days')),
                    'end' => date('Y-m-d'),
                    'label' => 'Last 7 Days'
                ];
            case 'last_30_days':
                return [
                    'start' => date('Y-m-d', strtotime('-30 days')),
                    'end' => date('Y-m-d'),
                    'label' => 'Last 30 Days'
                ];
            case 'this_month':
                return [
                    'start' => date('Y-m-01'),
                    'end' => date('Y-m-t'),
                    'label' => 'This Month'
                ];
            case 'last_month':
                return [
                    'start' => date('Y-m-01', strtotime('-1 month')),
                    'end' => date('Y-m-t', strtotime('-1 month')),
                    'label' => 'Last Month'
                ];
            case 'this_year':
                return [
                    'start' => date('Y-01-01'),
                    'end' => date('Y-12-31'),
                    'label' => 'This Year'
                ];
            default:
                return [
                    'start' => date('Y-m-01'),
                    'end' => date('Y-m-t'),
                    'label' => 'This Month'
                ];
        }
    }

    /**
     * Get overdue loans count
     */
    private function getOverdueLoans() {
        $tenantId = request()->tenant->id ?? auth()->user()->tenant_id ?? null;
        $cacheKey = 'overdue_loans_' . $tenantId;
        
        return Cache::remember($cacheKey, 300, function () { // 5 minutes cache
            return LoanRepayment::where('status', 0)
                ->where('repayment_date', '<', date('Y-m-d'))
                ->count();
        });
    }

    /**
     * Get monthly revenue data
     */
    private function getMonthlyRevenue($startDate, $endDate) {
        $tenantId = request()->tenant->id ?? auth()->user()->tenant_id ?? null;
        $cacheKey = 'monthly_revenue_' . $tenantId . '_' . $startDate . '_' . $endDate;
        
        return Cache::remember($cacheKey, 600, function () use ($startDate, $endDate) { // 10 minutes cache
            return Transaction::where('type', 'Deposit')
                ->where('status', 2)
                ->whereBetween('trans_date', [$startDate, $endDate])
                ->sum('amount');
        });
    }

    /**
     * Get member growth data
     */
    private function getMemberGrowth($startDate, $endDate) {
        $tenantId = request()->tenant->id ?? auth()->user()->tenant_id ?? null;
        $cacheKey = 'member_growth_' . $tenantId . '_' . $startDate . '_' . $endDate;
        
        return Cache::remember($cacheKey, 900, function () use ($startDate, $endDate) { // 15 minutes cache
            $current = Member::whereBetween('created_at', [$startDate, $endDate])->count();
            $previous = Member::whereBetween('created_at', [
                date('Y-m-d', strtotime($startDate . ' -1 month')),
                date('Y-m-d', strtotime($endDate . ' -1 month'))
            ])->count();

            return [
                'current' => $current,
                'previous' => $previous,
                'growth' => $previous > 0 ? round((($current - $previous) / $previous) * 100, 2) : 0
            ];
        });
    }

    /**
     * Get loan performance metrics - OPTIMIZED
     */
    private function getLoanPerformance($startDate, $endDate) {
        $tenantId = request()->tenant->id ?? auth()->user()->tenant_id ?? null;
        $cacheKey = 'loan_performance_' . $tenantId;
        
        return Cache::remember($cacheKey, 600, function () { // 10 minutes cache
            // Single query to get all loan counts by status
            $loanStats = Loan::selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) as defaulted
            ')->first();

            $totalLoans = $loanStats->total;
            $activeLoans = $loanStats->active;
            $completedLoans = $loanStats->completed;
            $defaultedLoans = $loanStats->defaulted;

            return [
                'total' => $totalLoans,
                'active' => $activeLoans,
                'completed' => $completedLoans,
                'defaulted' => $defaultedLoans,
                'success_rate' => $totalLoans > 0 ? round(($completedLoans / $totalLoans) * 100, 2) : 0,
                'default_rate' => $totalLoans > 0 ? round(($defaultedLoans / $totalLoans) * 100, 2) : 0
            ];
        });
    }

    /**
     * Get transaction volume data - OPTIMIZED
     */
    private function getTransactionVolume($startDate, $endDate) {
        $tenantId = request()->tenant->id ?? auth()->user()->tenant_id ?? null;
        $cacheKey = 'transaction_volume_' . $tenantId . '_' . $startDate . '_' . $endDate;
        
        return Cache::remember($cacheKey, 300, function () use ($startDate, $endDate) { // 5 minutes cache
            return Transaction::whereBetween('trans_date', [$startDate, $endDate])
                ->where('status', 2)
                ->selectRaw('type, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('type')
                ->get()
                ->keyBy('type');
        });
    }

    /**
     * Get asset summary - OPTIMIZED
     */
    private function getAssetSummary() {
        if (!class_exists('App\Models\Asset')) {
            return null;
        }

        $tenantId = request()->tenant->id ?? auth()->user()->tenant_id ?? null;
        $cacheKey = 'asset_summary_' . $tenantId;
        
        return Cache::remember($cacheKey, 1800, function () { // 30 minutes cache
            // Use single query with aggregation instead of loading all records
            $assetStats = \App\Models\Asset::selectRaw('
                COUNT(*) as total,
                SUM(purchase_value) as total_value,
                SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN is_leasable = 1 THEN 1 ELSE 0 END) as leasable
            ')->first();

            $categories = \App\Models\Asset::selectRaw('category_id, COUNT(*) as count')
                ->groupBy('category_id')
                ->pluck('count', 'category_id');

            return [
                'total' => $assetStats->total,
                'total_value' => $assetStats->total_value,
                'active' => $assetStats->active,
                'leasable' => $assetStats->leasable,
                'categories' => $categories
            ];
        });
    }

    /**
     * Get employee summary - OPTIMIZED
     */
    private function getEmployeeSummary() {
        if (!class_exists('App\Models\Employee')) {
            return null;
        }

        $tenantId = request()->tenant->id ?? auth()->user()->tenant_id ?? null;
        $cacheKey = 'employee_summary_' . $tenantId;
        
        return Cache::remember($cacheKey, 1800, function () { // 30 minutes cache
            // Use single query with aggregation instead of loading all records
            $employeeStats = \App\Models\Employee::selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
            ')->first();

            $departments = \App\Models\Employee::selectRaw('department, COUNT(*) as count')
                ->groupBy('department')
                ->pluck('count', 'department');

            $employmentTypes = \App\Models\Employee::selectRaw('employment_type, COUNT(*) as count')
                ->groupBy('employment_type')
                ->pluck('count', 'employment_type');

            return [
                'total' => $employeeStats->total,
                'active' => $employeeStats->active,
                'departments' => $departments,
                'employment_types' => $employmentTypes
            ];
        });
    }

    /**
     * Get top performing members
     */
    private function getTopMembers() {
        $tenantId = request()->tenant->id ?? auth()->user()->tenant_id ?? null;
        $cacheKey = 'top_members_' . $tenantId;
        
        return Cache::remember($cacheKey, 1200, function () { // 20 minutes cache
            return Member::withCount(['transactions' => function($query) {
                $query->where('status', 2)->where('dr_cr', 'cr');
            }])
            ->withSum(['transactions as total_deposits' => function($query) {
                $query->where('status', 2)->where('dr_cr', 'cr');
            }], 'amount')
            ->orderBy('total_deposits', 'desc')
            ->limit(10)
            ->get();
        });
    }

    /**
     * Get branch performance - OPTIMIZED
     */
    private function getBranchPerformance($startDate, $endDate) {
        $tenantId = request()->tenant->id ?? auth()->user()->tenant_id ?? null;
        $cacheKey = 'branch_performance_' . $tenantId . '_' . $startDate . '_' . $endDate;
        
        return Cache::remember($cacheKey, 900, function () use ($startDate, $endDate) { // 15 minutes cache
            // Get all branches with member counts
            $branches = \App\Models\Branch::withCount(['members' => function($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }])->get();

            // Get all transaction totals for branches in a single query
            $branchTransactions = Transaction::selectRaw('
                branches.id as branch_id,
                SUM(transactions.amount) as total_transactions
            ')
            ->join('members', 'transactions.member_id', '=', 'members.id')
            ->join('branches', 'members.branch_id', '=', 'branches.id')
            ->whereBetween('transactions.trans_date', [$startDate, $endDate])
            ->where('transactions.status', 2)
            ->groupBy('branches.id')
            ->pluck('total_transactions', 'branch_id');

            // Map transaction totals to branches
            return $branches->map(function($branch) use ($branchTransactions) {
                $branch->total_transactions = $branchTransactions->get($branch->id, 0);
                return $branch;
            });
        });
    }

    /**
     * Get currency breakdown - OPTIMIZED
     */
    private function getCurrencyBreakdown() {
        $tenantId = request()->tenant->id ?? auth()->user()->tenant_id ?? null;
        $cacheKey = 'currency_breakdown_' . $tenantId;
        
        return Cache::remember($cacheKey, 1200, function () { // 20 minutes cache
            // Get all active currencies
            $currencies = \App\Models\Currency::where('status', 1)->get();

            // Get all transaction stats for currencies in a single query
            $currencyStats = Transaction::selectRaw('
                savings_products.currency_id,
                COUNT(transactions.id) as transactions_count,
                SUM(transactions.amount) as total_amount
            ')
            ->join('savings_accounts', 'transactions.savings_account_id', '=', 'savings_accounts.id')
            ->join('savings_products', 'savings_accounts.savings_product_id', '=', 'savings_products.id')
            ->where('transactions.status', 2)
            ->groupBy('savings_products.currency_id')
            ->get()
            ->keyBy('currency_id');

            // Map stats to currencies
            return $currencies->map(function($currency) use ($currencyStats) {
                $stats = $currencyStats->get($currency->id);
                
                return (object) [
                    'id' => $currency->id,
                    'name' => $currency->name,
                    'transactions_count' => $stats ? $stats->transactions_count : 0,
                    'total_amount' => $stats ? $stats->total_amount : 0
                ];
            });
        });
    }

    /**
     * AJAX endpoint for analytics data
     */
    public function analytics_data() {
        $type = request()->get('type');
        $dateRange = $this->getDateRange();
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        switch ($type) {
            case 'member_growth':
                return response()->json($this->getMemberGrowthChart($startDate, $endDate));
            case 'loan_performance':
                return response()->json($this->getLoanPerformanceChart($startDate, $endDate));
            case 'transaction_trends':
                return response()->json($this->getTransactionTrendsChart($startDate, $endDate));
            case 'revenue_breakdown':
                return response()->json($this->getRevenueBreakdownChart($startDate, $endDate));
            default:
                return response()->json(['error' => 'Invalid analytics type']);
        }
    }

    /**
     * Get VSLA summary - OPTIMIZED
     */
    private function getVslaSummary() {
        $tenantId = request()->tenant->id ?? auth()->user()->tenant_id ?? null;
        $cacheKey = 'vsla_summary_' . $tenantId;
        
        return Cache::remember($cacheKey, 900, function () use ($tenantId) { // 15 minutes cache
            if (!class_exists('App\Models\VslaTransaction')) {
                return null;
            }

            // Get VSLA transaction stats
            $vslaStats = \App\Models\VslaTransaction::selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN transaction_type = "share_purchase" THEN amount ELSE 0 END) as total_shares,
                SUM(CASE WHEN transaction_type = "welfare_contribution" THEN amount ELSE 0 END) as total_welfare
            ')->first();

            // Get VSLA settings
            $vslaSettings = \App\Models\VslaSetting::where('tenant_id', $tenantId)->first();

            return [
                'total_transactions' => $vslaStats->total,
                'approved_transactions' => $vslaStats->approved,
                'total_shares' => $vslaStats->total_shares,
                'total_welfare' => $vslaStats->total_welfare,
                'share_amount' => $vslaSettings ? $vslaSettings->share_amount : 0,
                'meeting_frequency' => $vslaSettings ? $vslaSettings->meeting_frequency : 'weekly'
            ];
        });
    }

    /**
     * Get Voting summary - OPTIMIZED
     */
    private function getVotingSummary() {
        $tenantId = request()->tenant->id ?? auth()->user()->tenant_id ?? null;
        $cacheKey = 'voting_summary_' . $tenantId;
        
        return Cache::remember($cacheKey, 1200, function () { // 20 minutes cache
            if (!class_exists('App\Models\VotingPosition')) {
                return null;
            }

            // Get voting position stats
            $votingStats = \App\Models\VotingPosition::selectRaw('
                COUNT(*) as total_positions,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_positions
            ')->first();

            // Get total votes cast
            $totalVotes = \App\Models\Vote::count();

            return [
                'total_positions' => $votingStats->total_positions,
                'active_positions' => $votingStats->active_positions,
                'total_votes_cast' => $totalVotes
            ];
        });
    }

    /**
     * Get E-Signature summary - OPTIMIZED
     */
    private function getESignatureSummary() {
        $tenantId = request()->tenant->id ?? auth()->user()->tenant_id ?? null;
        $cacheKey = 'esignature_summary_' . $tenantId;
        
        return Cache::remember($cacheKey, 900, function () { // 15 minutes cache
            if (!class_exists('App\Models\ESignatureDocument')) {
                return null;
            }

            // Get E-Signature document stats
            $esignatureStats = \App\Models\ESignatureDocument::selectRaw('
                COUNT(*) as total_documents,
                SUM(CASE WHEN status = "signed" THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled
            ')->first();

            return [
                'total_documents' => $esignatureStats->total_documents,
                'completed_documents' => $esignatureStats->completed,
                'pending_documents' => $esignatureStats->pending,
                'cancelled_documents' => $esignatureStats->cancelled
            ];
        });
    }

    /**
     * Clear dashboard cache
     */
    public function clearCache() {
        $tenantId = request()->tenant->id ?? auth()->user()->tenant_id ?? null;
        
        // Clear all dashboard-related cache keys
        $cacheKeys = [
            'overdue_loans_' . $tenantId,
            'monthly_revenue_' . $tenantId,
            'member_growth_' . $tenantId,
            'loan_performance_' . $tenantId,
            'transaction_volume_' . $tenantId,
            'asset_summary_' . $tenantId,
            'employee_summary_' . $tenantId,
            'top_members_' . $tenantId,
            'branch_performance_' . $tenantId,
            'currency_breakdown_' . $tenantId,
            'dashboard_stats_' . $tenantId,
            'vsla_summary_' . $tenantId,
            'voting_summary_' . $tenantId,
            'esignature_summary_' . $tenantId,
        ];
        
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
        
        // Clear cache with date ranges (more complex pattern)
        // In production, you might want to use more specific cache clearing patterns
        // For now, we'll clear the main cache keys and let date-specific caches expire naturally
        
        return response()->json(['success' => true, 'message' => 'Dashboard cache cleared successfully']);
    }

    /**
     * Get member growth chart data
     */
    private function getMemberGrowthChart($startDate, $endDate) {
        $months = [];
        $data = [];
        
        $current = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        
        while ($current->lte($end)) {
            $monthStart = $current->copy()->startOfMonth();
            $monthEnd = $current->copy()->endOfMonth();
            
            $count = Member::whereBetween('created_at', [$monthStart, $monthEnd])->count();
            
            $months[] = $current->format('M Y');
            $data[] = $count;
            
            $current->addMonth();
        }
        
        return ['months' => $months, 'data' => $data];
    }

    /**
     * Get loan performance chart data
     */
    private function getLoanPerformanceChart($startDate, $endDate) {
        $performance = $this->getLoanPerformance($startDate, $endDate);
        
        return [
            'labels' => ['Active', 'Completed', 'Defaulted'],
            'data' => [
                $performance['active'],
                $performance['completed'],
                $performance['defaulted']
            ],
            'colors' => ['#28a745', '#007bff', '#dc3545']
        ];
    }

    /**
     * Get transaction trends chart data
     */
    private function getTransactionTrendsChart($startDate, $endDate) {
        $transactions = Transaction::whereBetween('trans_date', [$startDate, $endDate])
            ->where('status', 2)
            ->selectRaw('DATE(trans_date) as date, type, SUM(amount) as total')
            ->groupBy('date', 'type')
            ->orderBy('date')
            ->get();

        $dates = [];
        $deposits = [];
        $withdrawals = [];
        $loanPayments = [];

        $grouped = $transactions->groupBy('date');
        
        foreach ($grouped as $date => $dayTransactions) {
            $dates[] = \Carbon\Carbon::parse($date)->format('M d');
            $deposits[] = $dayTransactions->where('type', 'Deposit')->sum('total');
            $withdrawals[] = $dayTransactions->where('type', 'Withdraw')->sum('total');
            $loanPayments[] = $dayTransactions->where('type', 'Loan_Repayment')->sum('total');
        }

        return [
            'dates' => $dates,
            'deposits' => $deposits,
            'withdrawals' => $withdrawals,
            'loan_payments' => $loanPayments
        ];
    }

    /**
     * Get revenue breakdown chart data
     */
    private function getRevenueBreakdownChart($startDate, $endDate) {
        $revenues = Transaction::whereBetween('trans_date', [$startDate, $endDate])
            ->where('status', 2)
            ->where('dr_cr', 'cr')
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->get();

        return [
            'labels' => $revenues->pluck('type')->toArray(),
            'data' => $revenues->pluck('total')->toArray(),
            'colors' => ['#28a745', '#007bff', '#ffc107', '#17a2b8', '#6f42c1']
        ];
    }

}
