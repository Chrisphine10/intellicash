<?php
namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanRepayment;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Notifications\LoanPaymentReceived;
use App\Services\AuditService;
use App\Utilities\LoanCalculator as Calculator;
use DataTables;
use DB;
use Exception;
use Illuminate\Http\Request;
use Validator;

class LoanPaymentController extends Controller {

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
        $user = auth()->user();
        
        // Only allow admin and staff users for admin loan payment management
        if (!in_array($user->user_type, ['admin', 'user'])) {
            abort(403, 'Unauthorized access to loan payment management');
        }
        
        // For staff users, check specific permissions
        if ($user->user_type === 'user' && !has_permission('loan_payment.view')) {
            abort(403, 'Insufficient permissions for loan payment management');
        }
        
        $assets = ['datatable'];
        return view('backend.admin.loan_payment.list', compact('assets'));
    }

    public function get_table_data() {
        $user = auth()->user();
        
        $loanpayments = LoanPayment::select('loan_payments.*')
            ->with('loan')
            ->orderBy("loan_payments.id", "desc");

        // Add data filtering based on user type
        if ($user->user_type === 'user') {
            // Staff users might have limited access - could be filtered by branch or other criteria
            // For now, show all payments but this can be customized based on business requirements
            // Example: $loanpayments->whereHas('loan', function($query) use ($user) {
            //     $query->where('branch_id', $user->branch_id);
            // });
        }
        // Admin users see all data (no additional filtering needed)

        return Datatables::eloquent($loanpayments)
            ->editColumn('repayment_amount', function ($loanpayment) {
                return decimalPlace($loanpayment->repayment_amount - $loanpayment->interest, currency($loanpayment->loan->currency->name));
            })
            ->addColumn('total_amount', function ($loanpayment) {
                return decimalPlace($loanpayment->total_amount, currency($loanpayment->loan->currency->name));
            })
            ->addColumn('action', function ($loanpayment) {
                return '<div class="dropdown text-center">'
                . '<button class="btn btn-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown">' . _lang('Action')
                . '&nbsp;</button>'
                . '<div class="dropdown-menu">'
                . '<a class="dropdown-item" href="' . route('loan_payments.show', $loanpayment['id']) . '" data-title="' . _lang('Update Account') . '"><i class="ti-eye"></i>  ' . _lang('View') . '</a>'
                . '<a class="dropdown-item" href="' . route('loan_payments.show', $loanpayment['id']) . '?print=general" target="_blank"><i class="fas fa-print"></i>  ' . _lang('Regular Print') . '</a>'
                . '<a class="dropdown-item" href="' . route('loan_payments.show', $loanpayment['id']) . '?print=pos" target="_blank"><i class="fas fa-print"></i>  ' . _lang('POS Receipt') . '</a>'
                . '<a class="dropdown-item" href="' . route('loans.show', $loanpayment['loan_id']) . '" data-title="' . _lang('Account Details') . '"><i class="ti-file"></i> ' . _lang('Loan Details') . '</a>'
                . '<form action="' . route('loan_payments.destroy', $loanpayment['id']) . '" method="post">'
                . csrf_field()
                . '<input name="_method" type="hidden" value="DELETE">'
                . '<button class="dropdown-item btn-remove" type="submit"><i class="ti-trash"></i> ' . _lang('Delete') . '</button>'
                    . '</form>'
                    . '</div>'
                    . '</div>';
            })
            ->setRowId(function ($loanpayment) {
                return "row_" . $loanpayment->id;
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        $user = auth()->user();
        
        // Check permissions for creating loan payments
        if ($user->user_type === 'user' && !has_permission('loan_payment.create')) {
            abort(403, 'Insufficient permissions to create loan payments');
        }
        
        $alert_col = 'col-lg-8 offset-lg-2';
        return view('backend.admin.loan_payment.create', compact('alert_col'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $user = auth()->user();
        
        // Check permissions for creating loan payments
        if ($user->user_type === 'user' && !has_permission('loan_payment.create')) {
            abort(403, 'Insufficient permissions to create loan payments');
        }
        
        $validator = Validator::make($request->all(), [
            'loan_id'          => 'required|exists:loans,id',
            'paid_at'          => 'required|date',
            'late_penalties'   => 'nullable|numeric|min:0',
            'principal_amount' => 'required|numeric|min:0.01',
            'interest'         => 'required|numeric|min:0',
            'due_amount_of'    => 'required|exists:loan_repayments,id',
        ], [
            'loan_id.required' => 'Loan ID is required',
            'loan_id.exists' => 'Invalid loan selected',
            'principal_amount.min' => 'Principal amount must be greater than 0',
            'late_penalties.min' => 'Late penalties cannot be negative',
            'due_amount_of.exists' => 'Invalid repayment schedule selected',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('loan_payments.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        DB::beginTransaction();
        
        // Use atomic validation with pessimistic locking to prevent race conditions
        $repayment = LoanRepayment::where('loan_id', $request->loan_id)
            ->where('status', 0)
            ->where('id', $request->due_amount_of)
            ->lockForUpdate()
            ->first();

        if (!$repayment) {
            DB::rollback();
            return back()->with('error', _lang('No pending repayment found for this loan or repayment schedule mismatch'));
        }

        $existing_amount = $repayment->principal_amount;

        $loan = Loan::find($request->loan_id);

        $amount = $request->principal_amount + $request->late_penalties + $repayment->interest;
        if ($request->account_id != 'cash') {

            $account = SavingsAccount::where('id', $request->account_id)
                ->where('member_id', $loan->borrower_id)
                ->first();

            if (! $account) {
                return back()->with('error', _lang('Invalid account !'));
            }

            //Check Available Balance
            if (get_account_balance($request->account_id, $loan->borrower_id) < $amount) {
                return back()->with('error', _lang('Insufficient balance !'));
            }
        }

        if ($request->account_id != 'cash') {
            //Create Debit Transactions
            $debit                     = new Transaction();
            $debit->trans_date         = now();
            $debit->member_id          = $loan->borrower_id;
            $debit->savings_account_id = $request->account_id;
            $debit->amount             = $amount;
            $debit->dr_cr              = 'dr';
            $debit->type               = 'Loan_Repayment';
            $debit->method             = 'Manual';
            $debit->status             = 2;
            $debit->note               = _lang('Loan Repayment');
            $debit->description        = _lang('Loan Repayment');
            $debit->created_user_id    = auth()->id();
            $debit->branch_id          = $loan->borrower->branch_id;
            $debit->loan_id            = $loan->id;

            $debit->save();

            // Log audit trail for loan payment transaction
            AuditService::logCreated($debit, 'Loan payment transaction created: ' . $debit->amount . ' for loan ID ' . $loan->id);
        }

        $loanpayment                   = new LoanPayment();
        $loanpayment->loan_id          = $request->loan_id;
        $loanpayment->paid_at          = $request->paid_at;
        $loanpayment->late_penalties   = $request->late_penalties ?? 0; //it's optionals
        $loanpayment->interest         = $repayment->interest;
        $loanpayment->repayment_amount = $request->principal_amount + $repayment->interest;
        $loanpayment->total_amount     = $loanpayment->repayment_amount + $request->late_penalties;
        $loanpayment->remarks          = $request->remarks;
        $loanpayment->repayment_id     = $repayment->id;
        $loanpayment->member_id        = $loan->borrower_id;
        $loanpayment->transaction_id   = $request->account_id != 'cash' ? $debit->id : null;

        $loanpayment->save();

        // Log audit trail for loan payment
        AuditService::logCreated($loanpayment, 'Loan payment created: ' . $loanpayment->total_amount . ' for loan ID ' . $loan->id);

        //Update Loan Balance using standardized calculation
        $loan->total_paid = $loan->calculateRemainingPrincipal();
        if ($loan->isFullyPaid()) {
            $loan->status = 2;
        }
        $loan->save();

        //Update Repayment Status
        $repayment->principal_amount = $request->principal_amount;
        $repayment->amount_to_pay    = $request->principal_amount + $repayment->interest;
        $repayment->balance = $loan->calculateRemainingBalance();
        $repayment->status  = 1;
        $repayment->save();

        //Delete All Upcomming Repayment schedule if payment is done
        if ($loan->total_paid >= $loan->applied_amount) {
            LoanRepayment::where('loan_id', $request->loan_id)->where('status', 0)->delete();
        } else {
            //Update Upcomming Repayment Schedule
            if ($request->principal_amount != $existing_amount) {
                $upCommingRepayments = LoanRepayment::where('loan_id', $request->loan_id)
                    ->where('status', 0)
                    ->orderBy('id', 'asc')
                    ->get();

                if ($upCommingRepayments->isEmpty()) {
                    return back()->with('error', _lang('You must pay the full repayment amount as this is your final scheduled payment.'));
                }

                // Create Loan Repayments
                $interest_type = $loan->loan_product->interest_type;
                $calculator    = new Calculator(
                    $loan->applied_amount - $loan->total_paid,
                    $upCommingRepayments[0]->repayment_date,
                    $loan->loan_product->interest_rate,
                    $upCommingRepayments->count(),
                    $loan->loan_product->term_period,
                    $loan->late_payment_penalties,
                    $loan->applied_amount
                );

                if ($interest_type == 'flat_rate') {
                    $repayments = $calculator->get_flat_rate();
                } else if ($interest_type == 'fixed_rate') {
                    $repayments = $calculator->get_fixed_rate();
                } else if ($interest_type == 'mortgage') {
                    $repayments = $calculator->get_mortgage();
                } else if ($interest_type == 'one_time') {
                    $repayments = $calculator->get_one_time();
                } else if ($interest_type == 'reducing_amount') {
                    $repayments = $calculator->get_reducing_amount();
                } else if ($interest_type == 'compound') {
                    $repayments = $calculator->get_compound();
                }

                $index = 0;
                foreach ($repayments as $newRepayment) {
                    $upCommingRepayment                   = $upCommingRepayments[$index];
                    $upCommingRepayment->amount_to_pay    = $newRepayment['amount_to_pay'];
                    $upCommingRepayment->penalty          = $newRepayment['penalty'];
                    $upCommingRepayment->principal_amount = $newRepayment['principal_amount'];
                    $upCommingRepayment->interest         = $newRepayment['interest'];
                    $upCommingRepayment->balance          = $newRepayment['balance'];
                    $upCommingRepayment->save();
                    $index++;
                }
            }
        }

        DB::commit();

        try {
            $loanpayment->member->notify(new LoanPaymentReceived($loanpayment));
        } catch (Exception $e) {}

        return redirect()->route('loan_payments.index')->with('success', _lang('Loan Payment Made Sucessfully'));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $tenant, $id) {
        $loanpayment = LoanPayment::find($id);
        if (! $request->ajax()) {
            return view('backend.admin.loan_payment.view', compact('loanpayment', 'id'));
        } else {
            return view('backend.admin.loan_payment.modal.view', compact('loanpayment', 'id'));
        }
    }

    public function get_repayment_by_loan_id($tenant, $loan_id) {
        $repayments = LoanRepayment::where('loan_id', $loan_id)
            ->where('status', 0)
            ->orderBy('id', 'asc')
            ->limit(1)
            ->get();

        $accounts = [];
        if ($repayments->count() > 0) {
            $accounts = SavingsAccount::with('savings_type.currency')
                ->where('member_id', $repayments[0]->loan->borrower_id)
                ->get();
        }

        return response()->json([
            'repayments' => $repayments, 
            'accounts' => $accounts
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($tenant, $id) {
        $user = auth()->user();
        
        // Check permissions for deleting loan payments
        if ($user->user_type === 'user' && !has_permission('loan_payment.delete')) {
            abort(403, 'Insufficient permissions to delete loan payments');
        }
        
        DB::beginTransaction();

        $loanpayment = LoanPayment::find($id);

        $transaction = Transaction::find($loanpayment->transaction_id);
        if ($transaction) {
            $transaction->delete();
        }

        //Update Balance
        $repayment         = LoanRepayment::find($loanpayment->repayment_id);
        $repayment->status = 0;
        $repayment->save();

        $loan             = Loan::find($loanpayment->loan_id);
        $loan->total_paid = $loan->total_paid - $repayment->principal_amount;
        if ($loan->total_paid < $loan->applied_amount) {
            $loan->status = 1;
        }
        $loan->save();

        $loanpayment->delete();

        DB::commit();

        return back()->with('success', _lang('Deleted Sucessfully'));
    }
}
