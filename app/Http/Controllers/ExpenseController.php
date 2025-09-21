<?php
namespace App\Http\Controllers;

use App\Models\Expense;
use DataTables;
use Illuminate\Http\Request;
use Validator;

class ExpenseController extends Controller {

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
        return view('backend.admin.expense.list', compact('assets'));
    }

    public function get_table_data() {

        $currency = currency(get_base_currency());

        $expenses = Expense::select('expenses.*')
            ->with(['expense_category', 'bank_account.currency'])
            ->orderBy("expenses.id", "desc");

        return Datatables::eloquent($expenses)
            ->editColumn('amount', function ($expense) use ($currency) {
                return decimalPlace($expense->amount, $currency);
            })
            ->addColumn('bank_account', function ($expense) {
                return $expense->bank_account ? $expense->bank_account->bank_name . ' - ' . $expense->bank_account->account_name : 'N/A';
            })
            ->addColumn('action', function ($expense) {
                return '<div class="dropdown text-center">'
                . '<button class="btn btn-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown">' . _lang('Action')
                . '&nbsp;</button>'
                . '<div class="dropdown-menu">'
                . '<a class="dropdown-item ajax-modal" href="' . route('expenses.edit', $expense['id']) . '" data-title="' . _lang('Expense Details') . '"><i class="ti-pencil-alt"></i> ' . _lang('Edit') . '</a>'
                . '<a class="dropdown-item ajax-modal" href="' . route('expenses.show', $expense['id']) . '" data-title="' . _lang('Update Expense') . '"><i class="ti-eye"></i>  ' . _lang('View') . '</a>'
                . '<form action="' . route('expenses.destroy', $expense['id']) . '" method="post">'
                . csrf_field()
                . '<input name="_method" type="hidden" value="DELETE">'
                . '<button class="dropdown-item btn-remove" type="submit"><i class="ti-trash"></i> ' . _lang('Delete') . '</button>'
                    . '</form>'
                    . '</div>'
                    . '</div>';
            })
            ->setRowId(function ($expense) {
                return "row_" . $expense->id;
            })
            ->rawColumns(['amount', 'bank_account', 'action'])
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
            return view('backend.admin.expense.modal.create');
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
            'expense_date'        => 'required',
            'expense_category_id' => 'required',
            'bank_account_id'     => 'required',
            'amount'              => 'required|numeric',
            'attachment'          => 'nullable|mimes:jpeg,JPEG,png,PNG,jpg,doc,pdf,docx,zip',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('expenses.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $attachment = '';
        if ($request->hasfile('attachment')) {
            $fileUploadService = new \App\Services\MilitaryFileUploadService();
            $uploadResult = $fileUploadService->uploadFile($request->file('attachment'), 'expenses');
            
            if ($uploadResult['success']) {
                $attachment = $uploadResult['filename'];
            } else {
                return back()
                    ->with('error', $uploadResult['error'])
                    ->withInput();
            }
        }

        // Check if bank account has sufficient balance
        $bankAccount = \App\Models\BankAccount::find($request->input('bank_account_id'));
        if (!$bankAccount || !$bankAccount->hasSufficientBalance($request->input('amount'))) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => [_lang('Insufficient balance in selected bank account')]]);
            } else {
                return redirect()->route('expenses.create')
                    ->with('error', _lang('Insufficient balance in selected bank account'))
                    ->withInput();
            }
        }

        $expense                      = new Expense();
        $expense->expense_date        = $request->input('expense_date');
        $expense->expense_category_id = $request->input('expense_category_id');
        $expense->bank_account_id     = $request->input('bank_account_id');
        $expense->amount              = $request->input('amount');
        $expense->reference           = $request->input('reference');
        $expense->note                = $request->input('note');
        $expense->attachment          = $attachment;
        $expense->created_user_id     = auth()->id();
        $expense->branch_id           = auth()->user()->branch_id;

        $expense->save();

        // Create bank transaction for the expense
        $bankTransaction = new \App\Models\BankTransaction();
        $bankTransaction->trans_date = $request->input('expense_date');
        $bankTransaction->bank_account_id = $request->input('bank_account_id');
        $bankTransaction->amount = $request->input('amount');
        $bankTransaction->type = 'expense';
        $bankTransaction->dr_cr = 'dr'; // Debit for expense
        $bankTransaction->description = 'Expense: ' . ($expense->expense_category->name ?? 'Unknown Category');
        $bankTransaction->created_user_id = auth()->id();
        $bankTransaction->status = 1;
        $bankTransaction->save();

        // Update bank account balance
        $bankAccount->current_balance -= $request->input('amount');
        $bankAccount->last_balance_update = now();
        $bankAccount->save();

        if (! $request->ajax()) {
            return redirect()->route('expenses.create')->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $expense, 'table' => '#expenses_table']);
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $tenant, $id) {
        $expense = Expense::find($id);
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.admin.expense.modal.view', compact('expense', 'id'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $tenant, $id) {
        $expense = Expense::find($id);
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.admin.expense.modal.edit', compact('expense', 'id'));
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
            'expense_date'        => 'required',
            'expense_category_id' => 'required',
            'bank_account_id'     => 'required',
            'amount'              => 'required|numeric',
            'attachment'          => 'nullable|mimes:jpeg,JPEG,png,PNG,jpg,doc,pdf,docx,zip',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('expenses.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        if ($request->hasfile('attachment')) {
            $file       = $request->file('attachment');
            $attachment = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/media/", $attachment);
        }

        $expense = Expense::find($id);
        
        // Get old values for balance adjustment
        $oldAmount = $expense->amount;
        $oldBankAccountId = $expense->bank_account_id;
        
        // Check if bank account has sufficient balance (considering the old amount being reversed)
        $bankAccount = \App\Models\BankAccount::find($request->input('bank_account_id'));
        $newAmount = $request->input('amount');
        
        if (!$bankAccount) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => [_lang('Invalid bank account selected')]]);
            } else {
                return redirect()->route('expenses.edit', $id)
                    ->with('error', _lang('Invalid bank account selected'))
                    ->withInput();
            }
        }

        // Calculate balance difference
        $balanceDifference = $newAmount - $oldAmount;
        
        // If the new amount is greater, check if there's sufficient balance
        if ($balanceDifference > 0 && !$bankAccount->hasSufficientBalance($balanceDifference)) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => [_lang('Insufficient balance in selected bank account')]]);
            } else {
                return redirect()->route('expenses.edit', $id)
                    ->with('error', _lang('Insufficient balance in selected bank account'))
                    ->withInput();
            }
        }

        $expense->expense_date        = $request->input('expense_date');
        $expense->expense_category_id = $request->input('expense_category_id');
        $expense->bank_account_id     = $request->input('bank_account_id');
        $expense->amount              = $request->input('amount');
        $expense->reference           = $request->input('reference');
        $expense->note                = $request->input('note');
        if ($request->hasfile('attachment')) {
            $expense->attachment = $attachment;
        }
        $expense->updated_user_id = auth()->id();
        $expense->branch_id       = auth()->user()->branch_id;

        $expense->save();

        // Handle bank account balance adjustments
        if ($oldBankAccountId != $request->input('bank_account_id')) {
            // If bank account changed, reverse old transaction and create new one
            $oldBankAccount = \App\Models\BankAccount::find($oldBankAccountId);
            if ($oldBankAccount) {
                $oldBankAccount->current_balance += $oldAmount;
                $oldBankAccount->last_balance_update = now();
                $oldBankAccount->save();
            }
            
            $bankAccount->current_balance -= $newAmount;
        } else {
            // Same bank account, just adjust by the difference
            $bankAccount->current_balance -= $balanceDifference;
        }
        
        $bankAccount->last_balance_update = now();
        $bankAccount->save();

        if (! $request->ajax()) {
            return redirect()->route('expenses.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $expense, 'table' => '#expenses_table']);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($tenant, $id) {
        $expense = Expense::find($id);
        
        // Reverse bank account balance
        if ($expense->bank_account_id) {
            $bankAccount = \App\Models\BankAccount::find($expense->bank_account_id);
            if ($bankAccount) {
                $bankAccount->current_balance += $expense->amount;
                $bankAccount->last_balance_update = now();
                $bankAccount->save();
            }
        }
        
        $expense->delete();
        return redirect()->route('expenses.index')->with('success', _lang('Deleted Successfully'));
    }
}