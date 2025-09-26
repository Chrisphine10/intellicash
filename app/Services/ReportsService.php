<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\Expense;
use App\Models\BankTransaction;
use App\Models\BankAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ReportsService
{
    /**
     * Get loan summary statistics
     */
    public function getLoanSummary($tenantId, $filters = [])
    {
        $cacheKey = 'loan_summary_' . $tenantId . '_' . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 1800, function () use ($tenantId, $filters) {
            $query = Loan::where('tenant_id', $tenantId);
            
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            if (isset($filters['date_from']) && isset($filters['date_to'])) {
                $query->whereBetween('created_at', [$filters['date_from'], $filters['date_to']]);
            }
            
            return [
                'total_loans' => $query->count(),
                'total_amount' => $query->sum('applied_amount'),
                'average_amount' => $query->avg('applied_amount'),
                'status_breakdown' => $query->groupBy('status')->count()
            ];
        });
    }
    
    /**
     * Get outstanding loans with optimized query
     */
    public function getOutstandingLoans($tenantId)
    {
        return DB::select('
            SELECT 
                l.id,
                l.loan_id,
                l.applied_amount,
                l.total_payable,
                l.total_paid,
                COALESCE(lp.total_interest_paid, 0) as interest_paid,
                (l.total_payable - (l.total_paid + COALESCE(lp.total_interest_paid, 0))) as outstanding_amount,
                m.first_name,
                m.last_name,
                m.member_no,
                lp_product.name as product_name
            FROM loans l
            LEFT JOIN (
                SELECT loan_id, SUM(interest) as total_interest_paid
                FROM loan_payments
                GROUP BY loan_id
            ) lp ON l.id = lp.loan_id
            LEFT JOIN members m ON l.borrower_id = m.id
            LEFT JOIN loan_products lp_product ON l.loan_product_id = lp_product.id
            WHERE l.tenant_id = ? AND l.status = 1
            ORDER BY outstanding_amount DESC
        ', [$tenantId]);
    }
    
    /**
     * Get loan statistics by product
     */
    public function getLoanStatistics($tenantId, $dateFrom, $dateTo)
    {
        return DB::select('
            SELECT 
                lp.name as product_name,
                COUNT(l.id) as total_loans,
                SUM(l.applied_amount) as total_disbursed,
                SUM(l.total_paid) as total_collected,
                AVG(l.applied_amount) as avg_loan_size,
                COUNT(CASE WHEN l.status = 1 THEN 1 END) as active_loans,
                COUNT(CASE WHEN l.status = 2 THEN 1 END) as completed_loans,
                COUNT(CASE WHEN l.status = 3 THEN 1 END) as defaulted_loans
            FROM loans l
            INNER JOIN loan_products lp ON l.loan_product_id = lp.id
            WHERE l.tenant_id = ? 
                AND l.created_at >= ? 
                AND l.created_at <= ?
            GROUP BY lp.id, lp.name
            ORDER BY total_disbursed DESC
        ', [$tenantId, $dateFrom, $dateTo]);
    }
    
    /**
     * Get cash in hand calculation
     */
    public function getCashInHand($tenantId, $asOfDate = null)
    {
        $asOfDate = $asOfDate ?: Carbon::now()->format('Y-m-d');
        
        // Calculate cash in hand from cash transactions
        $cashDeposits = Transaction::where('tenant_id', $tenantId)
            ->where('method', 'cash')
            ->where('dr_cr', 'cr')
            ->where('status', 2)
            ->whereRaw('date(trans_date) <= ?', [$asOfDate])
            ->sum('amount');
            
        $cashWithdrawals = Transaction::where('tenant_id', $tenantId)
            ->where('method', 'cash')
            ->where('dr_cr', 'dr')
            ->where('status', 2)
            ->whereRaw('date(trans_date) <= ?', [$asOfDate])
            ->sum('amount');
            
        return $cashDeposits - $cashWithdrawals;
    }
    
    /**
     * Get bank balances
     */
    public function getBankBalances($tenantId, $asOfDate = null)
    {
        $asOfDate = $asOfDate ?: Carbon::now()->format('Y-m-d');
        
        return DB::select('SELECT SUM(
            (SELECT IFNULL(SUM(amount),0) FROM bank_transactions WHERE dr_cr = "cr" AND status = 1 AND bank_account_id = bank_accounts.id AND date(trans_date) <= ?) -
            (SELECT IFNULL(SUM(amount),0) FROM bank_transactions WHERE dr_cr = "dr" AND status = 1 AND bank_account_id = bank_accounts.id AND date(trans_date) <= ?)
        ) as balance FROM bank_accounts WHERE tenant_id = ?', [$asOfDate, $asOfDate, $tenantId])[0]->balance ?? 0;
    }
    
    /**
     * Get savings liabilities
     */
    public function getSavingsLiabilities($tenantId, $asOfDate = null)
    {
        $asOfDate = $asOfDate ?: Carbon::now()->format('Y-m-d');
        
        return DB::select('SELECT SUM(
            (SELECT IFNULL(SUM(amount),0) FROM transactions WHERE dr_cr = "cr" AND status = 2 AND savings_account_id = savings_accounts.id AND date(trans_date) <= ?) -
            (SELECT IFNULL(SUM(amount),0) FROM transactions WHERE dr_cr = "dr" AND status = 2 AND savings_account_id = savings_accounts.id AND date(trans_date) <= ?)
        ) as balance FROM savings_accounts WHERE tenant_id = ?', [$asOfDate, $asOfDate, $tenantId])[0]->balance ?? 0;
    }
    
    /**
     * Get retained earnings calculation
     */
    public function getRetainedEarnings($tenantId, $asOfDate = null)
    {
        $asOfDate = $asOfDate ?: Carbon::now()->format('Y-m-d');
        
        // Revenue from loan interest and penalties
        $revenue = LoanPayment::where('tenant_id', $tenantId)
            ->whereRaw('date(paid_at) <= ?', [$asOfDate])
            ->sum(DB::raw('interest + late_penalties'));
            
        // Revenue from transaction charges
        $revenue += Transaction::where('tenant_id', $tenantId)
            ->where('charge', '>', 0)
            ->where('status', 2)
            ->whereRaw('date(trans_date) <= ?', [$asOfDate])
            ->sum('charge');
        
        // Expenses
        $expenses = Expense::where('tenant_id', $tenantId)
            ->whereRaw('date(expense_date) <= ?', [$asOfDate])
            ->sum('amount');
            
        return $revenue - $expenses;
    }
    
    /**
     * Get comprehensive balance sheet data
     */
    public function getBalanceSheetData($tenantId, $asOfDate = null)
    {
        $asOfDate = $asOfDate ?: Carbon::now()->format('Y-m-d');
        
        $assets = [
            'cash_in_hand' => $this->getCashInHand($tenantId, $asOfDate),
            'bank_balances' => $this->getBankBalances($tenantId, $asOfDate),
            'loan_portfolio' => $this->getLoanPortfolioValue($tenantId, $asOfDate),
            'fixed_assets' => $this->getFixedAssetsValue($tenantId, $asOfDate),
            'other_assets' => 0
        ];
        
        $liabilities = [
            'savings_deposits' => $this->getSavingsLiabilities($tenantId, $asOfDate),
            'borrowings' => 0,
            'accrued_expenses' => 0,
            'other_liabilities' => 0
        ];
        
        $equity = [
            'retained_earnings' => $this->getRetainedEarnings($tenantId, $asOfDate),
            'capital' => 0,
            'reserves' => 0
        ];
        
        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => array_sum($assets),
            'total_liabilities' => array_sum($liabilities),
            'total_equity' => array_sum($equity)
        ];
    }
    
    /**
     * Get loan portfolio value (outstanding loans)
     */
    private function getLoanPortfolioValue($tenantId, $asOfDate)
    {
        $loanPortfolio = Loan::where('status', 1)
            ->where('tenant_id', $tenantId)
            ->with('payments')
            ->get()
            ->sum(function ($loan) {
                $totalPaidIncludingInterest = $loan->total_paid + $loan->payments->sum('interest');
                return $loan->total_payable - $totalPaidIncludingInterest;
            });
            
        return $loanPortfolio;
    }
    
    /**
     * Get fixed assets value (if asset management is enabled)
     */
    private function getFixedAssetsValue($tenantId, $asOfDate)
    {
        // This would integrate with asset management module if enabled
        // For now, return 0
        return 0;
    }
    
    /**
     * Clear cache for specific tenant
     */
    public function clearCache($tenantId)
    {
        $patterns = [
            'loan_summary_' . $tenantId . '_*',
            'outstanding_loans_' . $tenantId,
            'balance_sheet_' . $tenantId . '_*'
        ];
        
        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
}
