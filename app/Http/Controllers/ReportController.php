<?php
namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_timezone());
        view()->share('assets', ['datatable']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function account_statement(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.account_statement');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data           = [];
            $date1          = $request->date1;
            $date2          = $request->date2;
            $account_number = isset($request->account_number) ? $request->account_number : '';

            $account = SavingsAccount::where('account_number', $account_number)->with(['savings_type.currency', 'member'])->first();
            if (! $account) {
                return back()->with('error', _lang('Account not found'));
            }

            DB::select("SELECT ((SELECT IFNULL(SUM(amount),0) FROM transactions WHERE dr_cr = 'cr' AND member_id = $account->member_id AND savings_account_id = $account->id AND status=2 AND created_at < '$date1') - (SELECT IFNULL(SUM(amount),0) FROM transactions WHERE dr_cr = 'dr' AND member_id = $account->member_id AND savings_account_id = $account->id AND status = 2 AND created_at < '$date1')) into @openingBalance");

            $data['report_data'] = DB::select("SELECT '$date1' trans_date,'Opening Balance' as description, 0 as 'debit', 0 as 'credit', @openingBalance as 'balance'
            UNION ALL
            SELECT date(trans_date), description, debit, credit, @openingBalance := @openingBalance + (credit - debit) as balance FROM
            (SELECT date(transactions.trans_date) as trans_date, transactions.description, IF(transactions.dr_cr='dr',transactions.amount,0) as debit, IF(transactions.dr_cr='cr',transactions.amount,0) as credit FROM `transactions` JOIN savings_accounts ON savings_account_id=savings_accounts.id WHERE savings_accounts.id = $account->id AND transactions.member_id = $account->member_id AND transactions.status=2 AND date(transactions.trans_date) >= '$date1' AND date(transactions.trans_date) <= '$date2')
            as all_transaction");

            $data['date1']          = $request->date1;
            $data['date2']          = $request->date2;
            $data['account_number'] = $request->account_number;
            $data['account']        = $account;
            return view('backend.admin.reports.account_statement', $data);
        }
    }

    public function account_balances(Request $request) {
        if ($request->isMethod('get')) {
            $account_type = _lang('N/A');
            return view('backend.admin.reports.account_balances', compact('account_type'));
        } else if ($request->isMethod('post')) {
            $account_type_id = $request->account_type_id;
            $member_id       = $request->member_id;

            if ($account_type_id != 'all') {
                $savingsProduct = SavingsProduct::find($account_type_id);
                if (! $savingsProduct) {
                    return back()->with('error', _lang('Invalid Account Type'));
                }
                $account_type = $savingsProduct->name;
                $accounts     = get_all_account_details($savingsProduct->id, $request->member_id);
            } else {
                $account_type = _lang('All');
                $accounts     = get_all_account_details(null, $request->member_id);
            }

            return view('backend.admin.reports.account_balances', compact('accounts', 'account_type_id', 'account_type', 'member_id'));
        }
    }

    public function loan_report(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.loan_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data      = [];
            $date1     = $request->date1;
            $date2     = $request->date2;
            $member_no = isset($request->member_no) ? $request->member_no : '';
            $status    = isset($request->status) ? $request->status : '';
            $loan_type = isset($request->loan_type) ? $request->loan_type : '';

            $data['report_data'] = Loan::select('loans.*')
                ->with(['borrower', 'loan_product'])
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                }, function ($query, $status) {
                    if ($status != '') {
                        return $query->where('status', $status);
                    }
                })
                ->when($loan_type, function ($query, $loan_type) {
                    return $query->where('loan_product_id', $loan_type);
                })
                ->when($member_no, function ($query, $member_no) {
                    return $query->whereHas('borrower', function ($query) use ($member_no) {
                        return $query->where('member_no', $member_no);
                    });
                })
                ->whereRaw("date(loans.created_at) >= '$date1' AND date(loans.created_at) <= '$date2'")
                ->orderBy('id', 'desc')
                ->get();

            $data['date1']     = $request->date1;
            $data['date2']     = $request->date2;
            $data['status']    = $request->status;
            $data['member_no'] = $request->member_no;
            $data['loan_type'] = $request->loan_type;
            return view('backend.admin.reports.loan_report', $data);
        }
    }

    public function loan_due_report(Request $request) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $data = [];
        $date = date('Y-m-d');

        $data['report_data'] = LoanRepayment::selectRaw('loan_id, MIN(repayment_date) as earliest_repayment_date, SUM(principal_amount) as total_due')
            ->with('loan')
            ->whereRaw("repayment_date < '$date'")
            ->where('status', 0)
            ->groupBy('loan_id')
            ->get();

        return view('backend.admin.reports.loan_due_report', $data);
    }

    public function transactions_report(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.transactions_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data             = [];
            $date1            = $request->date1;
            $date2            = $request->date2;
            $account_number   = isset($request->account_number) ? $request->account_number : '';
            $status           = isset($request->status) ? $request->status : '';
            $transaction_type = isset($request->transaction_type) ? $request->transaction_type : '';

            $data['report_data'] = Transaction::select('transactions.*')
                ->with(['member', 'account'])
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                }, function ($query, $status) {
                    if ($status != '') {
                        return $query->where('status', $status);
                    }
                })
                ->when($transaction_type, function ($query, $transaction_type) {
                    return $query->where('type', $transaction_type);
                })
                ->when($account_number, function ($query, $account_number) {
                    return $query->whereHas('account', function ($query) use ($account_number) {
                        return $query->where('account_number', $account_number);
                    });
                })
                ->whereRaw("date(transactions.trans_date) >= '$date1' AND date(transactions.trans_date) <= '$date2'")
                ->orderBy('transactions.trans_date', 'desc')
                ->get();

            $data['date1']            = $request->date1;
            $data['date2']            = $request->date2;
            $data['status']           = $request->status;
            $data['account_number']   = $request->account_number;
            $data['transaction_type'] = $request->transaction_type;
            return view('backend.admin.reports.transactions_report', $data);
        }
    }

    public function expense_report(Request $request) {
        if ($request->isMethod('get')) {
            $expense_categories = ExpenseCategory::all();
            return view('backend.admin.reports.expense_report', compact('expense_categories'));
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data     = [];
            $date1    = $request->date1;
            $date2    = $request->date2;
            $category = isset($request->category) ? $request->category : '';
            $branch   = isset($request->branch) ? $request->branch : '';

            $data['report_data'] = Expense::select('expenses.*')
                ->with(['expense_category'])
                ->when($category, function ($query, $category) {
                    return $query->whereHas('expense_category', function ($query) use ($category) {
                        return $query->where('expense_category_id', $category);
                    });
                })
                ->when($branch, function ($query, $branch) {
                    return $query->where('branch_id', $branch);
                })
                ->whereRaw("date(expenses.expense_date) >= '$date1' AND date(expenses.expense_date) <= '$date2'")
                ->orderBy('expense_date', 'desc')
                ->get();

            $data['date1']              = $request->date1;
            $data['date2']              = $request->date2;
            $data['category']           = $request->category;
            $data['branch']             = $request->branch;
            $data['expense_categories'] = ExpenseCategory::all();
            return view('backend.admin.reports.expense_report', $data);
        }
    }

    public function revenue_report(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.revenue_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data        = [];
            $year        = $request->year;
            $month       = $request->month;
            $currency_id = $request->currency_id;

            $transaction_revenue = Transaction::selectRaw("CONCAT('Revenue from ', type), sum(charge) as amount")
                ->whereRaw("YEAR(trans_date) = '$year' AND MONTH(trans_date) = '$month'")
                ->where('charge', '>', 0)
                ->where('status', 2)
                ->whereHas('account.savings_type', function ($query) use ($currency_id) {
                    return $query->where('currency_id', $currency_id);
                })
                ->groupBy('type');

            $maintainaince_fee = Transaction::selectRaw("CONCAT('Revenue from ', type), sum(amount) as amount")
                ->whereRaw("YEAR(trans_date) = '$year' AND MONTH(trans_date) = '$month'")
                ->where('type', 'Account_Maintenance_Fee')
                ->where('status', 2)
                ->whereHas('account.savings_type', function ($query) use ($currency_id) {
                    return $query->where('currency_id', $currency_id);
                })
                ->groupBy('type');

            $others_fee = Transaction::join('transaction_categories', function ($join) {
                $join->on('transaction_categories.name', '=', 'transactions.type')
                    ->where('transaction_categories.status', '=', 1);
            })
                ->selectRaw("CONCAT('Revenue from ', type), sum(amount) as amount")
                ->whereRaw("YEAR(trans_date) = '$year' AND MONTH(trans_date) = '$month'")
                ->where('dr_cr', 'dr')
                ->where('transactions.status', 2)
                ->whereHas('account.savings_type', function ($query) use ($currency_id) {
                    return $query->where('currency_id', $currency_id);
                })
                ->groupBy('type');

            $data['report_data'] = LoanPayment::selectRaw("'Revenue from Loan' as type, sum(interest + late_penalties) as amount")
                ->whereRaw("YEAR(loan_payments.paid_at) = '$year' AND MONTH(loan_payments.paid_at) = '$month'")
                ->whereHas('loan', function ($query) use ($currency_id) {
                    return $query->where('currency_id', $currency_id);
                })
                ->union($transaction_revenue)
                ->union($maintainaince_fee)
                ->union($others_fee)
                ->get();

            $data['year']        = $request->year;
            $data['month']       = $request->month;
            $data['currency_id'] = $request->currency_id;
            return view('backend.admin.reports.revenue_report', $data);
        }

    }

    public function loan_repayment_report(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.loan_repayment_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data    = [];
            $loan_id = isset($request->loan_id) ? $request->loan_id : '';

            $data['report_data'] = Loan::select('loans.*')
                ->with(['borrower', 'loan_product', 'payments'])
                ->when($loan_id, function ($query, $loan_id) {
                    return $query->where('id', $loan_id);
                })
                ->orderBy('id', 'desc')
                ->first();

            return view('backend.admin.reports.loan_repayment_report', $data);
        }
    }

    public function cash_in_hand() {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);
        $tenant_id = request()->tenant->id;

        $total_deposit = DB::select("SELECT currency.name as currency_name, IFNULL(SUM(amount),0) as total_deposit FROM transactions
		JOIN savings_accounts ON savings_accounts.id = transactions.savings_account_id
		JOIN savings_products ON savings_products.id = savings_accounts.savings_product_id
		JOIN currency ON currency.id = savings_products.currency_id
		WHERE transactions.type = 'Deposit' AND transactions.status = 2 AND transactions.tenant_id = $tenant_id GROUP BY currency_name");

        foreach ($total_deposit as $row) {
            $data['total_deposit'][$row->currency_name] = $row;
        }

        $total_withdraw = DB::select("SELECT currency.name as currency_name, IFNULL(SUM(amount),0) as total_withdraw FROM transactions
		JOIN savings_accounts ON savings_accounts.id = transactions.savings_account_id
		JOIN savings_products ON savings_products.id = savings_accounts.savings_product_id
		JOIN currency ON currency.id = savings_products.currency_id
		WHERE transactions.type = 'Withdraw' AND transactions.status = 2 AND transactions.tenant_id = $tenant_id  GROUP BY currency_name");

        foreach ($total_withdraw as $row) {
            $data['total_withdraw'][$row->currency_name] = $row;
        }

        $total_cash_disbursement = DB::select("SELECT currency.name as currency_name, IFNULL(SUM(applied_amount),0) as total_cash_disbursement FROM loans
		JOIN currency ON currency.id = loans.currency_id
		WHERE loans.disburse_method = 'cash' AND (loans.status = 1 OR loans.status = 2) AND loans.tenant_id = $tenant_id  GROUP BY currency_name");

        foreach ($total_cash_disbursement as $row) {
            $data['total_cash_disbursement'][$row->currency_name] = $row;
        }

        $total_cash_payment = DB::select("SELECT currency.name as currency_name, IFNULL(SUM(total_amount),0) as total_cash_payment FROM loan_payments
		JOIN loans ON loans.id = loan_payments.loan_id
		JOIN currency ON currency.id = loans.currency_id
		WHERE loan_payments.transaction_id IS NULL AND loan_payments.tenant_id = $tenant_id GROUP BY currency_name");

        foreach ($total_cash_payment as $row) {
            $data['total_cash_payment'][$row->currency_name] = $row;
        }

        $bank_to_cash_deposit = DB::select("SELECT currency.name as currency_name, IFNULL(SUM(amount),0) as bank_to_cash_deposit FROM bank_transactions
		JOIN bank_accounts ON bank_accounts.id = bank_transactions.bank_account_id
		JOIN currency ON currency.id = bank_accounts.currency_id
		WHERE bank_transactions.type = 'bank_to_cash' AND bank_transactions.status = 1 AND bank_transactions.tenant_id = $tenant_id GROUP BY currency_name");

        foreach ($bank_to_cash_deposit as $row) {
            $data['bank_to_cash_deposit'][$row->currency_name] = $row;
        }

        $cash_to_bank_deposit = DB::select("SELECT currency.name as currency_name, IFNULL(SUM(amount),0) as cash_to_bank_deposit FROM bank_transactions
		JOIN bank_accounts ON bank_accounts.id = bank_transactions.bank_account_id
		JOIN currency ON currency.id = bank_accounts.currency_id
		WHERE bank_transactions.type = 'cash_to_bank' AND bank_transactions.status = 1 AND bank_transactions.tenant_id = $tenant_id GROUP BY currency_name");

        foreach ($cash_to_bank_deposit as $row) {
            $data['cash_to_bank_deposit'][$row->currency_name] = $row;
        }

        $data['total_expense'] = DB::select("SELECT IFNULL(SUM(amount),0) as total_expense FROM expenses WHERE tenant_id = $tenant_id");
        $data['currencies']    = Currency::active()
            ->whereHas('savings_products')
            ->orWhereHas('bank_accounts')
            ->get();

        return view('backend.admin.reports.cash_in_hand', $data);
    }

    public function bank_transactions(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.bank_transactions_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data             = [];
            $date1            = $request->date1;
            $date2            = $request->date2;
            $bank_account_id  = isset($request->bank_account_id) ? $request->bank_account_id : '';
            $status           = isset($request->status) ? $request->status : '';
            $transaction_type = isset($request->transaction_type) ? $request->transaction_type : '';

            $data['report_data'] = BankTransaction::select('bank_transactions.*')
                ->with(['bankAccount.currency'])
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                }, function ($query, $status) {
                    if ($status != '') {
                        return $query->where('status', $status);
                    }
                })
                ->when($transaction_type, function ($query, $transaction_type) {
                    return $query->where('bank_transactions.type', $transaction_type);
                })
                ->when($bank_account_id, function ($query, $bank_account_id) {
                    return $query->where('bank_transactions.bank_account_id', $bank_account_id);
                })
                ->whereRaw("date(bank_transactions.trans_date) >= '$date1' AND date(bank_transactions.trans_date) <= '$date2'")
                ->orderBy('bank_transactions.trans_date', 'desc')
                ->get();

            $data['date1']            = $request->date1;
            $data['date2']            = $request->date2;
            $data['status']           = $request->status;
            $data['bank_account_id']  = $request->bank_account_id;
            $data['transaction_type'] = $request->transaction_type;
            return view('backend.admin.reports.bank_transactions_report', $data);
        }
    }

    public function bank_balances(Request $request) {
        $data             = [];
        $data['accounts'] = BankAccount::select('bank_accounts.*', DB::raw("((SELECT IFNULL(SUM(amount),0)
        FROM bank_transactions WHERE dr_cr = 'cr' AND status = 1 AND bank_account_id = bank_accounts.id) -
        (SELECT IFNULL(SUM(amount),0) FROM bank_transactions WHERE dr_cr = 'dr'
        AND status = 1 AND bank_account_id = bank_accounts.id)) as balance"))
            ->with('currency')
            ->orderBy('id', 'desc')
            ->get();

        return view('backend.admin.reports.bank_balances', $data);
    }

    // ==================== NEW ANALYTICS AND CHARTS ====================

    public function loan_released_chart() {
        $data = [];
        $data['chart_data'] = Loan::selectRaw('DATE(release_date) as date, COUNT(*) as count, SUM(applied_amount) as total_amount')
            ->whereNotNull('release_date')
            ->where('status', 1)
            ->whereRaw('release_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }

    public function loan_collections_chart() {
        $data = [];
        $data['chart_data'] = LoanPayment::selectRaw('DATE(paid_at) as date, COUNT(*) as count, SUM(total_amount) as total_amount')
            ->whereRaw('paid_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }

    public function collections_vs_due_chart() {
        $data = [];
        
        // Collections data
        $collections = LoanPayment::selectRaw('DATE(paid_at) as date, SUM(total_amount) as collected')
            ->whereRaw('paid_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Due loans data
        $due_loans = LoanRepayment::selectRaw('DATE(repayment_date) as date, SUM(amount_to_pay) as due_amount')
            ->where('status', 0)
            ->whereRaw('repayment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $data['collections'] = $collections;
        $data['due_loans'] = $due_loans;

        return response()->json($data);
    }

    public function collections_vs_released_chart() {
        $data = [];
        
        // Collections data
        $collections = LoanPayment::selectRaw('DATE(paid_at) as date, SUM(total_amount) as collected')
            ->whereRaw('paid_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Released loans data
        $released = Loan::selectRaw('DATE(release_date) as date, SUM(applied_amount) as released_amount')
            ->whereNotNull('release_date')
            ->where('status', 1)
            ->whereRaw('release_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $data['collections'] = $collections;
        $data['released'] = $released;

        return response()->json($data);
    }

    public function outstanding_loans_summary() {
        $data = [];
        
        $data['total_outstanding'] = Loan::where('status', 1)
            ->selectRaw('SUM(applied_amount - total_paid) as total_outstanding')
            ->first();

        $data['principal_outstanding'] = LoanRepayment::where('status', 0)
            ->selectRaw('SUM(principal_amount) as principal_outstanding')
            ->first();

        $data['interest_outstanding'] = LoanRepayment::where('status', 0)
            ->selectRaw('SUM(interest) as interest_outstanding')
            ->first();

        $data['penalty_outstanding'] = LoanRepayment::where('status', 0)
            ->selectRaw('SUM(penalty) as penalty_outstanding')
            ->first();

        return response()->json($data);
    }

    public function due_vs_collections_breakdown() {
        $data = [];
        
        // Principal breakdown
        $data['principal'] = [
            'due' => LoanRepayment::where('status', 0)->sum('principal_amount'),
            'collected' => LoanPayment::sum('repayment_amount')
        ];

        // Interest breakdown
        $data['interest'] = [
            'due' => LoanRepayment::where('status', 0)->sum('interest'),
            'collected' => LoanPayment::sum('interest')
        ];

        // Penalty breakdown
        $data['penalty'] = [
            'due' => LoanRepayment::where('status', 0)->sum('penalty'),
            'collected' => LoanPayment::sum('late_penalties')
        ];

        return response()->json($data);
    }

    public function loan_statistics_chart() {
        $data = [];
        
        // Number of open loans (cumulative)
        $data['open_loans'] = Loan::where('status', 1)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereRaw('created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Number of loans released
        $data['loans_released'] = Loan::whereNotNull('release_date')
            ->where('status', 1)
            ->selectRaw('DATE(release_date) as date, COUNT(*) as count')
            ->whereRaw('release_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Number of repayments collected
        $data['repayments_collected'] = LoanPayment::selectRaw('DATE(paid_at) as date, COUNT(*) as count')
            ->whereRaw('paid_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Number of fully paid loans
        $data['fully_paid'] = Loan::where('status', 2)
            ->selectRaw('DATE(updated_at) as date, COUNT(*) as count')
            ->whereRaw('updated_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }

    public function new_clients_chart() {
        $data = [];
        
        // Borrowers with first loan (new clients) - simplified approach
        $data['new_clients'] = Loan::selectRaw('DATE(release_date) as date, COUNT(DISTINCT borrower_id) as count')
            ->whereNotNull('release_date')
            ->where('status', 1)
            ->whereRaw('release_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }

    public function loan_status_pie_chart() {
        $data = [];
        
        $data['status_breakdown'] = Loan::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->map(function($item) {
                $status_labels = [
                    0 => 'Pending',
                    1 => 'Active',
                    2 => 'Fully Paid',
                    3 => 'Default'
                ];
                return [
                    'status' => $status_labels[$item->status] ?? 'Unknown',
                    'count' => $item->count
                ];
            });

        return response()->json($data);
    }

    public function borrower_gender_chart() {
        $data = [];
        
        $data['gender_breakdown'] = Member::selectRaw('gender, COUNT(*) as count')
            ->whereHas('loans', function($query) {
                $query->where('status', 1);
            })
            ->groupBy('gender')
            ->get();

        return response()->json($data);
    }

    public function recovery_rate_analysis() {
        $data = [];
        
        $total_loans = Loan::count();
        $fully_paid = Loan::where('status', 2)->count();
        $default_loans = Loan::where('status', 3)->count();
        $active_loans = Loan::where('status', 1)->count();

        $data['recovery_rate'] = [
            'total_loans' => $total_loans,
            'fully_paid' => $fully_paid,
            'default_loans' => $default_loans,
            'active_loans' => $active_loans,
            'recovery_percentage' => $total_loans > 0 ? round(($fully_paid / $total_loans) * 100, 2) : 0
        ];

        return response()->json($data);
    }

    public function loan_tenure_analysis() {
        $data = [];
        
        $data['average_tenure'] = Loan::selectRaw('AVG(DATEDIFF(COALESCE(updated_at, CURDATE()), release_date)) as avg_tenure_days')
            ->whereNotNull('release_date')
            ->where('status', 2)
            ->first();

        $data['average_disbursement'] = Loan::selectRaw('AVG(applied_amount) as avg_disbursement')
            ->whereNotNull('release_date')
            ->where('status', 1)
            ->first();

        return response()->json($data);
    }

    public function borrower_age_analysis() {
        $data = [];
        
        // This would require adding age calculation or birth_date field to members table
        // For now, we'll use a placeholder structure
        $data['age_groups'] = [
            ['age_group' => '18-25', 'open_loans' => 0, 'fully_paid' => 0, 'default' => 0],
            ['age_group' => '26-35', 'open_loans' => 0, 'fully_paid' => 0, 'default' => 0],
            ['age_group' => '36-45', 'open_loans' => 0, 'fully_paid' => 0, 'default' => 0],
            ['age_group' => '46-55', 'open_loans' => 0, 'fully_paid' => 0, 'default' => 0],
            ['age_group' => '55+', 'open_loans' => 0, 'fully_paid' => 0, 'default' => 0]
        ];

        return response()->json($data);
    }

    // ==================== NEW REPORTS ====================

    public function borrowers_report(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.borrowers_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data = [];
            $date1 = $request->date1;
            $date2 = $request->date2;
            $status = isset($request->status) ? $request->status : '';
            $gender = isset($request->gender) ? $request->gender : '';

            $data['report_data'] = Member::with(['loans' => function($query) use ($date1, $date2, $status) {
                $query->when($status, function($q, $status) {
                    return $q->where('status', $status);
                })
                ->whereRaw("date(created_at) >= '$date1' AND date(created_at) <= '$date2'");
            }])
            ->when($gender, function($query, $gender) {
                return $query->where('gender', $gender);
            })
            ->whereHas('loans')
            ->orderBy('id', 'desc')
            ->get();

            $data['date1'] = $request->date1;
            $data['date2'] = $request->date2;
            $data['status'] = $request->status;
            $data['gender'] = $request->gender;
            return view('backend.admin.reports.borrowers_report', $data);
        }
    }

    public function loan_arrears_aging_report(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.loan_arrears_aging_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data = [];
            $data['report_data'] = LoanRepayment::with(['loan.borrower', 'loan.loan_product'])
                ->where('status', 0)
                ->whereRaw('repayment_date < CURDATE()')
                ->selectRaw('*, DATEDIFF(CURDATE(), repayment_date) as days_overdue')
                ->orderBy('days_overdue', 'desc')
                ->get();

            return view('backend.admin.reports.loan_arrears_aging_report', $data);
        }
    }

    public function collections_report(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.collections_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data = [];
            $date1 = $request->date1;
            $date2 = $request->date2;
            $collector_id = isset($request->collector_id) ? $request->collector_id : '';

            $data['report_data'] = LoanPayment::with(['loan.borrower', 'loan.created_by', 'member'])
                ->when($collector_id, function($query, $collector_id) {
                    return $query->whereHas('loan', function($q) use ($collector_id) {
                        return $q->where('created_user_id', $collector_id);
                    });
                })
                ->whereRaw("date(paid_at) >= '$date1' AND date(paid_at) <= '$date2'")
                ->orderBy('paid_at', 'desc')
                ->get();

            $data['date1'] = $request->date1;
            $data['date2'] = $request->date2;
            $data['collector_id'] = $request->collector_id;
            return view('backend.admin.reports.collections_report', $data);
        }
    }

    public function disbursement_report(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.disbursement_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data = [];
            $date1 = $request->date1;
            $date2 = $request->date2;
            $loan_product_id = isset($request->loan_product_id) ? $request->loan_product_id : '';

            $data['report_data'] = Loan::with(['borrower', 'loan_product', 'currency'])
                ->when($loan_product_id, function($query, $loan_product_id) {
                    return $query->where('loan_product_id', $loan_product_id);
                })
                ->whereNotNull('release_date')
                ->where('status', 1)
                ->whereRaw("date(release_date) >= '$date1' AND date(release_date) <= '$date2'")
                ->orderBy('release_date', 'desc')
                ->get();

            $data['date1'] = $request->date1;
            $data['date2'] = $request->date2;
            $data['loan_product_id'] = $request->loan_product_id;
            return view('backend.admin.reports.disbursement_report', $data);
        }
    }

    public function fees_report(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.fees_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data = [];
            $date1 = $request->date1;
            $date2 = $request->date2;

            $data['report_data'] = LoanPayment::with(['loan.borrower'])
                ->selectRaw('*, (interest + late_penalties) as total_fees')
                ->whereRaw("date(paid_at) >= '$date1' AND date(paid_at) <= '$date2'")
                ->orderBy('paid_at', 'desc')
                ->get();

            $data['date1'] = $request->date1;
            $data['date2'] = $request->date2;
            return view('backend.admin.reports.fees_report', $data);
        }
    }

    public function loan_officer_report(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.loan_officer_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data = [];
            $date1 = $request->date1;
            $date2 = $request->date2;
            $officer_id = isset($request->officer_id) ? $request->officer_id : '';

            $data['report_data'] = Loan::with(['borrower', 'loan_product', 'created_by'])
                ->when($officer_id, function($query, $officer_id) {
                    return $query->where('created_user_id', $officer_id);
                })
                ->whereRaw("date(created_at) >= '$date1' AND date(created_at) <= '$date2'")
                ->orderBy('created_at', 'desc')
                ->get();

            $data['date1'] = $request->date1;
            $data['date2'] = $request->date2;
            $data['officer_id'] = $request->officer_id;
            return view('backend.admin.reports.loan_officer_report', $data);
        }
    }

    public function loan_products_report(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.loan_products_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data = [];
            $data['report_data'] = LoanProduct::withCount(['loans'])
                ->get()
                ->map(function($product) {
                    $loans = $product->loans;
                    $product->total_loans = $loans->count();
                    $product->total_disbursed = $loans->sum('applied_amount');
                    $product->total_collected = $loans->sum('total_paid');
                    $product->avg_loan_size = $loans->avg('applied_amount');
                    return $product;
                });

            return view('backend.admin.reports.loan_products_report', $data);
        }
    }

    public function monthly_report(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.monthly_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data = [];
            $year = $request->year;
            $month = $request->month;

            $data['monthly_data'] = [
                'loans_disbursed' => Loan::whereRaw("YEAR(release_date) = '$year' AND MONTH(release_date) = '$month'")
                    ->where('status', 1)
                    ->sum('applied_amount'),
                'loans_collected' => LoanPayment::whereRaw("YEAR(paid_at) = '$year' AND MONTH(paid_at) = '$month'")
                    ->sum('total_amount'),
                'new_borrowers' => Loan::whereRaw("YEAR(release_date) = '$year' AND MONTH(release_date) = '$month'")
                    ->where('status', 1)
                    ->distinct('borrower_id')
                    ->count('borrower_id'),
                'fees_collected' => LoanPayment::whereRaw("YEAR(paid_at) = '$year' AND MONTH(paid_at) = '$month'")
                    ->sum(DB::raw('interest + late_penalties'))
            ];

            $data['year'] = $request->year;
            $data['month'] = $request->month;
            return view('backend.admin.reports.monthly_report', $data);
        }
    }

    public function outstanding_report(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.outstanding_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data = [];
            $data['report_data'] = Loan::with(['borrower', 'loan_product', 'currency'])
                ->where('status', 1)
                ->selectRaw('*, (applied_amount - total_paid) as outstanding_amount')
                ->orderBy('outstanding_amount', 'desc')
                ->get();

            return view('backend.admin.reports.outstanding_report', $data);
        }
    }

    public function portfolio_at_risk_report(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.portfolio_at_risk_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data = [];
            $data['report_data'] = LoanRepayment::with(['loan.borrower', 'loan.loan_product'])
                ->where('status', 0)
                ->whereRaw('repayment_date < CURDATE()')
                ->selectRaw('*, DATEDIFF(CURDATE(), repayment_date) as days_overdue')
                ->orderBy('days_overdue', 'desc')
                ->get();

            return view('backend.admin.reports.portfolio_at_risk_report', $data);
        }
    }

    public function at_glance_report(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.at_glance_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data = [];
            $data['summary'] = [
                'total_loans' => Loan::count(),
                'active_loans' => Loan::where('status', 1)->count(),
                'fully_paid_loans' => Loan::where('status', 2)->count(),
                'default_loans' => Loan::where('status', 3)->count(),
                'total_borrowers' => Member::whereHas('loans')->count(),
                'total_disbursed' => Loan::where('status', 1)->sum('applied_amount'),
                'total_collected' => LoanPayment::sum('total_amount'),
                'total_outstanding' => Loan::where('status', 1)->sum(DB::raw('applied_amount - total_paid')),
                'total_fees' => LoanPayment::sum(DB::raw('interest + late_penalties'))
            ];

            return view('backend.admin.reports.at_glance_report', $data);
        }
    }

    public function balance_sheet(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.balance_sheet');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data = [];
            $as_of_date = $request->as_of_date;

            // Assets
            $data['assets'] = [
                'cash_in_hand' => $this->get_cash_in_hand($as_of_date),
                'bank_balances' => $this->get_bank_balances($as_of_date),
                'loan_portfolio' => Loan::where('status', 1)->sum(DB::raw('applied_amount - total_paid')),
                'fixed_assets' => 0 // Would need asset management module
            ];

            // Liabilities
            $data['liabilities'] = [
                'savings_deposits' => $this->get_savings_liabilities($as_of_date),
                'borrowings' => 0 // Would need borrowing module
            ];

            // Equity
            $data['equity'] = [
                'retained_earnings' => $this->get_retained_earnings($as_of_date),
                'capital' => 0 // Would need capital module
            ];

            $data['as_of_date'] = $request->as_of_date;
            return view('backend.admin.reports.balance_sheet', $data);
        }
    }

    public function profit_loss_statement(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.profit_loss_statement');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data = [];
            $date1 = $request->date1;
            $date2 = $request->date2;

            // Revenue
            $data['revenue'] = [
                'interest_income' => LoanPayment::whereRaw("date(paid_at) >= '$date1' AND date(paid_at) <= '$date2'")->sum('interest'),
                'penalty_income' => LoanPayment::whereRaw("date(paid_at) >= '$date1' AND date(paid_at) <= '$date2'")->sum('late_penalties'),
                'fee_income' => Transaction::whereRaw("date(trans_date) >= '$date1' AND date(trans_date) <= '$date2'")->where('charge', '>', 0)->sum('charge')
            ];

            // Expenses
            $data['expenses'] = [
                'operating_expenses' => Expense::whereRaw("date(expense_date) >= '$date1' AND date(expense_date) <= '$date2'")->sum('amount'),
                'interest_expense' => 0 // Would need borrowing module
            ];

            $data['date1'] = $request->date1;
            $data['date2'] = $request->date2;
            return view('backend.admin.reports.profit_loss_statement', $data);
        }
    }

    // Helper methods for balance sheet
    private function get_cash_in_hand($as_of_date) {
        // Implementation would depend on cash management system
        return 0;
    }

    private function get_bank_balances($as_of_date) {
        return BankAccount::selectRaw('SUM(
            (SELECT IFNULL(SUM(amount),0) FROM bank_transactions WHERE dr_cr = "cr" AND status = 1 AND bank_account_id = bank_accounts.id AND date(trans_date) <= "' . $as_of_date . '") -
            (SELECT IFNULL(SUM(amount),0) FROM bank_transactions WHERE dr_cr = "dr" AND status = 1 AND bank_account_id = bank_accounts.id AND date(trans_date) <= "' . $as_of_date . '")
        ) as balance')->first()->balance ?? 0;
    }

    private function get_savings_liabilities($as_of_date) {
        return SavingsAccount::selectRaw('SUM(
            (SELECT IFNULL(SUM(amount),0) FROM transactions WHERE dr_cr = "cr" AND status = 2 AND savings_account_id = savings_accounts.id AND date(trans_date) <= "' . $as_of_date . '") -
            (SELECT IFNULL(SUM(amount),0) FROM transactions WHERE dr_cr = "dr" AND status = 2 AND savings_account_id = savings_accounts.id AND date(trans_date) <= "' . $as_of_date . '")
        ) as balance')->first()->balance ?? 0;
    }

    private function get_retained_earnings($as_of_date) {
        // Simplified calculation - would need proper accounting system
        $revenue = LoanPayment::whereRaw("date(paid_at) <= '$as_of_date'")->sum(DB::raw('interest + late_penalties'));
        $expenses = Expense::whereRaw("date(expense_date) <= '$as_of_date'")->sum('amount');
        return $revenue - $expenses;
    }

}
