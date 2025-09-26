<?php
namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetLease;
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
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReportsService;
use App\Services\AuditService;
use App\Services\DataSanitizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;
use Carbon\Carbon;

class ReportController extends Controller {

    protected $reportsService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(ReportsService $reportsService) {
        $this->middleware('auth');
        $this->middleware('tenant.access');
        $this->middleware('permission:reports.view');
        $this->middleware('report.rate.limit');
        
        $this->reportsService = $reportsService;
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
            try {
                // Validate input
                $validator = Validator::make($request->all(), [
                    'date1' => 'required|date|before_or_equal:date2',
                    'date2' => 'required|date|after_or_equal:date1',
                    'account_number' => 'required|string|max:50|regex:/^[A-Z0-9]+$/'
                ], [
                    'date1.required' => 'Start date is required',
                    'date1.date' => 'Invalid start date format',
                    'date1.before_or_equal' => 'Start date must be before or equal to end date',
                    'date2.required' => 'End date is required',
                    'date2.date' => 'Invalid end date format',
                    'date2.after_or_equal' => 'End date must be after or equal to start date',
                    'account_number.required' => 'Account number is required',
                    'account_number.regex' => 'Invalid account number format'
                ]);

                if ($validator->fails()) {
                    return back()->withErrors($validator)->withInput();
                }

                // Sanitize inputs
                $sanitized = DataSanitizationService::sanitizeReportInputs($request->all());
                $date1 = $sanitized['date1'];
                $date2 = $sanitized['date2'];
                $account_number = $sanitized['account_number'];

                // Log report access
                AuditService::logReportAccess('account_statement', $sanitized);

                $account = SavingsAccount::where('account_number', $account_number)
                    ->with(['savings_type.currency', 'member'])
                    ->first();
                
                if (!$account) {
                    return back()->with('error', _lang('Account not found'));
                }

                // Use parameterized queries to prevent SQL injection
                DB::select("SELECT ((SELECT IFNULL(SUM(amount),0) FROM transactions WHERE dr_cr = 'cr' AND member_id = ? AND savings_account_id = ? AND status=2 AND created_at < ?) - (SELECT IFNULL(SUM(amount),0) FROM transactions WHERE dr_cr = 'dr' AND member_id = ? AND savings_account_id = ? AND status = 2 AND created_at < ?)) into @openingBalance", 
                    [$account->member_id, $account->id, $date1, $account->member_id, $account->id, $date1]);

                $data['report_data'] = DB::select("SELECT ? as trans_date,'Opening Balance' as description, 0 as 'debit', 0 as 'credit', @openingBalance as 'balance'
                UNION ALL
                SELECT date(trans_date), description, debit, credit, @openingBalance := @openingBalance + (credit - debit) as balance FROM
                (SELECT date(transactions.trans_date) as trans_date, transactions.description, IF(transactions.dr_cr='dr',transactions.amount,0) as debit, IF(transactions.dr_cr='cr',transactions.amount,0) as credit FROM `transactions` JOIN savings_accounts ON savings_account_id=savings_accounts.id WHERE savings_accounts.id = ? AND transactions.member_id = ? AND transactions.status=2 AND date(transactions.trans_date) >= ? AND date(transactions.trans_date) <= ?)
                as all_transaction", 
                    [$date1, $account->id, $account->member_id, $date1, $date2]);

                $data['date1'] = $date1;
                $data['date2'] = $date2;
                $data['account_number'] = $account_number;
                $data['account'] = $account;
                
                return view('backend.admin.reports.account_statement', $data);
                
            } catch (Exception $e) {
                Log::error('Account statement report generation failed', [
                    'user_id' => auth()->id(),
                    'tenant_id' => app('tenant')->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return back()->with('error', 'Report generation failed. Please try again.');
            }
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
            try {
                // Validate input
                $validator = Validator::make($request->all(), [
                    'date1' => 'required|date|before_or_equal:date2',
                    'date2' => 'required|date|after_or_equal:date1',
                    'member_no' => 'nullable|string|max:50|regex:/^[A-Z0-9]+$/',
                    'status' => 'nullable|in:0,1,2,3',
                    'loan_type' => 'nullable|exists:loan_products,id',
                    'per_page' => 'nullable|integer|min:10|max:100'
                ], [
                    'date1.required' => 'Start date is required',
                    'date1.date' => 'Invalid start date format',
                    'date1.before_or_equal' => 'Start date must be before or equal to end date',
                    'date2.required' => 'End date is required',
                    'date2.date' => 'Invalid end date format',
                    'date2.after_or_equal' => 'End date must be after or equal to start date',
                    'member_no.regex' => 'Member number must contain only letters and numbers',
                    'status.in' => 'Invalid status value',
                    'loan_type.exists' => 'Invalid loan product selected',
                    'per_page.min' => 'Minimum 10 records per page',
                    'per_page.max' => 'Maximum 100 records per page'
                ]);

                if ($validator->fails()) {
                    return back()->withErrors($validator)->withInput();
                }

                // Sanitize inputs
                $sanitized = DataSanitizationService::sanitizeReportInputs($request->all());
                $date1 = $sanitized['date1'];
                $date2 = $sanitized['date2'];
                $member_no = $sanitized['member_no'];
                $status = $sanitized['status'];
                $loan_type = $sanitized['loan_type'];
                $perPage = $sanitized['per_page'] ?? 50;

                // Log report access
                AuditService::logReportAccess('loan_report', $sanitized);

                $query = Loan::select('loans.*')
                    ->with(['borrower:id,first_name,last_name,member_no', 'loan_product:id,name'])
                    ->when($status, function ($query, $status) {
                        return $query->where('status', $status);
                    })
                    ->when($loan_type, function ($query, $loan_type) {
                        return $query->where('loan_product_id', $loan_type);
                    })
                    ->when($member_no, function ($query, $member_no) {
                        return $query->whereHas('borrower', function ($query) use ($member_no) {
                            return $query->where('member_no', $member_no);
                        });
                    })
                    ->whereRaw("date(loans.created_at) >= ? AND date(loans.created_at) <= ?", [$date1, $date2])
                    ->orderBy('id', 'desc');

                $data['report_data'] = $query->paginate($perPage);
                $data['pagination'] = $data['report_data']->appends($request->query());
                $data['date1'] = $date1;
                $data['date2'] = $date2;
                $data['status'] = $status;
                $data['member_no'] = $member_no;
                $data['loan_type'] = $loan_type;
                
                return view('backend.admin.reports.loan_report', $data);
                
            } catch (Exception $e) {
                Log::error('Loan report generation failed', [
                    'user_id' => auth()->id(),
                    'tenant_id' => app('tenant')->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return back()->with('error', 'Report generation failed. Please try again.');
            }
        }
    }

    public function loan_due_report(Request $request) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $data = [];
        $date = date('Y-m-d');

        $data['report_data'] = LoanRepayment::selectRaw('loan_id, MIN(repayment_date) as earliest_repayment_date, SUM(principal_amount) as total_due')
            ->with('loan')
            ->whereRaw("repayment_date < ?", [$date])
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
                ->whereRaw("date(transactions.trans_date) >= ? AND date(transactions.trans_date) <= ?", [$date1, $date2])
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
                ->whereRaw("date(expenses.expense_date) >= ? AND date(expenses.expense_date) <= ?", [$date1, $date2])
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
            try {
                // Validate input
                $validator = Validator::make($request->all(), [
                    'year' => 'required|integer|min:2020|max:' . date('Y'),
                    'month' => 'required|integer|min:1|max:12',
                    'currency_id' => 'required|exists:currency,id'
                ], [
                    'year.required' => 'Year is required',
                    'year.integer' => 'Year must be a valid number',
                    'year.min' => 'Year cannot be before 2020',
                    'year.max' => 'Year cannot be in the future',
                    'month.required' => 'Month is required',
                    'month.integer' => 'Month must be a valid number',
                    'month.min' => 'Month must be between 1 and 12',
                    'month.max' => 'Month must be between 1 and 12',
                    'currency_id.required' => 'Currency is required',
                    'currency_id.exists' => 'Invalid currency selected'
                ]);

                if ($validator->fails()) {
                    return back()->withErrors($validator)->withInput();
                }

                // Sanitize inputs
                $sanitized = DataSanitizationService::sanitizeReportInputs($request->all());
                $year = $sanitized['year'];
                $month = $sanitized['month'];
                $currency_id = $sanitized['currency_id'];

                // Log report access
                AuditService::logReportAccess('revenue_report', $sanitized);

                $transaction_revenue = Transaction::selectRaw("CONCAT('Revenue from ', type), sum(charge) as amount")
                    ->whereRaw("YEAR(trans_date) = ? AND MONTH(trans_date) = ?", [$year, $month])
                    ->where('charge', '>', 0)
                    ->where('status', 2)
                    ->whereHas('account.savings_type', function ($query) use ($currency_id) {
                        return $query->where('currency_id', $currency_id);
                    })
                    ->groupBy('type');

                $maintainaince_fee = Transaction::selectRaw("CONCAT('Revenue from ', type), sum(amount) as amount")
                    ->whereRaw("YEAR(trans_date) = ? AND MONTH(trans_date) = ?", [$year, $month])
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
                    ->whereRaw("YEAR(trans_date) = ? AND MONTH(trans_date) = ?", [$year, $month])
                    ->where('dr_cr', 'dr')
                    ->where('transactions.status', 2)
                    ->whereHas('account.savings_type', function ($query) use ($currency_id) {
                        return $query->where('currency_id', $currency_id);
                    })
                    ->groupBy('type');

                $data['report_data'] = LoanPayment::selectRaw("'Revenue from Loan' as type, sum(interest + late_penalties) as amount")
                    ->whereRaw("YEAR(loan_payments.paid_at) = ? AND MONTH(loan_payments.paid_at) = ?", [$year, $month])
                    ->whereHas('loan', function ($query) use ($currency_id) {
                        return $query->where('currency_id', $currency_id);
                    })
                    ->union($transaction_revenue)
                    ->union($maintainaince_fee)
                    ->union($others_fee)
                    ->get();

                $data['year'] = $year;
                $data['month'] = $month;
                $data['currency_id'] = $currency_id;
                
                return view('backend.admin.reports.revenue_report', $data);
                
            } catch (Exception $e) {
                Log::error('Revenue report generation failed', [
                    'user_id' => auth()->id(),
                    'tenant_id' => app('tenant')->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return back()->with('error', 'Report generation failed. Please try again.');
            }
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
            try {
                // Validate input
                $validator = Validator::make($request->all(), [
                    'date1' => 'required|date|before_or_equal:date2',
                    'date2' => 'required|date|after_or_equal:date1',
                    'bank_account_id' => 'nullable|exists:bank_accounts,id',
                    'status' => 'nullable|in:0,1,2',
                    'transaction_type' => 'nullable|string|max:50'
                ], [
                    'date1.required' => 'Start date is required',
                    'date1.date' => 'Invalid start date format',
                    'date1.before_or_equal' => 'Start date must be before or equal to end date',
                    'date2.required' => 'End date is required',
                    'date2.date' => 'Invalid end date format',
                    'date2.after_or_equal' => 'End date must be after or equal to start date',
                    'bank_account_id.exists' => 'Invalid bank account selected',
                    'status.in' => 'Invalid status value'
                ]);

                if ($validator->fails()) {
                    return back()->withErrors($validator)->withInput();
                }

                // Sanitize inputs
                $sanitized = DataSanitizationService::sanitizeReportInputs($request->all());
                $date1 = $sanitized['date1'];
                $date2 = $sanitized['date2'];
                $bank_account_id = $sanitized['bank_account_id'];
                $status = $sanitized['status'];
                $transaction_type = $sanitized['transaction_type'];

                // Log report access
                AuditService::logReportAccess('bank_transactions', $sanitized);

                $query = BankTransaction::select('bank_transactions.*')
                    ->with(['bankAccount.currency'])
                    ->when($status, function ($query, $status) {
                        return $query->where('status', $status);
                    })
                    ->when($transaction_type, function ($query, $transaction_type) {
                        return $query->where('bank_transactions.type', $transaction_type);
                    })
                    ->when($bank_account_id, function ($query, $bank_account_id) {
                        return $query->where('bank_transactions.bank_account_id', $bank_account_id);
                    })
                    ->whereRaw("date(bank_transactions.trans_date) >= ? AND date(bank_transactions.trans_date) <= ?", [$date1, $date2])
                    ->orderBy('bank_transactions.trans_date', 'desc');

                $data['report_data'] = $query->paginate(50);
                $data['pagination'] = $data['report_data']->appends($request->query());
                $data['date1'] = $date1;
                $data['date2'] = $date2;
                $data['status'] = $status;
                $data['bank_account_id'] = $bank_account_id;
                $data['transaction_type'] = $transaction_type;
                
                return view('backend.admin.reports.bank_transactions_report', $data);
                
            } catch (Exception $e) {
                Log::error('Bank transactions report generation failed', [
                    'user_id' => auth()->id(),
                    'tenant_id' => app('tenant')->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return back()->with('error', 'Report generation failed. Please try again.');
            }
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
        
        // Calculate total outstanding correctly including interest
        $totalOutstanding = Loan::where('status', 1)
            ->with('payments')
            ->get()
            ->sum(function ($loan) {
                $totalPaidIncludingInterest = $loan->total_paid + $loan->payments->sum('interest');
                return $loan->total_payable - $totalPaidIncludingInterest;
            });
        $data['total_outstanding'] = (object)['total_outstanding' => $totalOutstanding];

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
            try {
                // Validate input
                $validator = Validator::make($request->all(), [
                    'date1' => 'required|date|before_or_equal:date2',
                    'date2' => 'required|date|after_or_equal:date1',
                    'collector_id' => 'nullable|exists:users,id'
                ], [
                    'date1.required' => 'Start date is required',
                    'date1.date' => 'Invalid start date format',
                    'date1.before_or_equal' => 'Start date must be before or equal to end date',
                    'date2.required' => 'End date is required',
                    'date2.date' => 'Invalid end date format',
                    'date2.after_or_equal' => 'End date must be after or equal to start date',
                    'collector_id.exists' => 'Invalid collector selected'
                ]);

                if ($validator->fails()) {
                    return back()->withErrors($validator)->withInput();
                }

                // Sanitize inputs
                $sanitized = DataSanitizationService::sanitizeReportInputs($request->all());
                $date1 = $sanitized['date1'];
                $date2 = $sanitized['date2'];
                $collector_id = $sanitized['collector_id'];

                // Log report access
                AuditService::logReportAccess('collections_report', $sanitized);

                @ini_set('max_execution_time', 0);
                @set_time_limit(0);

                $data['report_data'] = LoanPayment::with(['loan.borrower', 'loan.created_by', 'member'])
                    ->when($collector_id, function($query, $collector_id) {
                        return $query->whereHas('loan', function($q) use ($collector_id) {
                            return $q->where('created_user_id', $collector_id);
                        });
                    })
                    ->whereRaw("date(paid_at) >= ? AND date(paid_at) <= ?", [$date1, $date2])
                    ->orderBy('paid_at', 'desc')
                    ->get();

                $data['date1'] = $date1;
                $data['date2'] = $date2;
                $data['collector_id'] = $collector_id;
                return view('backend.admin.reports.collections_report', $data);
                
            } catch (Exception $e) {
                Log::error('Collections report generation failed', [
                    'user_id' => auth()->id(),
                    'tenant_id' => app('tenant')->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return back()->with('error', 'Report generation failed. Please try again.');
            }
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
            try {
                // Validate input
                $validator = Validator::make($request->all(), [
                    'year' => 'required|integer|min:2020|max:' . date('Y'),
                    'month' => 'required|integer|min:1|max:12'
                ], [
                    'year.required' => 'Year is required',
                    'year.integer' => 'Year must be a valid number',
                    'year.min' => 'Year cannot be before 2020',
                    'year.max' => 'Year cannot be in the future',
                    'month.required' => 'Month is required',
                    'month.integer' => 'Month must be a valid number',
                    'month.min' => 'Month must be between 1 and 12',
                    'month.max' => 'Month must be between 1 and 12'
                ]);

                if ($validator->fails()) {
                    return back()->withErrors($validator)->withInput();
                }

                // Sanitize inputs
                $sanitized = DataSanitizationService::sanitizeReportInputs($request->all());
                $year = $sanitized['year'];
                $month = $sanitized['month'];

                // Log report access
                AuditService::logReportAccess('monthly_report', $sanitized);

                $data['monthly_data'] = [
                    'loans_disbursed' => Loan::whereRaw("YEAR(release_date) = ? AND MONTH(release_date) = ?", [$year, $month])
                        ->where('status', 1)
                        ->sum('applied_amount'),
                    'loans_collected' => LoanPayment::whereRaw("YEAR(paid_at) = ? AND MONTH(paid_at) = ?", [$year, $month])
                        ->sum('total_amount'),
                    'new_borrowers' => Loan::whereRaw("YEAR(release_date) = ? AND MONTH(release_date) = ?", [$year, $month])
                        ->where('status', 1)
                        ->distinct('borrower_id')
                        ->count('borrower_id'),
                    'fees_collected' => LoanPayment::whereRaw("YEAR(paid_at) = ? AND MONTH(paid_at) = ?", [$year, $month])
                        ->sum(DB::raw('interest + late_penalties'))
                ];

                $data['year'] = $year;
                $data['month'] = $month;
                
                return view('backend.admin.reports.monthly_report', $data);
                
            } catch (Exception $e) {
                Log::error('Monthly report generation failed', [
                    'user_id' => auth()->id(),
                    'tenant_id' => app('tenant')->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return back()->with('error', 'Report generation failed. Please try again.');
            }
        }
    }

    public function outstanding_report(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.outstanding_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data = [];
            $data['report_data'] = Loan::with(['borrower', 'loan_product', 'currency', 'payments'])
                ->where('status', 1)
                ->get()
                ->map(function ($loan) {
                    // Calculate outstanding amount correctly including interest
                    $totalPaidIncludingInterest = $loan->total_paid + $loan->payments->sum('interest');
                    $loan->outstanding_amount = $loan->total_payable - $totalPaidIncludingInterest;
                    return $loan;
                })
                ->sortByDesc('outstanding_amount');

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
                ->where('repayment_date', '<', now()->toDateString()) // Use consistent date filtering
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
            // Clear any previous session data to ensure clean state
            $data = [];
            $tenant = app('tenant');
            $data['asset_management_enabled'] = $tenant->isAssetManagementEnabled();
            return view('backend.admin.reports.balance_sheet', $data);
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data = [];
            $as_of_date = $request->as_of_date ?? date('Y-m-d');
            $tenant = app('tenant');
            $tenant_id = $tenant->id;

            try {
                // Get comprehensive asset data
                $data['assets'] = $this->get_comprehensive_assets($as_of_date, $tenant_id);
                
                // Get comprehensive liability data
                $data['liabilities'] = $this->get_comprehensive_liabilities($as_of_date, $tenant_id);
                
                // Get comprehensive equity data
                $data['equity'] = $this->get_comprehensive_equity($as_of_date, $tenant_id);
                
                // Calculate totals
                $data['total_assets'] = array_sum($data['assets']);
                $data['total_liabilities'] = array_sum($data['liabilities']);
                $data['total_equity'] = array_sum($data['equity']);
                
                // Set flag to indicate report data is available
                $data['balance_sheet_data'] = true;
                $data['asset_management_enabled'] = $tenant->isAssetManagementEnabled();
                
                // Debug information (remove in production)
                $data['debug'] = [
                    'as_of_date' => $as_of_date,
                    'tenant_id' => $tenant_id,
                    'asset_management_enabled' => $tenant->isAssetManagementEnabled(),
                    'assets_count' => \App\Models\Asset::where('tenant_id', $tenant_id)->count(),
                    'loans_count' => \App\Models\Loan::where('tenant_id', $tenant_id)->count(),
                    'bank_accounts_count' => \App\Models\BankAccount::where('tenant_id', $tenant_id)->count(),
                ];
                
            } catch (\Exception $e) {
                // If there's an error, set default values
                $data['assets'] = [
                    'cash_in_hand' => 0,
                    'bank_balances' => 0,
                    'loan_portfolio' => 0,
                    'fixed_assets' => 0,
                    'lease_receivables' => 0,
                    'other_assets' => 0
                ];
                $data['liabilities'] = [
                    'savings_deposits' => 0,
                    'borrowings' => 0,
                    'accrued_expenses' => 0,
                    'other_liabilities' => 0
                ];
                $data['equity'] = [
                    'retained_earnings' => 0,
                    'capital' => 0,
                    'reserves' => 0
                ];
                $data['total_assets'] = 0;
                $data['total_liabilities'] = 0;
                $data['total_equity'] = 0;
                $data['balance_sheet_data'] = true;
                $data['asset_management_enabled'] = $tenant->isAssetManagementEnabled();
                $data['error'] = $e->getMessage();
            }

            $data['as_of_date'] = $as_of_date;
            
            // Handle export request
            if ($request->export) {
                return $this->export_balance_sheet($data);
            }
            
            return view('backend.admin.reports.balance_sheet', $data);
        }
    }

    public function profit_loss_statement(Request $request) {
        if ($request->isMethod('get')) {
            return view('backend.admin.reports.profit_loss_statement');
        } else if ($request->isMethod('post')) {
            try {
                // Validate input
                $validator = Validator::make($request->all(), [
                    'date1' => 'required|date|before_or_equal:date2',
                    'date2' => 'required|date|after_or_equal:date1'
                ], [
                    'date1.required' => 'Start date is required',
                    'date1.date' => 'Invalid start date format',
                    'date1.before_or_equal' => 'Start date must be before or equal to end date',
                    'date2.required' => 'End date is required',
                    'date2.date' => 'Invalid end date format',
                    'date2.after_or_equal' => 'End date must be after or equal to start date'
                ]);

                if ($validator->fails()) {
                    return back()->withErrors($validator)->withInput();
                }

                // Sanitize inputs
                $sanitized = DataSanitizationService::sanitizeReportInputs($request->all());
                $date1 = $sanitized['date1'];
                $date2 = $sanitized['date2'];

                // Log report access
                AuditService::logReportAccess('profit_loss_statement', $sanitized);

                // Revenue
                $data['revenue'] = [
                    'interest_income' => LoanPayment::whereRaw("date(paid_at) >= ? AND date(paid_at) <= ?", [$date1, $date2])->sum('interest'),
                    'penalty_income' => LoanPayment::whereRaw("date(paid_at) >= ? AND date(paid_at) <= ?", [$date1, $date2])->sum('late_penalties'),
                    'fee_income' => Transaction::whereRaw("date(trans_date) >= ? AND date(trans_date) <= ?", [$date1, $date2])->where('charge', '>', 0)->sum('charge')
                ];

                // Expenses
                $data['expenses'] = [
                    'operating_expenses' => Expense::whereRaw("date(expense_date) >= ? AND date(expense_date) <= ?", [$date1, $date2])->sum('amount'),
                    'interest_expense' => 0 // Would need borrowing module
                ];

                $data['date1'] = $date1;
                $data['date2'] = $date2;
                
                return view('backend.admin.reports.profit_loss_statement', $data);
                
            } catch (Exception $e) {
                Log::error('Profit loss statement generation failed', [
                    'user_id' => auth()->id(),
                    'tenant_id' => app('tenant')->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return back()->with('error', 'Report generation failed. Please try again.');
            }
        }
    }

    // Comprehensive helper methods for balance sheet
    private function get_comprehensive_assets($as_of_date, $tenant_id) {
        $assets = [];
        
        // Cash in Hand - from cash transactions
        $assets['cash_in_hand'] = $this->get_cash_in_hand($as_of_date, $tenant_id);
        
        // Bank Balances
        $assets['bank_balances'] = $this->get_bank_balances($as_of_date, $tenant_id);
        
        // Loan Portfolio (Outstanding loans) - Calculate correctly including interest
        $loanPortfolio = Loan::where('status', 1)
            ->where('tenant_id', $tenant_id)
            ->with('payments')
            ->get()
            ->sum(function ($loan) {
                $totalPaidIncludingInterest = $loan->total_paid + $loan->payments->sum('interest');
                return $loan->total_payable - $totalPaidIncludingInterest;
            });
        $assets['loan_portfolio'] = $loanPortfolio;
        
        // Fixed Assets (if asset management is enabled)
        $tenant = Tenant::find($tenant_id);
        if ($tenant && $tenant->isAssetManagementEnabled()) {
            $assets['fixed_assets'] = $this->get_fixed_assets_value($as_of_date, $tenant_id);
            $assets['lease_receivables'] = $this->get_lease_receivables($as_of_date, $tenant_id);
        } else {
            $assets['fixed_assets'] = 0;
            $assets['lease_receivables'] = 0;
        }
        
        // Other Assets (prepaid expenses, etc.)
        $assets['other_assets'] = $this->get_other_assets($as_of_date, $tenant_id);
        
        return $assets;
    }
    
    private function get_comprehensive_liabilities($as_of_date, $tenant_id) {
        $liabilities = [];
        
        // Savings Deposits
        $liabilities['savings_deposits'] = $this->get_savings_liabilities($as_of_date, $tenant_id);
        
        // Borrowings (if any external borrowings exist)
        $liabilities['borrowings'] = $this->get_borrowings($as_of_date, $tenant_id);
        
        // Accrued Expenses
        $liabilities['accrued_expenses'] = $this->get_accrued_expenses($as_of_date, $tenant_id);
        
        // Other Liabilities
        $liabilities['other_liabilities'] = $this->get_other_liabilities($as_of_date, $tenant_id);
        
        return $liabilities;
    }
    
    private function get_comprehensive_equity($as_of_date, $tenant_id) {
        $equity = [];
        
        // Retained Earnings
        $equity['retained_earnings'] = $this->get_retained_earnings($as_of_date, $tenant_id);
        
        // Capital (initial capital or equity injections)
        $equity['capital'] = $this->get_capital($as_of_date, $tenant_id);
        
        // Reserves
        $equity['reserves'] = $this->get_reserves($as_of_date, $tenant_id);
        
        // Asset Purchase Adjustment - This is now handled by proper financial transactions
        // when assets are purchased, so we no longer need this adjustment
        $equity['asset_purchase_adjustment'] = 0;
        
        return $equity;
    }
    
    private function get_cash_in_hand($as_of_date, $tenant_id) {
        // Calculate cash in hand from cash transactions
        $cash_deposits = Transaction::where('tenant_id', $tenant_id)
            ->where('method', 'cash')
            ->where('dr_cr', 'cr')
            ->where('status', 2)
            ->whereRaw('date(trans_date) <= ?', [$as_of_date])
            ->sum('amount');
            
        $cash_withdrawals = Transaction::where('tenant_id', $tenant_id)
            ->where('method', 'cash')
            ->where('dr_cr', 'dr')
            ->where('status', 2)
            ->whereRaw('date(trans_date) <= ?', [$as_of_date])
            ->sum('amount');
            
        return $cash_deposits - $cash_withdrawals;
    }

    private function get_bank_balances($as_of_date, $tenant_id) {
        return DB::select('SELECT SUM(
            (SELECT IFNULL(SUM(amount),0) FROM bank_transactions WHERE dr_cr = "cr" AND status = 1 AND bank_account_id = bank_accounts.id AND date(trans_date) <= ?) -
            (SELECT IFNULL(SUM(amount),0) FROM bank_transactions WHERE dr_cr = "dr" AND status = 1 AND bank_account_id = bank_accounts.id AND date(trans_date) <= ?)
        ) as balance FROM bank_accounts WHERE tenant_id = ?', [$as_of_date, $as_of_date, $tenant_id])[0]->balance ?? 0;
    }

    private function get_savings_liabilities($as_of_date, $tenant_id) {
        return DB::select('SELECT SUM(
            (SELECT IFNULL(SUM(amount),0) FROM transactions WHERE dr_cr = "cr" AND status = 2 AND savings_account_id = savings_accounts.id AND date(trans_date) <= ?) -
            (SELECT IFNULL(SUM(amount),0) FROM transactions WHERE dr_cr = "dr" AND status = 2 AND savings_account_id = savings_accounts.id AND date(trans_date) <= ?)
        ) as balance FROM savings_accounts WHERE tenant_id = ?', [$as_of_date, $as_of_date, $tenant_id])[0]->balance ?? 0;
    }
    
    private function get_fixed_assets_value($as_of_date, $tenant_id) {
        $assets = Asset::where('tenant_id', $tenant_id)
            ->where('status', 'active')
            ->where('purchase_date', '<=', $as_of_date)
            ->get();
            
        $total_value = 0;
        foreach ($assets as $asset) {
            $total_value += $asset->calculateCurrentValue($as_of_date);
        }
        
        return $total_value;
    }
    
    private function get_lease_receivables($as_of_date, $tenant_id) {
        // Since there's no amount_paid column, we'll calculate based on daily rate
        // and days elapsed for active leases
        $active_leases = AssetLease::where('tenant_id', $tenant_id)
            ->where('status', 'active')
            ->where('start_date', '<=', $as_of_date)
            ->where(function($query) use ($as_of_date) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $as_of_date);
            })
            ->get();
            
        $total_receivables = 0;
        foreach ($active_leases as $lease) {
            $end_date = $lease->end_date ? $lease->end_date : $as_of_date;
            $days_elapsed = \Carbon\Carbon::parse($lease->start_date)->diffInDays(\Carbon\Carbon::parse($end_date));
            $total_receivables += $days_elapsed * $lease->daily_rate;
        }
        
        return $total_receivables;
    }
    
    private function get_other_assets($as_of_date, $tenant_id) {
        // Prepaid expenses, advances, etc.
        // This would depend on your specific business needs
        return 0;
    }
    
    private function get_borrowings($as_of_date, $tenant_id) {
        // External borrowings, loans from other institutions
        // This would depend on your specific business needs
        return 0;
    }
    
    private function get_accrued_expenses($as_of_date, $tenant_id) {
        // Accrued expenses that haven't been paid yet
        // This would depend on your specific business needs
        return 0;
    }
    
    private function get_other_liabilities($as_of_date, $tenant_id) {
        // Other liabilities like deferred revenue, etc.
        // This would depend on your specific business needs
        return 0;
    }

    private function get_retained_earnings($as_of_date, $tenant_id) {
        // Revenue from loan interest and penalties
        $revenue = LoanPayment::where('tenant_id', $tenant_id)
            ->whereRaw('date(paid_at) <= ?', [$as_of_date])
            ->sum(DB::raw('interest + late_penalties'));
            
        // Revenue from transaction charges
        $revenue += Transaction::where('tenant_id', $tenant_id)
            ->where('charge', '>', 0)
            ->where('status', 2)
            ->whereRaw('date(trans_date) <= ?', [$as_of_date])
            ->sum('charge');
            
        // Revenue from asset leases
        $revenue += AssetLease::where('tenant_id', $tenant_id)
            ->where('status', 'completed')
            ->whereRaw('date(end_date) <= ?', [$as_of_date])
            ->sum('total_amount');
        
        // Expenses
        $expenses = Expense::where('tenant_id', $tenant_id)
            ->whereRaw('date(expense_date) <= ?', [$as_of_date])
            ->sum('amount');
            
        return $revenue - $expenses;
    }
    
    private function get_capital($as_of_date, $tenant_id) {
        // Initial capital or equity injections
        // This would depend on your specific business needs
        // Could be tracked in a separate capital table
        return 0;
    }
    
    private function get_reserves($as_of_date, $tenant_id) {
        // Reserves for contingencies, etc.
        // This would depend on your specific business needs
        return 0;
    }
    
    private function get_asset_purchase_adjustment($as_of_date, $tenant_id) {
        // Calculate the current value of assets purchased up to the as_of_date
        // This represents the "unrecorded" cash outflow or liability that should have been created
        // when assets were purchased, but wasn't properly recorded in the financial system.
        // We use current value to match what's shown in the assets section.
        
        $tenant = Tenant::find($tenant_id);
        if (!$tenant || !$tenant->isAssetManagementEnabled()) {
            return 0;
        }
        
        $assets = Asset::where('tenant_id', $tenant_id)
            ->where('status', 'active')
            ->where('purchase_date', '<=', $as_of_date)
            ->get();
            
        $total_current_value = 0;
        foreach ($assets as $asset) {
            $total_current_value += $asset->calculateCurrentValue($as_of_date);
        }
        
        return $total_current_value;
    }
    
    // Export functionality
    private function export_balance_sheet($data) {
        $filename = 'balance_sheet_' . $data['as_of_date'] . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($file, ['Balance Sheet as of ' . $data['as_of_date']]);
            fputcsv($file, []); // Empty row
            
            // Assets section
            fputcsv($file, ['ASSETS']);
            fputcsv($file, ['Description', 'Amount']);
            foreach ($data['assets'] as $key => $value) {
                fputcsv($file, [ucwords(str_replace('_', ' ', $key)), number_format($value, 2)]);
            }
            fputcsv($file, ['Total Assets', number_format($data['total_assets'], 2)]);
            fputcsv($file, []); // Empty row
            
            // Liabilities section
            fputcsv($file, ['LIABILITIES']);
            fputcsv($file, ['Description', 'Amount']);
            foreach ($data['liabilities'] as $key => $value) {
                fputcsv($file, [ucwords(str_replace('_', ' ', $key)), number_format($value, 2)]);
            }
            fputcsv($file, ['Total Liabilities', number_format($data['total_liabilities'], 2)]);
            fputcsv($file, []); // Empty row
            
            // Equity section
            fputcsv($file, ['EQUITY']);
            fputcsv($file, ['Description', 'Amount']);
            foreach ($data['equity'] as $key => $value) {
                $description = ucwords(str_replace('_', ' ', $key));
                if ($key === 'asset_purchase_adjustment') {
                    $description = 'Asset Purchase Adjustment';
                }
                fputcsv($file, [$description, number_format($value, 2)]);
            }
            fputcsv($file, ['Total Equity', number_format($data['total_equity'], 2)]);
            fputcsv($file, []); // Empty row
            
            fputcsv($file, ['Total Liabilities & Equity', number_format($data['total_liabilities'] + $data['total_equity'], 2)]);
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

}
