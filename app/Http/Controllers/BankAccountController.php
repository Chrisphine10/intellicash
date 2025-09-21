<?php
namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

class BankAccountController extends Controller {

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
        $bankAccounts = BankAccount::with('currency')->active()->get()->sortByDesc("id");
        return view('backend.admin.bank_account.list', compact('bankAccounts', 'assets'));
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
            return view('backend.admin.bank_account.modal.create');
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
            'opening_date'           => 'required|date',
            'bank_name'              => 'required|max:191',
            'currency_id'            => 'required|exists:currency,id',
            'account_name'           => 'required|max:100',
            'account_number'         => 'required|max:50|unique:bank_accounts,account_number',
            'opening_balance'        => 'required|numeric|min:0',
            'minimum_balance'        => 'nullable|numeric|min:0',
            'maximum_balance'        => 'nullable|numeric|min:0|gt:minimum_balance',
            'allow_negative_balance' => 'boolean',
            'is_active'              => 'boolean',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('bank_accounts.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        // Validate currency exists and is active
        $currency = \App\Models\Currency::find($request->currency_id);
        if (!$currency) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => ['Invalid currency selected']]);
            } else {
                return redirect()->route('bank_accounts.create')
                    ->withErrors(['currency_id' => 'Invalid currency selected'])
                    ->withInput();
            }
        }

        DB::beginTransaction();

        try {
            $bankAccount = new BankAccount();
            $bankAccount->opening_date = $request->input('opening_date');
            $bankAccount->bank_name = $request->input('bank_name');
            $bankAccount->currency_id = $request->input('currency_id');
            $bankAccount->account_name = $request->input('account_name');
            $bankAccount->account_number = $request->input('account_number');
            $bankAccount->opening_balance = $request->input('opening_balance');
            $bankAccount->current_balance = $request->input('opening_balance'); // Initialize current balance
            $bankAccount->minimum_balance = $request->input('minimum_balance', 0);
            $bankAccount->maximum_balance = $request->input('maximum_balance');
            $bankAccount->allow_negative_balance = $request->boolean('allow_negative_balance', true);
            $bankAccount->is_active = $request->boolean('is_active', true);
            $bankAccount->description = $request->input('description');
            $bankAccount->last_balance_update = now();

            $bankAccount->save();

            // Log bank account creation
            AuditService::logCreated($bankAccount, 'Bank account created with opening balance: ' . $bankAccount->opening_balance);

            // Create opening balance transaction if balance > 0
            if ($bankAccount->opening_balance > 0) {
                $bankTransaction = new \App\Models\BankTransaction();
                $bankTransaction->trans_date = $request->input('opening_date');
                $bankTransaction->bank_account_id = $bankAccount->id;
                $bankTransaction->amount = $request->input('opening_balance');
                $bankTransaction->type = \App\Models\BankTransaction::TYPE_DEPOSIT;
                $bankTransaction->dr_cr = 'cr';
                $bankTransaction->status = \App\Models\BankTransaction::STATUS_APPROVED;
                $bankTransaction->description = 'Opening Balance';
                $bankTransaction->created_user_id = auth()->id();

                $bankTransaction->save();

                // Log opening balance transaction
                AuditService::logCreated($bankTransaction, 'Opening balance transaction created');
            }

            DB::commit();

            if (!$request->ajax()) {
                return redirect()->route('bank_accounts.create')->with('success', _lang('Saved Successfully'));
            } else {
                return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $bankAccount, 'table' => '#bank_accounts_table']);
            }

        } catch (\Exception $e) {
            DB::rollback();
            
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => ['Failed to create bank account: ' . $e->getMessage()]]);
            } else {
                return redirect()->route('bank_accounts.create')
                    ->withErrors(['general' => 'Failed to create bank account: ' . $e->getMessage()])
                    ->withInput();
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
        $bankAccount = BankAccount::with('currency')->find($id);
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.admin.bank_account.modal.view', compact('bankAccount', 'id'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $tenant, $id) {
        $bankAccount = BankAccount::with('currency')->find($id);
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.admin.bank_account.modal.edit', compact('bankAccount', 'id'));
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
            'bank_name'              => 'required|max:191',
            'account_name'           => 'required|max:100',
            'account_number'         => 'required|max:50|unique:bank_accounts,account_number,' . $id,
            'currency_id'            => 'required|exists:currency,id',
            'minimum_balance'        => 'nullable|numeric|min:0',
            'maximum_balance'        => 'nullable|numeric|min:0|gt:minimum_balance',
            'allow_negative_balance' => 'boolean',
            'is_active'              => 'boolean',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('bank_accounts.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $bankAccount = BankAccount::find($id);
        if (!$bankAccount) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => ['Bank account not found']]);
            } else {
                return redirect()->route('bank_accounts.index')
                    ->withErrors(['general' => 'Bank account not found']);
            }
        }
        
        // Store old values for audit
        $oldValues = $bankAccount->getAttributes();
        
        // Check if currency is being changed
        $oldCurrencyId = $bankAccount->currency_id;
        $newCurrencyId = $request->input('currency_id');
        
        // Prevent currency change if there are existing transactions
        if ($oldCurrencyId != $newCurrencyId) {
            $hasTransactions = $bankAccount->bankTransactions()->exists() || 
                              $bankAccount->transactions()->exists() || 
                              $bankAccount->vslaTransactions()->exists();
            
            if ($hasTransactions) {
                if ($request->ajax()) {
                    return response()->json(['result' => 'error', 'message' => ['Cannot change currency for accounts with existing transactions. Please create a new account instead.']]);
                } else {
                    return redirect()->route('bank_accounts.edit', $id)
                        ->withErrors(['currency_id' => 'Cannot change currency for accounts with existing transactions. Please create a new account instead.'])
                        ->withInput();
                }
            }
        }

        // Validate new currency exists
        $currency = \App\Models\Currency::find($newCurrencyId);
        if (!$currency) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => ['Invalid currency selected']]);
            } else {
                return redirect()->route('bank_accounts.edit', $id)
                    ->withErrors(['currency_id' => 'Invalid currency selected'])
                    ->withInput();
            }
        }

        DB::beginTransaction();

        try {
            $bankAccount->bank_name = $request->input('bank_name');
            $bankAccount->account_name = $request->input('account_name');
            $bankAccount->account_number = $request->input('account_number');
            $bankAccount->currency_id = $request->input('currency_id');
            $bankAccount->minimum_balance = $request->input('minimum_balance', 0);
            $bankAccount->maximum_balance = $request->input('maximum_balance');
            $bankAccount->allow_negative_balance = $request->boolean('allow_negative_balance', true);
            $bankAccount->is_active = $request->boolean('is_active', true);
            $bankAccount->description = $request->input('description');

            // Validate balance constraints
            if (!$bankAccount->allow_negative_balance && $bankAccount->current_balance < $bankAccount->minimum_balance) {
                throw new \Exception('Current balance cannot be below minimum balance when negative balances are not allowed');
            }

            if ($bankAccount->maximum_balance && $bankAccount->current_balance > $bankAccount->maximum_balance) {
                throw new \Exception('Current balance exceeds maximum balance limit');
            }

            $bankAccount->save();

            // Log bank account update
            AuditService::logUpdated($bankAccount, $oldValues, 'Bank account updated');

            DB::commit();

            if (!$request->ajax()) {
                return redirect()->route('bank_accounts.index')->with('success', _lang('Updated Successfully'));
            } else {
                return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $bankAccount, 'table' => '#bank_accounts_table']);
            }

        } catch (\Exception $e) {
            DB::rollback();
            
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => ['Failed to update bank account: ' . $e->getMessage()]]);
            } else {
                return redirect()->route('bank_accounts.edit', $id)
                    ->withErrors(['general' => 'Failed to update bank account: ' . $e->getMessage()])
                    ->withInput();
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($tenant, $id) {
        $bankAccount = BankAccount::find($id);
        
        // Log bank account deletion
        AuditService::logDeleted($bankAccount, 'Bank account deleted');
        
        $bankAccount->delete();
        return redirect()->route('bank_accounts.index')->with('success', _lang('Deleted Successfully'));
    }
}