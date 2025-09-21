<?php
namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Services\AuditService;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

class BankTransactionController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_timezone());
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $assets = ['datatable'];
        return view('backend.admin.bank_transaction.list', compact('assets'));
    }

    public function get_table_data() {
        $bankTransactions = BankTransaction::select('bank_transactions.*')
            ->with('bank_account.currency')
            ->orderBy("bank_transactions.id", "desc");

        return Datatables::eloquent($bankTransactions)
            ->editColumn('status', function ($bankTransaction) {
                if ($bankTransaction->status == BankTransaction::STATUS_PENDING) {
                    return show_status(_lang('Pending'), 'warning');
                } elseif ($bankTransaction->status == BankTransaction::STATUS_APPROVED) {
                    return show_status(_lang('Approved'), 'success');
                } elseif ($bankTransaction->status == BankTransaction::STATUS_REJECTED) {
                    return show_status(_lang('Rejected'), 'danger');
                } elseif ($bankTransaction->status == BankTransaction::STATUS_CANCELLED) {
                    return show_status(_lang('Cancelled'), 'secondary');
                }
                return show_status(_lang('Unknown'), 'secondary');
            })
            ->editColumn('amount', function ($bankTransaction) {
                return decimalPlace($bankTransaction->amount, currency($bankTransaction->bank_account->currency->name));
            })
            ->editColumn('type', function ($bankTransaction) {
                return $bankTransaction->type_label;
            })
            ->addColumn('action', function ($bankTransaction) {
                return '<div class="dropdown text-center">'
                . '<button class="btn btn-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown">' . _lang('Action')
                . '</button>'
                . '<div class="dropdown-menu">'
                . '<a class="dropdown-item ajax-modal" href="' . route('bank_transactions.edit', $bankTransaction['id']) . '" data-title="' . _lang('Update Bank Transaction') . '"><i class="fas fa-pencil-alt"></i> ' . _lang('Edit') . '</a>'
                . '<a class="dropdown-item ajax-modal" href="' . route('bank_transactions.show', $bankTransaction['id']) . '" data-title="' . _lang('Bank Transaction Details') . '"><i class="fas fa-eye"></i> ' . _lang('Details') . '</a>'
                . '<form action="' . route('bank_transactions.destroy', $bankTransaction['id']) . '" method="post">'
                . csrf_field()
                . '<input name="_method" type="hidden" value="DELETE">'
                . '<button class="dropdown-item btn-remove" type="submit"><i class="fas fa-trash-alt"></i> ' . _lang('Delete') . '</button>'
                    . '</form>'
                    . '</div>'
                    . '</div>';
            })
            ->setRowId(function ($bankTransaction) {
                return "row_" . $bankTransaction->id;
            })
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.admin.bank_transaction.modal.create');
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
            'trans_date'      => 'required',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'amount'          => 'required|numeric|min:0.01',
            'type'            => 'required|in:' . implode(',', [
                BankTransaction::TYPE_CASH_TO_BANK,
                BankTransaction::TYPE_BANK_TO_CASH,
                BankTransaction::TYPE_DEPOSIT,
                BankTransaction::TYPE_WITHDRAW,
                BankTransaction::TYPE_TRANSFER,
                BankTransaction::TYPE_LOAN_DISBURSEMENT,
                BankTransaction::TYPE_LOAN_REPAYMENT
            ]),
            'status'          => 'required|in:' . implode(',', [
                BankTransaction::STATUS_PENDING,
                BankTransaction::STATUS_APPROVED,
                BankTransaction::STATUS_REJECTED
            ]),
            'cheque_number'   => 'nullable|string|max:50',
            'description'     => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('bank_transactions.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $bankAccount = BankAccount::find($request->bank_account_id);
        if (!$bankAccount) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => [_lang('Bank account not found')]]);
            } else {
                return back()->with('error', _lang('Bank account not found'));
            }
        }

        // Check if account is active
        if (!$bankAccount->is_active) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => [_lang('Bank account is not active')]]);
            } else {
                return back()->with('error', _lang('Bank account is not active'));
            }
        }

        // Parse and validate transaction date
        try {
            $transDate = \Carbon\Carbon::parse($request->trans_date)->format('Y-m-d');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => [_lang('Invalid date format')]]);
            } else {
                return back()->with('error', _lang('Invalid date format'));
            }
        }

        if ($transDate < $bankAccount->opening_date->format('Y-m-d')) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => [_lang('Transaction date cannot be smaller than account opening date')]]);
            } else {
                return back()->with('error', _lang('Transaction date cannot be smaller than account opening date'));
            }
        }

        // Validate future dates (optional business rule)
        if ($transDate > now()->addDays(30)->format('Y-m-d')) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => [_lang('Transaction date cannot be more than 30 days in the future')]]);
            } else {
                return back()->with('error', _lang('Transaction date cannot be more than 30 days in the future'));
            }
        }

        // Handle file attachment
        $attachment = '';
        if ($request->hasfile('attachment')) {
            $file = $request->file('attachment');
            $attachment = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/media/", $attachment);
        }

        DB::beginTransaction();

        try {
            $bankTransaction = new BankTransaction();
            $bankTransaction->trans_date = $transDate;
            $bankTransaction->bank_account_id = $request->input('bank_account_id');
            $bankTransaction->amount = $request->input('amount');
            $bankTransaction->type = $request->input('type');
            $bankTransaction->status = $request->input('status');
            $bankTransaction->description = $request->input('description');
            $bankTransaction->cheque_number = $request->input('cheque_number');
            $bankTransaction->attachment = $attachment;
            $bankTransaction->created_user_id = auth()->id();
            $bankTransaction->tenant_id = auth()->user()->tenant_id ?? 1;

            // Determine debit/credit based on transaction type
            if (in_array($request->type, [
                BankTransaction::TYPE_CASH_TO_BANK,
                BankTransaction::TYPE_DEPOSIT,
                BankTransaction::TYPE_LOAN_REPAYMENT
            ])) {
                $bankTransaction->dr_cr = 'cr';
            } else {
                $bankTransaction->dr_cr = 'dr';
            }

            // Validate sufficient balance for debit transactions
            if ($bankTransaction->dr_cr === 'dr' && $bankTransaction->status == BankTransaction::STATUS_APPROVED) {
                if (!$bankAccount->hasSufficientBalance($bankTransaction->amount)) {
                    throw new \Exception('Insufficient balance. Available: ' . $bankAccount->formatted_balance);
                }
            }

            $bankTransaction->save();

            // Update bank account balance if transaction is approved
            if ($bankTransaction->status == BankTransaction::STATUS_APPROVED) {
                if ($bankTransaction->dr_cr === 'cr') {
                    // Credit transaction - increase balance
                    $bankAccount->current_balance += $bankTransaction->amount;
                } else {
                    // Debit transaction - decrease balance
                    $bankAccount->current_balance -= $bankTransaction->amount;
                }
                $bankAccount->last_balance_update = now();
                $bankAccount->save();
            }

            // Log bank transaction creation
            AuditService::logCreated($bankTransaction, 'Bank transaction created: ' . $bankTransaction->type . ' - ' . $bankTransaction->amount);

            DB::commit();

            if (!$request->ajax()) {
                return redirect()->route('bank_transactions.create')->with('success', _lang('Saved Successfully'));
            } else {
                return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $bankTransaction, 'table' => '#bank_transactions_table']);
            }

        } catch (\Exception $e) {
            DB::rollback();
            
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => ['Failed to create transaction: ' . $e->getMessage()]]);
            } else {
                return back()->with('error', 'Failed to create transaction: ' . $e->getMessage())->withInput();
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
        $bankTransaction = BankTransaction::find($id);
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.admin.bank_transaction.modal.view', compact('bankTransaction', 'id'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $tenant, $id) {
        $bankTransaction = BankTransaction::find($id);
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.admin.bank_transaction.modal.edit', compact('bankTransaction', 'id'));
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
            'trans_date'      => 'required',
            'bank_account_id' => 'required',
            'amount'          => 'required|numeric',
            'type'            => 'required|in:cash_to_bank,bank_to_cash,deposit,withdraw',
            'status'          => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('bank_transactions.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $bankAccount = BankAccount::find($request->bank_account_id);
        if (! $bankAccount) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Bank account not found')]);
            } else {
                return back()->with('error', _lang('Bank account not found'));
            }
        }

        if ($request->trans_date < $bankAccount->getRawOriginal('opening_date')) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Transaction date cannot be smaller than account opening date')]);
            } else {
                return back()->with('error', _lang('Transaction date cannot be smaller than account opening date'));
            }
        }

        if ($request->hasfile('attachment')) {
            $file       = $request->file('attachment');
            $attachment = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/media/", $attachment);
        }

        $bankTransaction                  = BankTransaction::find($id);
        
        // Store old values for audit
        $oldValues = $bankTransaction->getAttributes();
        
        $bankTransaction->trans_date      = $request->input('trans_date');
        $bankTransaction->bank_account_id = $request->input('bank_account_id');
        $bankTransaction->amount          = $request->input('amount');
        $bankTransaction->type            = $request->input('type');
        $bankTransaction->status          = $request->input('status');
        $bankTransaction->description     = $request->input('description');

        if (in_array($request->type, ['cash_to_bank', 'deposit'])) {
            $bankTransaction->dr_cr = 'cr';
        } else {
            $bankTransaction->dr_cr = 'dr';
        }

        $bankTransaction->cheque_number = $bankTransaction->type == 'withdraw' ? $request->cheque_number : null;
        if ($request->hasfile('attachment')) {
            $bankTransaction->attachment = $attachment;
        }
        $bankTransaction->save();

        // Log bank transaction update
        AuditService::logUpdated($bankTransaction, $oldValues, 'Bank transaction updated');

        if (! $request->ajax()) {
            return redirect()->route('bank_transactions.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $bankTransaction, 'table' => '#bank_transactions_table']);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($tenant, $id) {
        $bankTransaction = BankTransaction::find($id);
        
        // Log bank transaction deletion
        AuditService::logDeleted($bankTransaction, 'Bank transaction deleted');
        
        $bankTransaction->delete();
        return redirect()->route('bank_transactions.index')->with('success', _lang('Deleted Successfully'));
    }
}