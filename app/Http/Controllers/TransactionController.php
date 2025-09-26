<?php
namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Notifications\DepositMoney;
use App\Notifications\WithdrawMoney;
use App\Services\AuditService;
use App\Services\BankingService;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

class TransactionController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_timezone());
        
        // Apply authorization middleware
        $this->middleware('auth');
        $this->middleware('transaction.auth:transactions.view')->only(['index', 'show']);
        $this->middleware('transaction.auth:transactions.create')->only(['create', 'store']);
        $this->middleware('transaction.auth:transactions.edit')->only(['edit', 'update']);
        $this->middleware('transaction.auth:transactions.delete')->only(['destroy']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $assets = ['datatable'];
        return view('backend.admin.transaction.list', compact('assets'));
    }

    public function get_table_data() {
        $transactions = Transaction::select([
                'transactions.*',
                'members.first_name as member_first_name',
                'members.last_name as member_last_name',
                'savings_accounts.account_number',
                'savings_products.name as savings_type_name',
                'currency.name as currency_name'
            ])
            ->leftJoin('members', 'transactions.member_id', '=', 'members.id')
            ->leftJoin('savings_accounts', 'transactions.savings_account_id', '=', 'savings_accounts.id')
            ->leftJoin('savings_products', 'savings_accounts.savings_product_id', '=', 'savings_products.id')
            ->leftJoin('currency', 'savings_products.currency_id', '=', 'currency.id')
            ->orderBy("transactions.trans_date", "desc");

        return Datatables::eloquent($transactions)
            ->editColumn('member_first_name', function ($transactions) {
                return $transactions->member_first_name . ' ' . $transactions->member_last_name;
            })
            ->editColumn('dr_cr', function ($transactions) {
                return strtoupper($transactions->dr_cr);
            })
            ->editColumn('status', function ($transactions) {
                return transaction_status($transactions->status);
            })
            ->editColumn('amount', function ($transaction) {
                $symbol = $transaction->dr_cr == 'dr' ? '-' : '+';
                $class  = $transaction->dr_cr == 'dr' ? 'text-danger' : 'text-success';
                return '<span class="' . $class . '">' . $symbol . ' ' . decimalPlace($transaction->amount, currency($transaction->currency_name)) . '</span>';
            })
            ->editColumn('type', function ($transaction) {
                return ucwords(str_replace('_', ' ', $transaction->type));
            })
            ->filterColumn('member_first_name', function ($query, $keyword) {
                $query->where(function($q) use ($keyword) {
                    $q->where("members.first_name", "like", "{$keyword}%")
                      ->orWhere("members.last_name", "like", "{$keyword}%");
                });
            }, true)
            ->addColumn('action', function ($transaction) {
                return '<div class="dropdown text-center">'
                . '<button class="btn btn-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown">' . _lang('Action')
                . '&nbsp;</button>'
                . '<div class="dropdown-menu">'
                . '<a class="dropdown-item" href="' . route('transactions.edit', $transaction['id']) . '"><i class="ti-pencil-alt"></i> ' . _lang('Edit') . '</a>'
                . '<a class="dropdown-item" href="' . route('transactions.show', $transaction['id']) . '"><i class="ti-eye"></i>  ' . _lang('Details') . '</a>'
                . '<a class="dropdown-item" href="' . route('transactions.show', $transaction['id']) . '?print=general" target="_blank"><i class="fas fa-print"></i>  ' . _lang('Regular Print') . '</a>'
                . '<a class="dropdown-item" href="' . route('transactions.show', $transaction['id']) . '?print=pos" target="_blank"><i class="fas fa-print"></i>  ' . _lang('POS Receipt') . '</a>'
                . '<form action="' . route('transactions.destroy', $transaction['id']) . '" method="post">'
                . csrf_field()
                . '<input name="_method" type="hidden" value="DELETE">'
                . '<button class="dropdown-item btn-remove" type="submit"><i class="ti-trash"></i> ' . _lang('Delete') . '</button>'
                    . '</form>'
                    . '</div>'
                    . '</div>';
            })
            ->setRowId(function ($transaction) {
                return "row_" . $transaction->id;
            })
            ->rawColumns(['action', 'status', 'amount'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        if (! $request->ajax()) {
            return view('backend.admin.transaction.create');
        } else {
            return view('backend.admin.transaction.modal.create');
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'trans_date'         => 'required',
            'member_id'          => 'required',
            'savings_account_id' => 'required',
            'amount'             => 'required|numeric',
            'dr_cr'              => 'required|in:dr,cr',
            'type'               => 'required',
            'status'             => 'required',
            'description'        => 'required',
        ], [
            'dr_cr.in' => 'Transaction must have a debit or credit',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $accountType = SavingsAccount::find($request->savings_account_id)->savings_type;

        if (! $accountType) {
            return back()
                ->with('error', _lang('Account type not found'))
                ->withInput();
        }

        // Use database transaction with pessimistic locking to prevent race conditions
        try {
            return DB::transaction(function() use ($request, $accountType) {
                // Lock the account to prevent concurrent modifications
                $account = SavingsAccount::where('id', $request->savings_account_id)
                    ->where('member_id', $request->member_id)
                    ->where('tenant_id', request()->tenant->id)
                    ->lockForUpdate()
                    ->first();

                if (!$account) {
                    throw new \Exception('Account not found or unauthorized access');
                }

                if ($request->dr_cr == 'dr') {
                    if ($accountType->allow_withdraw == 0) {
                        throw new \Exception(_lang('Withdraw is not allowed for') . ' ' . $accountType->name);
                    }

                    $account_balance = $this->getAccountBalanceAtomic($request->savings_account_id, $request->member_id);
                    if (($account_balance - $request->amount) < $accountType->minimum_account_balance) {
                        throw new \Exception(_lang('Sorry Minimum account balance will be exceeded'));
                    }

                    if ($account_balance < $request->amount) {
                        throw new \Exception(_lang('Insufficient account balance'));
                    }

                } else {
                    if ($request->amount < $accountType->minimum_deposit_amount) {
                        throw new \Exception(_lang('You must deposit minimum') . ' ' . $accountType->minimum_deposit_amount . ' ' . $accountType->currency->name);
                    }
                }

                $transaction                     = new Transaction();
                $transaction->trans_date         = $request->input('trans_date');
                $transaction->member_id          = $request->input('member_id');
                $transaction->savings_account_id = $request->input('savings_account_id');
                $transaction->amount             = $request->input('amount');
                $transaction->dr_cr              = $request->dr_cr == 'dr' ? 'dr' : 'cr';
                $transaction->type               = ucwords($request->type);
                $transaction->method             = 'Manual';
                $transaction->status             = $request->input('status');
                $transaction->description        = $request->input('description');
                $transaction->created_user_id    = auth()->id();

                $transaction->save();

                // Process bank account transaction automatically
                $bankingService = new BankingService();
                $bankingService->processMemberTransaction($transaction);

                // Log audit trail for transaction creation
                $transactionType = $transaction->dr_cr == 'dr' ? 'withdrawal' : 'deposit';
                AuditService::logCreated($transaction, 'Transaction created: ' . $transactionType . ' - ' . $transaction->amount . ' (' . $transaction->type . ')');

                if ($transaction->dr_cr == 'dr') {
                    try {
                        $transaction->member->notify(new WithdrawMoney($transaction));
                    } catch (\Exception $e) {}
                } else if ($transaction->dr_cr == 'cr') {
                    try {
                        $transaction->member->notify(new DepositMoney($transaction));
                    } catch (\Exception $e) {}
                }

                if (! $request->ajax()) {
                    return redirect()->route('transactions.show', $transaction->id)->with('success', _lang('Transaction completed successfully'));
                } else {
                    return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Transaction completed successfully'), 'data' => $transaction, 'table' => '#transactions_table']);
                }
            }, 5); // 5 second timeout for transaction
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $e->getMessage()]);
            } else {
                return back()->with('error', $e->getMessage())->withInput();
            }
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $tenant, $id) {
        $alert_col = 'col-lg-8 offset-lg-2';
        $transaction = Transaction::find($id);
        if (! $request->ajax()) {
            return view('backend.admin.transaction.view', compact('transaction', 'id', 'alert_col'));
        } else {
            return view('backend.admin.transaction.modal.view', compact('transaction', 'id'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $tenant, $id) {
        $transaction = Transaction::find($id);
        if (! $request->ajax()) {
            return view('backend.admin.transaction.edit', compact('transaction', 'id'));
        } else {
            return view('backend.admin.transaction.modal.edit', compact('transaction', 'id'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $tenant, $id) {
        $validator = Validator::make($request->all(), [
            'trans_date'         => 'required',
            'member_id'          => 'required',
            'savings_account_id' => 'required',
            'amount'             => 'required|numeric',
            'status'             => 'required',
            'description'        => 'required',
        ], [
            'dr_cr.in' => 'Transaction must have a debit or credit',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('transactions.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $transaction = Transaction::find($id);
        $oldValues = $transaction->getAttributes();

        $accountType = SavingsAccount::find($request->savings_account_id)->savings_type;

        if (! $accountType) {
            return back()
                ->with('error', _lang('Account type not found'))
                ->withInput();
        }

        // Use database transaction with pessimistic locking to prevent race conditions
        try {
            return DB::transaction(function() use ($request, $transaction, $accountType) {
                // Lock the account to prevent concurrent modifications
                $account = SavingsAccount::where('id', $request->savings_account_id)
                    ->where('member_id', $request->member_id)
                    ->where('tenant_id', request()->tenant->id)
                    ->lockForUpdate()
                    ->first();

                if (!$account) {
                    throw new \Exception('Account not found or unauthorized access');
                }

                if ($request->dr_cr == 'dr') {
                    if ($accountType->allow_withdraw == 0) {
                        throw new \Exception(_lang('Withdraw is not allowed for') . ' ' . $accountType->name);
                    }

                    $account_balance = $this->getAccountBalanceAtomic($request->savings_account_id, $request->member_id);
                    $previousAmount  = $request->member_id == $transaction->member_id ? $transaction->amount : 0;

                    if ((($account_balance + $previousAmount) - $request->amount) < $accountType->minimum_account_balance) {
                        throw new \Exception(_lang('Sorry Minimum account balance will be exceeded'));
                    }

                    if (($account_balance + $previousAmount) < $request->amount) {
                        throw new \Exception(_lang('Insufficient account balance'));
                    }
                } else {
                    if ($request->amount < $accountType->minimum_deposit_amount) {
                        throw new \Exception(_lang('You must deposit minimum') . ' ' . $accountType->minimum_deposit_amount . ' ' . $accountType->currency->name);
                    }
                }

                $transaction->trans_date         = $request->input('trans_date');
                $transaction->member_id          = $request->input('member_id');
                $transaction->savings_account_id = $request->input('savings_account_id');
                $transaction->amount             = $request->input('amount');
                $transaction->status             = $request->input('status');
                $transaction->description        = $request->input('description');
                $transaction->updated_user_id    = auth()->id();
                $transaction->save();

                // Process bank account transaction automatically (for updates)
                $bankingService = new BankingService();
                $bankingService->processMemberTransaction($transaction);

                return $transaction;
            }, 5); // 5 second timeout for transaction
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $e->getMessage()]);
            } else {
                return back()->with('error', $e->getMessage())->withInput();
            }
        }

        // Log audit trail for transaction update
        $transactionType = $transaction->dr_cr == 'dr' ? 'withdrawal' : 'deposit';
        AuditService::logUpdated($transaction, $oldValues, 'Transaction updated: ' . $transactionType . ' - ' . $transaction->amount . ' (' . $transaction->type . ')');

        if (! $request->ajax()) {
            return redirect()->route('transactions.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $transaction, 'table' => '#transactions_table']);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($tenant, $id) {
        DB::beginTransaction();

        $transaction = Transaction::find($id);

        if ($transaction->loan_id != null) {
            $loan = Loan::find($transaction->loan_id);
            if ($loan->status == 1 || $loan->status == 2) {
                return back()->with('error', _lang('Sorry, this transaction is associated with a loan !'));
            }
        }

        // Log audit trail for transaction deletion
        AuditService::logDeleted($transaction, 'Transaction deleted: ' . ($transaction->dr_cr == 'dr' ? 'withdrawal' : 'deposit') . ' - ' . $transaction->amount . ' (' . $transaction->type . ')');

        $transaction->delete();

        DB::commit();

        return redirect()->route('transactions.index')->with('success', _lang('Deleted Successfully'));
    }

    /**
     * Get account balance atomically within a locked transaction
     * This method ensures balance calculation is consistent with locked account
     *
     * @param int $accountId
     * @param int $memberId
     * @return float
     */
    private function getAccountBalanceAtomic($accountId, $memberId)
    {
        // Calculate blocked amount using Eloquent for security
        $blockedAmount = \App\Models\Guarantor::join('loans', 'loans.id', 'guarantors.loan_id')
            ->whereIn('loans.status', [0, 1]) // Use whereIn instead of whereRaw
            ->where('guarantors.member_id', $memberId)
            ->where('guarantors.savings_account_id', $accountId)
            ->sum('guarantors.amount');

        // Calculate balance using parameterized query with proper status handling
        $result = DB::select("
            SELECT (
                (SELECT IFNULL(SUM(amount), 0) 
                 FROM transactions 
                 WHERE dr_cr = 'cr' 
                   AND member_id = ? 
                   AND savings_account_id = ? 
                   AND status = 2) - 
                (SELECT IFNULL(SUM(amount), 0) 
                 FROM transactions 
                 WHERE dr_cr = 'dr' 
                   AND member_id = ? 
                   AND savings_account_id = ? 
                   AND status = 2)
            ) as balance
        ", [$memberId, $accountId, $memberId, $accountId]);

        $balance = $result[0]->balance ?? 0;
        return $balance - $blockedAmount;
    }
}