<?php
namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\CustomField;
use App\Services\BankingService;
use App\Models\Guarantor;
use App\Models\GuarantorRequest;
use App\Models\Loan;
use App\Models\LoanCollateral;
use App\Models\LoanPayment;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Notifications\ApprovedLoanRequest;
use App\Notifications\RejectLoanRequest;
use App\Utilities\LoanCalculator as Calculator;
use Carbon\Carbon;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LoanController extends Controller {

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
    public function index(Request $request, $tenant, $status = '') {
        // The tenant middleware already handles tenant identification and authorization
        // Additional check for authentication
        if (!auth()->check()) {
            return redirect()->route('login');
        }
        
        // Check if user has permission to view loans
        // Only allow admin and staff users for admin loan management
        if (!in_array(auth()->user()->user_type, ['admin', 'user'])) {
            abort(403, 'Unauthorized access to loan management');
        }
        
        // For staff users, check specific permissions
        if (auth()->user()->user_type === 'user' && !has_permission('loan.view')) {
            abort(403, 'Insufficient permissions for loan management');
        }
        
        if ($status == 'pending') {
            $status = 0;
        } else if ($status == 'active') {
            $status = 1;
        }
        $assets = ['datatable'];
        return view('backend.admin.loan.list', compact('status', 'assets'));
    }

    public function get_table_data(Request $request) {
        $loans = Loan::select('loans.*')
            ->with('borrower')
            ->with('currency')
            ->with('loan_product')
            ->orderBy("loans.id", "desc");

        return Datatables::eloquent($loans)
            ->filter(function ($query) use ($request) {
                if ($request->has('status')) {
                    $query->where('status', $request->status);
                }
            }, true)
            ->editColumn('borrower.first_name', function ($loan) {
                return $loan->borrower->first_name . ' ' . $loan->borrower->last_name;
            })
            ->editColumn('applied_amount', function ($loan) {
                return decimalPlace($loan->applied_amount, currency($loan->currency->name));
            })
            ->editColumn('status', function ($loan) {
                if ($loan->status == 0) {
                    return show_status(_lang('Pending'), 'warning');
                } else if ($loan->status == 1) {
                    return show_status(_lang('Approved'), 'success');
                } elseif ($loan->status == 2) {
                    return show_status(_lang('Completed'), 'info');
                } elseif ($loan->status == 3) {
                    return show_status(_lang('Cancelled'), 'danger');
                }
            })
            ->filterColumn('borrower.first_name', function ($query, $keyword) {
                $query->whereHas('borrower', function ($query) use ($keyword) {
                    return $query->where("first_name", "like", "{$keyword}%")
                        ->orWhere("last_name", "like", "{$keyword}%");
                });
            }, true)
            ->addColumn('action', function ($loan) {
                return '<form action="' . route('loans.destroy', $loan['id']) . '" class="text-center" method="post">'
                . '<a href="' . route('loans.show', $loan['id']) . '" class="btn btn-primary btn-xs"><i class="ti-eye"></i> ' . _lang('View') . '</a>&nbsp;'
                . '<a href="' . route('loans.edit', $loan['id']) . '" class="btn btn-warning btn-xs"><i class="ti-pencil-alt"></i> ' . _lang('Edit') . '</a>&nbsp;'
                . csrf_field()
                . '<input name="_method" type="hidden" value="DELETE">'
                . '<button class="btn btn-danger btn-xs btn-remove" type="submit"><i class="ti-trash"></i> ' . _lang('Delete') . '</button>'
                    . '</form>';
            })
            ->setRowId(function ($loan) {
                return "row_" . $loan->id;
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
        $alert_col    = "col-lg-8 offset-lg-2";
        $customFields = CustomField::where('table', 'loans')
            ->where('status', 1)
            ->orderBy("id", "asc")
            ->get();
        if (! $request->ajax()) {
            return view('backend.admin.loan.create', compact('alert_col', 'customFields'));
        } else {
            return view('backend.admin.loan.modal.create', compact('alert_col', 'customFields'));
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $loanProduct = LoanProduct::find($request->loan_product_id);

        $min_amount = $loanProduct->minimum_amount;
        $max_amount = $loanProduct->maximum_amount;

        $validationRules = [
            'loan_id'                => 'required|unique:loans',
            'loan_product_id'        => 'required',
            'borrower_id'            => 'required',
            'currency_id'            => 'required',
            'first_payment_date'     => 'required',
            'release_date'           => 'required',
            'applied_amount'         => "required|numeric|min:$min_amount|max:$max_amount",
            'late_payment_penalties' => 'required|numeric',
            'debit_account_id'       => 'required',
            'attachment'             => 'nullable|mimes:jpeg,JPEG,png,PNG,jpg,doc,pdf,docx,zip|max:8192', //8MB = 8192KB
        ];

        $validationMessages = [];

        // Custom field validation
        $customFields = CustomField::where('table', 'loans')
            ->orderBy("id", "desc")
            ->get();
        $customValidation = generate_custom_field_validation($customFields);

        $validationRules    = array_merge($validationRules, $customValidation['rules']);
        $validationMessages = array_merge($validationMessages, $customValidation['messages']);

        $validator = Validator::make($request->all(), $validationRules, $validationMessages);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('loans.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        //Check Debit account is valid account
        $account = SavingsAccount::where('id', $request->debit_account_id)
            ->where('member_id', $request->borrower_id)
            ->first();

        if (! $account) {
            return back()->with('error', _lang('Invalid account'));
        }

        $attachment = "";
        if ($request->hasfile('attachment')) {
            $file = $request->file('attachment');
            
            // Validate file type and size
            $allowedMimes = ['jpeg', 'jpg', 'png', 'pdf', 'doc', 'docx'];
            $maxSize = 8192; // 8MB in KB
            
            if (!in_array($file->getClientOriginalExtension(), $allowedMimes)) {
                return back()->with('error', _lang('Invalid file type. Only JPEG, PNG, PDF, DOC, DOCX files are allowed.'));
            }
            
            if ($file->getSize() > $maxSize * 1024) {
                return back()->with('error', _lang('File size too large. Maximum size is 8MB.'));
            }
            
            // Generate secure filename
            $extension = $file->getClientOriginalExtension();
            $attachment = time() . '_' . uniqid() . '.' . $extension;
            
            // Move file to secure location
            $file->move(public_path() . "/uploads/media/", $attachment);
        }

        DB::beginTransaction();

        // Store custom field data
        $customFieldsData = store_custom_field_data($customFields);

        $loan                         = new Loan();
        $loan->loan_id                = $loanProduct->loan_id_prefix . $loanProduct->starting_loan_id;
        $loan->loan_product_id        = $request->input('loan_product_id');
        $loan->borrower_id            = $request->input('borrower_id');
        $loan->currency_id            = $request->input('currency_id');
        $loan->first_payment_date     = $request->input('first_payment_date');
        $loan->release_date           = $request->input('release_date');
        $loan->applied_amount         = $request->input('applied_amount');
        $loan->late_payment_penalties = $request->input('late_payment_penalties');
        $loan->attachment             = $attachment;
        $loan->description            = $request->input('description');
        $loan->remarks                = $request->input('remarks');
        $loan->created_user_id        = Auth::id();
        $loan->branch_id              = auth()->user()->branch_id;
        $loan->custom_fields          = json_encode($customFieldsData);
        $loan->debit_account_id       = $request->debit_account_id;

        $loan->save();

        // Create Loan Repayments
        $calculator = new Calculator(
            $loan->applied_amount,
            $loan->first_payment_date,
            $loan->loan_product->interest_rate,
            $loan->loan_product->term,
            $loan->loan_product->term_period,
            $loan->late_payment_penalties
        );

        if ($loan->loan_product->interest_type == 'flat_rate') {
            $repayments = $calculator->get_flat_rate();
        } else if ($loan->loan_product->interest_type == 'fixed_rate') {
            $repayments = $calculator->get_fixed_rate();
        } else if ($loan->loan_product->interest_type == 'mortgage') {
            $repayments = $calculator->get_mortgage();
        } else if ($loan->loan_product->interest_type == 'one_time') {
            $repayments = $calculator->get_one_time();
        } else if ($loan->loan_product->interest_type == 'reducing_amount') {
            $repayments = $calculator->get_reducing_amount();
        } else if ($loan->loan_product->interest_type == 'compound') {
            $repayments = $calculator->get_compound();
        }

        $loan->total_payable = $calculator->payable_amount;
        $loan->save();

        //Check Account has enough balance for deducting fee
        $convertedAmount = convert_currency($loan->currency->name, $account->savings_type->currency->name, $loan->applied_amount);

        $charge = 0;
        $charge += $loanProduct->loan_application_fee_type == 1 ? ($loanProduct->loan_application_fee / 100) * $convertedAmount : $loanProduct->loan_application_fee;
        $charge += $loanProduct->loan_insurance_fee_type == 1 ? ($loanProduct->loan_insurance_fee / 100) * $convertedAmount : $loanProduct->loan_insurance_fee;

        if (get_account_balance($account->id, $loan->borrower_id) < $charge) {
            return back()->with('error', _lang('Insufficient balance for deducting loan application and insurance fee !'));
        }

        //Deduct Loan Processing Fee
        process_loan_fee('loan_application_fee', $loan->borrower_id, $request->debit_account_id, $convertedAmount, $loanProduct->loan_application_fee, $loanProduct->loan_application_fee_type, $loan->id);

        //Increment Loan ID
        if ($loanProduct->starting_loan_id != null) {
            $loanProduct->increment('starting_loan_id');
        }

        DB::commit();

        if (! $request->ajax()) {
            return redirect()->route('loans.show', $loan->id)->with('success', _lang('New Loan added successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('New Loan added successfully'), 'data' => $loan, 'table' => '#loans_table']);
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $tenant, $id) {
        $assets = ['datatable'];
        $loan            = Loan::find($id);
        
        if (!$loan) {
            abort(404, 'Loan not found');
        }
        
        // Check if user has permission to view this loan
        // Only allow admin and staff users for admin loan management
        if (!in_array(auth()->user()->user_type, ['admin', 'user'])) {
            abort(403, 'Unauthorized access to this loan');
        }
        
        // For staff users, check specific permissions
        if (auth()->user()->user_type === 'user' && !has_permission('loan.view')) {
            abort(403, 'Insufficient permissions to view this loan');
        }
        
        $loancollaterals = LoanCollateral::where('loan_id', $loan->id)
            ->orderBy("id", "desc")
            ->get();
        $customFields = CustomField::where('table', 'loans')
            ->where('status', 1)
            ->orderBy("id", "asc")
            ->get();

        $repayments = LoanRepayment::where('loan_id', $loan->id)->orderBy('id', 'asc')->get();

        $guarantors = Guarantor::where('loan_id', $loan->id)->get();
        $guarantorRequests = GuarantorRequest::where('loan_id', $loan->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $payments = LoanPayment::where('loan_id', $loan->id)->orderBy('id', 'desc')->get();

        return view('backend.admin.loan.view', compact('loan', 'loancollaterals', 'repayments', 'payments', 'guarantors', 'guarantorRequests', 'customFields', 'assets'));
    }

    /**
     * Approve Loan
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function approve(Request $request, $tenant, $id) {
        \Log::info('Loan approval method called', ['method' => $request->method(), 'id' => $id]);
        
        if ($request->isMethod('get')) {
            $alert_col = 'col-lg-6 offset-lg-3';
            $loan      = Loan::find($id);

            // Get bank accounts for loan disbursement (not member savings accounts)
            $bankAccounts = BankAccount::with('currency')
                ->active()
                ->byCurrency($loan->currency_id)
                ->get();

            if ($loan->status == 1) {
                abort(403);
            }

            return view('backend.admin.loan.approve', compact('loan', 'bankAccounts', 'alert_col'));
        }

        \Log::info('Processing loan approval POST', ['request_data' => $request->all()]);
        
        // Validate required fields (loan_id, release_date, and first_payment_date are now read-only)
        $validator = Validator::make($request->all(), [
            'bank_account_id' => 'required',
        ], [
            'bank_account_id.required' => 'Bank Account is required for loan disbursement',
        ]);

        if ($validator->fails()) {
            \Log::error('Loan approval validation failed', ['errors' => $validator->errors()]);
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $loan = Loan::find($id);

            if (!$loan) {
                return back()->with('error', _lang('Loan not found'));
            }

            if ($loan->status == 1) {
                return back()->with('error', _lang('Loan has already been approved'));
            }

            // Loan information is already set during application, no need to update
            // The loan_id, release_date, and first_payment_date are read-only during approval

        // Validate bank account for loan disbursement
        $bankAccount = BankAccount::find($request->bank_account_id);
        
        if (!$bankAccount) {
            return back()->with('error', _lang('Invalid bank account selected'));
        }

        // Check if bank account has sufficient balance
        if (!$bankAccount->hasSufficientBalance($loan->applied_amount)) {
            return back()->with('error', _lang('Insufficient balance in selected bank account. Available: ') . $bankAccount->formatted_balance);
        }

        // Get member's savings account for processing fees (if needed)
        $memberAccount = SavingsAccount::where('id', $loan->debit_account_id)
            ->where('member_id', $loan->borrower_id)
            ->first();

        if (!$memberAccount) {
            $memberAccount = SavingsAccount::where('member_id', $loan->borrower_id)->first();
        }

        $loanProduct = $loan->loan_product;

        // Process loan fees from member account (if available)
        if ($memberAccount) {
            $convertedAmount = convert_currency($loan->currency->name, $memberAccount->savings_type->currency->name, $loan->applied_amount);

            $charge = 0;
            $charge += $loanProduct->loan_application_fee_type == 1 ? ($loanProduct->loan_application_fee / 100) * $convertedAmount : $loanProduct->loan_application_fee;
            $charge += $loanProduct->loan_insurance_fee_type == 1 ? ($loanProduct->loan_insurance_fee / 100) * $convertedAmount : $loanProduct->loan_insurance_fee;

            if (get_account_balance($memberAccount->id, $loan->borrower_id) < $charge) {
                return back()->with('error', _lang('Insufficient balance for deducting loan application and insurance fee !'));
            }

            //Deduct Loan Processing Fee
            process_loan_fee('loan_processing_fee', $loan->borrower_id, $memberAccount->id, $convertedAmount, $loanProduct->loan_processing_fee, $loanProduct->loan_processing_fee_type, $loan->id);
        }

        $loan->status           = 1;
        $loan->approved_date    = date('Y-m-d');
        $loan->approved_user_id = Auth::id();
        $loan->disburse_method  = 'bank_account';
        $loan->save();

        // Create Loan Repayments
        $interest_type = $loan->loan_product->interest_type;
        $calculator = new Calculator(
            $loan->applied_amount,
            $loan->getRawOriginal('first_payment_date'),
            $loan->loan_product->interest_rate,
            $loan->loan_product->term,
            $loan->loan_product->term_period,
            $loan->late_payment_penalties
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

        $loan->total_payable = $calculator->payable_amount;
        $loan->save();

        foreach ($repayments as $repayment) {
            $loan_repayment                   = new LoanRepayment();
            $loan_repayment->loan_id          = $loan->id;
            $loan_repayment->repayment_date   = $repayment['date'];
            $loan_repayment->amount_to_pay    = $repayment['amount_to_pay'];
            $loan_repayment->penalty          = $repayment['penalty'];
            $loan_repayment->principal_amount = $repayment['principal_amount'];
            $loan_repayment->interest         = $repayment['interest'];
            $loan_repayment->balance          = $repayment['balance'];
            $loan_repayment->save();
        }

        // Process loan disbursement using BankingService
        $bankingService = new BankingService();
        $disbursementSuccess = $bankingService->processLoanDisbursement(
            $bankAccount->id,
            $loan->applied_amount,
            $loan->borrower_id,
            $loan->id,
            'Loan disbursement to ' . $loan->borrower->first_name . ' ' . $loan->borrower->last_name
        );

        if (!$disbursementSuccess) {
            DB::rollback();
            return back()->with('error', _lang('Failed to process loan disbursement'));
        }

        // Create member transaction record (if member has savings account)
        if ($memberAccount) {
            $transaction = new Transaction();
            $transaction->trans_date = now();
            $transaction->member_id = $loan->borrower_id;
            $transaction->savings_account_id = $memberAccount->id;
            $transaction->amount = $loan->applied_amount;
            $transaction->dr_cr = 'cr'; // Credit to member account
            $transaction->type = 'Loan';
            $transaction->method = 'Manual';
            $transaction->status = 2;
            $transaction->description = 'Loan approved and disbursed from bank account';
            $transaction->created_user_id = auth()->id();
            $transaction->loan_id = $loan->id;
            $transaction->save();

            // Process bank account transaction for member account
            $bankingService->processMemberTransaction($transaction);
        }

            DB::commit();

            try {
                $loan->borrower->notify(new ApprovedLoanRequest($loan));
            } catch (\Exception $e) {
                \Log::error('Failed to send loan approval notification', ['error' => $e->getMessage()]);
            }

            return redirect()->route('loans.show', $loan->id)->with('success', _lang('Loan Request Approved'));

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Loan approval failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->with('error', _lang('An error occurred while approving the loan. Please try again.'));
        }

    }

    /**
     * Reject Loan
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function reject(Request $request, $tenant, $id) {
        try {
            $loan = Loan::find($id);
            
            if (!$loan) {
                return back()->with('error', _lang('Loan not found'));
            }
            
            /** If not pending */
            if ($loan->status != 0) {
                return back()->with('error', _lang('Only pending loans can be rejected'));
            }
            
            $loan->status = 3; //Cancelled
            $loan->save();

            try {
                $loan->borrower->notify(new RejectLoanRequest($loan));
            } catch (\Exception $e) {
                \Log::error('Failed to send loan rejection notification', ['error' => $e->getMessage()]);
            }

            return back()->with('success', _lang('Loan Request Rejected'));
            
        } catch (\Exception $e) {
            \Log::error('Loan rejection failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->with('error', _lang('An error occurred while rejecting the loan. Please try again.'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request,$tenant, $id) {
        $loan = Loan::find($id);
        if ($loan->status == 2) {
            return back()->with('error', _lang('Sorry, This Loan is already completed'));
        }

        $customFields = CustomField::where('table', 'loans')
            ->where('status', 1)
            ->orderBy("id", "asc")
            ->get();

        if (! $request->ajax()) {
            return view('backend.admin.loan.edit', compact('loan', 'id', 'customFields'));
        } else {
            return view('backend.admin.loan.modal.edit', compact('loan', 'id', 'customFields'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request,$tenant, $id) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $loan = Loan::find($id);
        if ($loan->status == 2) {
            return back()->with('error', _lang('Sorry, This Loan is already completed'));
        }
        if ($loan->status != 0) {
            $loan->description = $request->input('description');
            $loan->remarks     = $request->input('remarks');

            $loan->save();

            return redirect()->route('loans.index')->with('success', _lang('Updated successfully'));
        } else {
            $validationRules = [
                'loan_id'                => [
                    'required',
                    Rule::unique('loans')->ignore($id),
                ],
                'loan_product_id'        => 'required',
                'borrower_id'            => 'required',
                'currency_id'            => 'required',
                'first_payment_date'     => 'required',
                'release_date'           => 'required',
                'applied_amount'         => 'required|numeric',
                'late_payment_penalties' => 'required|numeric',
                'debit_account_id'       => 'required',
                'attachment'             => 'nullable|mimes:jpeg,JPEG,png,PNG,jpg,doc,pdf,docx,zip|max:8192', //8MB = 8192KB
            ];

            $validationMessages = [];

            // Custom field validation
            $customFields = CustomField::where('table', 'loans')
                ->orderBy("id", "desc")
                ->get();
            $customValidation = generate_custom_field_validation($customFields, true);

            $validationRules    = array_merge($validationRules, $customValidation['rules']);
            $validationMessages = array_merge($validationMessages, $customValidation['messages']);

            $validator = Validator::make($request->all(), $validationRules, $validationMessages);
        }

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('loans.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        if ($request->hasfile('attachment')) {
            $file       = $request->file('attachment');
            $attachment = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/media/", $attachment);
        }

        DB::beginTransaction();

        // Store custom field data
        $customFieldsData = store_custom_field_data($customFields, json_decode($loan->custom_fields, true));

        $loan                         = Loan::find($id);
        $loan->loan_id                = $request->input('loan_id');
        $loan->loan_product_id        = $request->input('loan_product_id');
        $loan->borrower_id            = $request->input('borrower_id');
        $loan->currency_id            = $request->input('currency_id');
        $loan->first_payment_date     = $request->input('first_payment_date');
        $loan->release_date           = $request->input('release_date');
        $loan->applied_amount         = $request->input('applied_amount');
        $loan->late_payment_penalties = $request->input('late_payment_penalties');
        if ($request->hasfile('attachment')) {
            $loan->attachment = $attachment;
        }
        $loan->description      = $request->input('description');
        $loan->remarks          = $request->input('remarks');
        $loan->debit_account_id = $request->debit_account_id;
        $loan->custom_fields    = json_encode($customFieldsData);

        // Create Loan Repayments
        $calculator = new Calculator(
            $loan->applied_amount,
            $loan->first_payment_date,
            $loan->loan_product->interest_rate,
            $loan->loan_product->term,
            $loan->loan_product->term_period,
            $loan->late_payment_penalties
        );

        if ($loan->loan_product->interest_type == 'flat_rate') {
            $repayments = $calculator->get_flat_rate();
        } else if ($loan->loan_product->interest_type == 'fixed_rate') {
            $repayments = $calculator->get_fixed_rate();
        } else if ($loan->loan_product->interest_type == 'mortgage') {
            $repayments = $calculator->get_mortgage();
        } else if ($loan->loan_product->interest_type == 'one_time') {
            $repayments = $calculator->get_one_time();
        } else if ($loan->loan_product->interest_type == 'reducing_amount') {
            $repayments = $calculator->get_reducing_amount();
        } else if ($loan->loan_product->interest_type == 'compound') {
            $repayments = $calculator->get_compound();
        }

        $loan->total_payable = $calculator->payable_amount;
        $loan->save();

        DB::commit();

        if (! $request->ajax()) {
            return redirect()->route('loans.index')->with('success', _lang('Updated successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated successfully'), 'data' => $loan, 'table' => '#loans_table']);
        }
    }

    public function upcoming_loan_repayments(Request $request) {
        try {
            // Fix date range logic - start from tomorrow, end 7 days from today
            $startDate = Carbon::today()->addDay();
            $endDate = Carbon::today()->addDays(7);
            
            // Fix relationship loading to include borrower and currency
            $loanRepayments = LoanRepayment::with(['loan.borrower', 'loan.currency'])
                ->whereBetween('repayment_date', [$startDate, $endDate])
                ->where('status', 0)
                ->orderBy('repayment_date', 'asc')
                ->paginate(50); // Add pagination for large datasets

            $assets = ['datatable'];
            return view('backend.admin.loan.upcoming_loan_repayments', compact('loanRepayments', 'startDate', 'endDate', 'assets'));
            
        } catch (\Exception $e) {
            \Log::error('Error fetching upcoming loan repayments: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            // Return empty collection on error
            $loanRepayments = collect();
            $startDate = Carbon::today()->addDay();
            $endDate = Carbon::today()->addDays(7);
            $assets = ['datatable'];
            
            return view('backend.admin.loan.upcoming_loan_repayments', compact('loanRepayments', 'startDate', 'endDate', 'assets'))
                ->with('error', _lang('An error occurred while loading upcoming loan repayments. Please try again.'));
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($tenant,$id) {
        DB::beginTransaction();

        $loan = Loan::find($id);

        $loancollaterals = LoanCollateral::where('loan_id', $loan->id);
        $loancollaterals->delete();

        $repayments = LoanRepayment::where('loan_id', $loan->id);
        $repayments->delete();

        $loanpayment = LoanPayment::where('loan_id', $loan->id);
        $loanpayment->delete();

        $transaction = Transaction::where('loan_id', $loan->id);
        $transaction->delete();

        $loan->delete();

        DB::commit();

        return redirect()->route('loans.index')->with('success', _lang('Deleted successfully'));
    }

    public function calculator() {
        $data                           = [];
        $data['first_payment_date']     = '';
        $data['apply_amount']           = '';
        $data['interest_rate']          = '';
        $data['interest_type']          = '';
        $data['term']                   = '';
        $data['term_period']            = '';
        $data['late_payment_penalties'] = 0;
        return view('backend.admin.loan.calculator', $data);
    }

    public function calculate(Request $request) {
        $validator = Validator::make($request->all(), [
            'apply_amount'           => 'required|numeric',
            'interest_rate'          => 'required',
            'interest_type'          => 'required',
            'term'                   => 'required|integer|max:100',
            'term_period'            => $request->interest_type == 'one_time' ? '' : 'required',
            'late_payment_penalties' => 'required',
            'first_payment_date'     => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('loans.admin_calculator')->withErrors($validator)->withInput();
            }
        }

        $first_payment_date     = $request->first_payment_date;
        $apply_amount           = $request->apply_amount;
        $interest_rate          = $request->interest_rate;
        $interest_type          = $request->interest_type;
        $term                   = $request->term;
        $term_period            = $request->term_period;
        $late_payment_penalties = $request->late_payment_penalties;

        $data       = [];
        $table_data = [];

        if ($interest_type == 'flat_rate') {

            $calculator             = new Calculator($apply_amount, $first_payment_date, $interest_rate, $term, $term_period, $late_payment_penalties);
            $table_data             = $calculator->get_flat_rate();
            $data['payable_amount'] = $calculator->payable_amount;

        } else if ($interest_type == 'fixed_rate') {

            $calculator             = new Calculator($apply_amount, $first_payment_date, $interest_rate, $term, $term_period, $late_payment_penalties);
            $table_data             = $calculator->get_fixed_rate();
            $data['payable_amount'] = $calculator->payable_amount;

        } else if ($interest_type == 'mortgage') {

            $calculator             = new Calculator($apply_amount, $first_payment_date, $interest_rate, $term, $term_period, $late_payment_penalties);
            $table_data             = $calculator->get_mortgage();
            $data['payable_amount'] = $calculator->payable_amount;

        } else if ($interest_type == 'one_time') {

            $calculator             = new Calculator($apply_amount, $first_payment_date, $interest_rate, 1, $term_period, $late_payment_penalties);
            $table_data             = $calculator->get_one_time();
            $data['payable_amount'] = $calculator->payable_amount;

        } else if ($interest_type == 'reducing_amount') {

            $calculator             = new Calculator($apply_amount, $first_payment_date, $interest_rate, $term, $term_period, $late_payment_penalties);
            $table_data             = $calculator->get_reducing_amount();
            $data['payable_amount'] = $calculator->payable_amount;

        } else if ($interest_type == 'compound') {

            $calculator             = new Calculator($apply_amount, $first_payment_date, $interest_rate, $term, $term_period, $late_payment_penalties);
            $table_data             = $calculator->get_compound();
            $data['payable_amount'] = $calculator->payable_amount;

        }

        $data['table_data']             = $table_data;
        $data['first_payment_date']     = $request->first_payment_date;
        $data['apply_amount']           = $request->apply_amount;
        $data['interest_rate']          = $request->interest_rate;
        $data['interest_type']          = $request->interest_type;
        $data['term']                   = $request->term;
        $data['term_period']            = $request->term_period;
        $data['late_payment_penalties'] = $request->late_payment_penalties;

        return view('backend.admin.loan.calculator', $data);

    }

}