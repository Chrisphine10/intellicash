<?php

namespace App\Http\Controllers;

use App\Services\VslaLoanCalculator;
use App\Models\VslaTransaction;
use App\Models\VslaMeeting;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\Loan;
use App\Models\SavingsAccount;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class VslaTransactionsController extends Controller
{
    /**
     * Display a listing of VSLA transactions
     */
    public function index(Request $request)
    {
        $tenant = app('tenant');
        
        // Check if VSLA module is enabled
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Check authorization with tenant-specific permissions
        if (!$this->canAccessVslaTransactions($tenant)) {
            return back()->with('error', _lang('Permission denied! You do not have access to VSLA transactions.'));
        }
        
        $query = $tenant->vslaTransactions()
            ->with(['meeting', 'member', 'transaction', 'loan', 'bankAccount', 'createdUser']);
        
        // Filter by meeting
        if ($request->filled('meeting_id')) {
            $query->where('meeting_id', $request->meeting_id);
        }
        
        // Filter by transaction type
        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }
        
        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        $transactions = $query->orderBy('created_at', 'desc')->paginate(20);
        
        $meetings = $tenant->vslaMeetings()->orderBy('meeting_date', 'desc')->get();
        
        return view('backend.admin.vsla.transactions.index', compact('transactions', 'meetings'));
    }

    /**
     * Show the form for creating a new transaction
     */
    public function create(Request $request)
    {
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        if (!$this->canEditTransactions()) {
            return back()->with('error', _lang('Permission denied! You do not have permission to create transactions.'));
        }
        
        $meetings = $tenant->vslaMeetings()
            ->where('status', '!=', 'cancelled')
            ->orderBy('meeting_date', 'desc')
            ->get();
        
        $members = Member::where('tenant_id', $tenant->id)
            ->where('status', 1)
            ->orderBy('first_name')
            ->get();
        
        $selectedMeeting = $request->get('meeting_id');
        
        return view('backend.admin.vsla.transactions.create', compact('meetings', 'members', 'selectedMeeting'));
    }

    /**
     * Store a newly created transaction
     */
    public function store(Request $request)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        $validator = Validator::make($request->all(), [
            'meeting_id' => 'required|exists:vsla_meetings,id',
            'member_id' => 'required|exists:members,id',
            'transaction_type' => 'required|in:share_purchase,loan_issuance,loan_repayment,penalty_fine,welfare_contribution',
            'amount' => 'required|numeric|min:0.01|max:1000000', // FIXED: Added reasonable upper limit
            'description' => 'nullable|string|max:1000',
        ], [
            'amount.min' => _lang('Amount must be greater than zero'),
            'amount.max' => _lang('Amount exceeds maximum limit'),
            'transaction_type.in' => _lang('Invalid transaction type'),
            'member_id.exists' => _lang('Selected member does not exist'),
            'meeting_id.exists' => _lang('Selected meeting does not exist'),
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        DB::beginTransaction();

        try {
            $vslaTransaction = VslaTransaction::create([
                'tenant_id' => $tenant->id,
                'meeting_id' => $request->meeting_id,
                'member_id' => $request->member_id,
                'transaction_type' => $request->transaction_type,
                'amount' => $request->amount,
                'description' => $request->description,
                'status' => 'pending',
                'created_user_id' => auth()->id(),
            ]);

            // Process the transaction based on type
            $this->processVslaTransaction($vslaTransaction, $tenant);

            DB::commit();

            if ($request->ajax()) {
                return response()->json(['result' => 'success', 'message' => _lang('Transaction created successfully'), 'data' => $vslaTransaction]);
            }

            return redirect()->route('vsla.transactions.index')->with('success', _lang('Transaction created successfully'));

        } catch (\Exception $e) {
            DB::rollback();
            
            // FIXED: Enhanced error logging with detailed context
            \Log::error('VSLA Transaction Creation Error', [
                'exception' => $e,
                'request_data' => $request->all(),
                'user_id' => auth()->id(),
                'tenant_id' => $tenant->id ?? null,
                'transaction_type' => $request->transaction_type ?? 'unknown',
                'member_id' => $request->member_id ?? null,
                'amount' => $request->amount ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            $errorMessage = $e->getMessage();
            
            // FIXED: Provide more specific error messages based on exception type
            if (strpos($errorMessage, 'No active cycle') !== false) {
                $errorMessage = _lang('No active VSLA cycle found. Please create or activate a cycle first.');
            } elseif (strpos($errorMessage, 'VSLA') !== false && strpos($errorMessage, 'Account') !== false) {
                $errorMessage = _lang('VSLA account setup incomplete. Please contact administrator.');
            } elseif (strpos($errorMessage, 'Insufficient balance') !== false) {
                $errorMessage = _lang('Insufficient balance for this transaction.');
            }
            
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $errorMessage]);
            }
            
            return back()->with('error', $errorMessage)->withInput();
        }
    }

    /**
     * Ensure VSLA main cashbox account exists for the tenant
     * VSLA should only have ONE bank account (the cashbox)
     */
    private function ensureVslaAccountsExist($tenant)
    {
        // Check if main VSLA cashbox account exists
        $mainAccount = BankAccount::where('tenant_id', $tenant->id)
            ->where('bank_name', 'VSLA Internal')
            ->where('account_name', 'VSLA Main Account')
            ->first();

        if (!$mainAccount) {
            // Create only the main VSLA cashbox account
            BankAccount::create([
                'tenant_id' => $tenant->id,
                'opening_date' => now(),
                'bank_name' => 'VSLA Internal',
                'currency_id' => base_currency_id(),
                'account_name' => 'VSLA Main Account',
                'account_number' => 'VSLA-MAIN-' . $tenant->id,
                'opening_balance' => 0,
                'current_balance' => 0,
                'blocked_balance' => 0,
                'minimum_balance' => 0,
                'allow_negative_balance' => true, // VSLA cashbox can go negative
                'is_active' => true,
                'description' => 'Main VSLA cashbox account for all VSLA transactions and fund management',
            ]);
        }
        
    }

    /**
     * Get the main VSLA cashbox account for the tenant
     */
    private function getVslaCashboxAccount($tenant)
    {
        $cashboxAccount = BankAccount::where('tenant_id', $tenant->id)
            ->where('bank_name', 'VSLA Internal')
            ->where('account_name', 'VSLA Main Account')
            ->first();
        
        if (!$cashboxAccount) {
            throw new \Exception('VSLA Main Cashbox Account not found. Please ensure VSLA is properly configured.');
        }
        
        return $cashboxAccount;
    }

    /**
     * Update VSLA cashbox account balance after bank transaction
     */
    private function updateVslaCashboxBalance($bankTransaction)
    {
        $bankAccount = $bankTransaction->bankAccount;
        
        if ($bankAccount && $bankTransaction->status == BankTransaction::STATUS_APPROVED) {
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
    }

    /**
     * Process VSLA transaction based on type with proper locking
     */
    private function processVslaTransaction($vslaTransaction, $tenant)
    {
        // Use database lock to prevent race conditions
        return DB::transaction(function () use ($vslaTransaction, $tenant) {
            // Lock the VSLA transaction record to prevent concurrent processing
            $lockedTransaction = VslaTransaction::where('id', $vslaTransaction->id)
                ->lockForUpdate()
                ->first();
            
            if (!$lockedTransaction) {
                throw new \Exception('Transaction not found or already processed');
            }
            
            // Check if transaction is already processed
            if ($lockedTransaction->status !== 'pending') {
                throw new \Exception('Transaction has already been processed');
            }
            
            // Ensure VSLA accounts exist before processing
            $this->ensureVslaAccountsExist($tenant);

            try {
                switch ($lockedTransaction->transaction_type) {
                    case 'share_purchase':
                        $this->processSharePurchase($lockedTransaction, $tenant);
                        break;
                    case 'loan_issuance':
                        $this->processLoanIssuance($lockedTransaction, $tenant);
                        break;
                    case 'loan_repayment':
                        $this->processLoanRepayment($lockedTransaction, $tenant);
                        break;
                    case 'penalty_fine':
                    case 'welfare_contribution':
                        $this->processWelfareContribution($lockedTransaction, $tenant);
                        break;
                    default:
                        throw new \Exception('Invalid transaction type: ' . $lockedTransaction->transaction_type);
                }
                
                return $lockedTransaction;
            } catch (\Exception $e) {
                // Log the error for debugging
                \Log::error('VSLA Transaction Processing Error: ' . $e->getMessage(), [
                    'transaction_id' => $lockedTransaction->id,
                    'transaction_type' => $lockedTransaction->transaction_type,
                    'member_id' => $lockedTransaction->member_id,
                    'amount' => $lockedTransaction->amount,
                    'error' => $e->getTraceAsString()
                ]);
                
                throw $e;
            }
        });
    }

    /**
     * Process share purchase transaction
     */
    private function processSharePurchase($vslaTransaction, $tenant)
    {
        // Get member's VSLA Shares Account
        $memberShareAccount = \App\Models\SavingsAccount::where('tenant_id', $tenant->id)
            ->where('member_id', $vslaTransaction->member_id)
            ->whereHas('savings_type', function($q) {
                $q->where('name', 'VSLA Shares');
            })
            ->first();
        
        if (!$memberShareAccount) {
            throw new \Exception('VSLA Shares Account not found for member. Please sync VSLA accounts first.');
        }
        
        // Create bank transaction record for VSLA Main Cashbox Account
        $cashboxAccount = $this->getVslaCashboxAccount($tenant);
        
        $bankTransaction = BankTransaction::create([
            'tenant_id' => $tenant->id,
            'trans_date' => now(),
            'bank_account_id' => $cashboxAccount->id,
            'amount' => $vslaTransaction->amount,
            'dr_cr' => 'cr',
            'type' => BankTransaction::TYPE_DEPOSIT,
            'status' => BankTransaction::STATUS_APPROVED,
            'description' => 'VSLA Share Purchase - ' . $vslaTransaction->description,
            'created_user_id' => auth()->id(),
        ]);

        // Update VSLA cashbox balance
        $this->updateVslaCashboxBalance($bankTransaction);

        // Create transaction record linked to member's savings account
        $transaction = Transaction::create([
            'tenant_id' => $tenant->id,
            'trans_date' => now(),
            'member_id' => $vslaTransaction->member_id,
            'savings_account_id' => $memberShareAccount->id,
            'amount' => $vslaTransaction->amount,
            'dr_cr' => 'cr',
            'type' => 'Deposit',
            'method' => 'Manual',
            'status' => 2,
            'note' => $vslaTransaction->description,
            'description' => 'VSLA Share Purchase',
            'created_user_id' => auth()->id(),
            'branch_id' => auth()->user()->branch_id ?? null,
        ]);
        
        $vslaTransaction->update([
            'transaction_id' => $transaction->id,
            'savings_account_id' => $memberShareAccount->id,
            'status' => 'approved',
        ]);
    }

    /**
     * Process loan issuance transaction
     */
    private function processLoanIssuance($vslaTransaction, $tenant)
    {
        // Get VSLA Main Cashbox Account
        $cashboxAccount = $this->getVslaCashboxAccount($tenant);
        
        // Get VSLA loan product
        $loanProduct = \App\Models\LoanProduct::where('tenant_id', $tenant->id)
            ->where('name', 'VSLA Default Loan Product')
            ->first();
        
        if (!$loanProduct) {
            throw new \Exception('VSLA Loan Product not found');
        }
        
        // Generate loan ID
        $loanId = $loanProduct->loan_id_prefix . $loanProduct->starting_loan_id;
        
        // Calculate total payable based on loan product interest type
        // FIXED: Use centralized loan calculator for consistency
        $totalPayable = VslaLoanCalculator::calculateTotalPayable($vslaTransaction->amount, $loanProduct);
        
        // Create loan
        $loan = Loan::create([
            'tenant_id' => $tenant->id,
            'loan_id' => $loanId,
            'loan_product_id' => $loanProduct->id,
            'borrower_id' => $vslaTransaction->member_id,
            'applied_amount' => $vslaTransaction->amount,
            'total_payable' => $totalPayable,
            'total_paid' => 0,
            'currency_id' => base_currency_id(), // Use base currency (KES)
            'first_payment_date' => now()->addDays(30),
            'release_date' => now(),
            'late_payment_penalties' => $loanProduct->late_payment_penalties,
            'description' => 'VSLA Loan - ' . $vslaTransaction->description,
            'status' => 2, // Active/Disbursed
            'approved_date' => now(),
            'approved_user_id' => auth()->id(),
            'created_user_id' => auth()->id(),
            'branch_id' => auth()->user()->branch_id,
        ]);
        
        // Increment Loan ID for next loan
        if ($loanProduct->starting_loan_id != null) {
            $loanProduct->increment('starting_loan_id');
        }
        
        // Create bank transaction record for VSLA Main Cashbox Account (debit)
        $bankTransaction = BankTransaction::create([
            'tenant_id' => $tenant->id,
            'trans_date' => now(),
            'bank_account_id' => $cashboxAccount->id,
            'amount' => $vslaTransaction->amount,
            'dr_cr' => 'dr',
            'type' => BankTransaction::TYPE_LOAN_DISBURSEMENT,
            'status' => BankTransaction::STATUS_APPROVED,
            'description' => 'VSLA Loan Disbursement - ' . $vslaTransaction->description,
            'created_user_id' => auth()->id(),
        ]);

        // Update VSLA cashbox balance
        $this->updateVslaCashboxBalance($bankTransaction);

        // Create transaction record
        $transaction = Transaction::create([
            'tenant_id' => $tenant->id,
            'trans_date' => now(),
            'member_id' => $vslaTransaction->member_id,
            'loan_id' => $loan->id,
            'bank_account_id' => $cashboxAccount->id,
            'amount' => $vslaTransaction->amount,
            'dr_cr' => 'dr',
            'type' => 'Loan',
            'method' => 'Manual',
            'status' => 2,
            'note' => $vslaTransaction->description,
            'description' => 'VSLA Loan Disbursement',
            'created_user_id' => auth()->id(),
            'branch_id' => auth()->user()->branch_id ?? null,
        ]);
        
        $vslaTransaction->update([
            'loan_id' => $loan->id,
            'transaction_id' => $transaction->id,
            'bank_account_id' => $cashboxAccount->id,
            'status' => 'approved',
        ]);
    }

    /**
     * Process loan repayment transaction
     */
    private function processLoanRepayment($vslaTransaction, $tenant)
    {
        // Get VSLA Main Cashbox Account
        $cashboxAccount = $this->getVslaCashboxAccount($tenant);
        
        // Find the member's active loan
        $loan = Loan::where('tenant_id', $tenant->id)
            ->where('borrower_id', $vslaTransaction->member_id)
            ->where('status', 2)
            ->first();
        
        if (!$loan) {
            throw new \Exception('No active loan found for this member');
        }
        
        // Create bank transaction record for VSLA Main Cashbox Account (credit)
        $bankTransaction = BankTransaction::create([
            'tenant_id' => $tenant->id,
            'trans_date' => now(),
            'bank_account_id' => $cashboxAccount->id,
            'amount' => $vslaTransaction->amount,
            'dr_cr' => 'cr',
            'type' => BankTransaction::TYPE_LOAN_REPAYMENT,
            'status' => BankTransaction::STATUS_APPROVED,
            'description' => 'VSLA Loan Repayment - ' . $vslaTransaction->description,
            'created_user_id' => auth()->id(),
        ]);

        // Update VSLA cashbox balance
        $this->updateVslaCashboxBalance($bankTransaction);

        // Create transaction record
        $transaction = Transaction::create([
            'tenant_id' => $tenant->id,
            'trans_date' => now(),
            'member_id' => $vslaTransaction->member_id,
            'loan_id' => $loan->id,
            'bank_account_id' => $cashboxAccount->id,
            'amount' => $vslaTransaction->amount,
            'dr_cr' => 'cr',
            'type' => 'Loan Payment',
            'method' => 'Manual',
            'status' => 2,
            'note' => $vslaTransaction->description,
            'description' => 'VSLA Loan Repayment',
            'created_user_id' => auth()->id(),
            'branch_id' => auth()->user()->branch_id ?? null,
        ]);
        
        $vslaTransaction->update([
            'loan_id' => $loan->id,
            'transaction_id' => $transaction->id,
            'bank_account_id' => $cashboxAccount->id,
            'status' => 'approved',
        ]);
    }

    /**
     * Process welfare contribution transaction
     */
    private function processWelfareContribution($vslaTransaction, $tenant)
    {
        if ($vslaTransaction->transaction_type === 'penalty_fine') {
            // For penalty fines, deduct from member's share account
            $this->processPenaltyFine($vslaTransaction, $tenant);
            return;
        }
        
        // For welfare contributions, use member's welfare account
        $memberAccount = \App\Models\SavingsAccount::where('tenant_id', $tenant->id)
            ->where('member_id', $vslaTransaction->member_id)
            ->whereHas('savings_type', function($q) {
                $q->where('name', 'VSLA Welfare');
            })
            ->first();
        
        if (!$memberAccount) {
            throw new \Exception('VSLA Welfare Account not found for member. Please sync VSLA accounts first.');
        }
        
        // Create bank transaction record for VSLA Share Account (group account)
        $bankAccount = BankAccount::where('tenant_id', $tenant->id)
            ->where('account_name', 'VSLA Share Account')
            ->first();
        
        if ($bankAccount) {
            $bankTransaction = BankTransaction::create([
                'tenant_id' => $tenant->id,
                'trans_date' => now(),
                'bank_account_id' => $cashboxAccount->id,
                'amount' => $vslaTransaction->amount,
                'dr_cr' => 'cr',
                'type' => BankTransaction::TYPE_DEPOSIT,
                'status' => BankTransaction::STATUS_APPROVED,
                'description' => 'VSLA Welfare Contribution - ' . $vslaTransaction->description,
                'created_user_id' => auth()->id(),
            ]);
        }

        // Create transaction record linked to member's savings account
        $transaction = Transaction::create([
            'tenant_id' => $tenant->id,
            'trans_date' => now(),
            'member_id' => $vslaTransaction->member_id,
            'savings_account_id' => $memberAccount->id,
            'amount' => $vslaTransaction->amount,
            'dr_cr' => 'cr',
            'type' => 'Deposit',
            'method' => 'Manual',
            'status' => 2,
            'note' => $vslaTransaction->description,
            'description' => 'VSLA Welfare Contribution',
            'created_user_id' => auth()->id(),
            'branch_id' => auth()->user()->branch_id,
        ]);
        
        $vslaTransaction->update([
            'transaction_id' => $transaction->id,
            'savings_account_id' => $memberAccount->id,
            'status' => 'approved',
        ]);
    }

    /**
     * Process penalty fine transaction
     */
    private function processPenaltyFine($vslaTransaction, $tenant)
    {
        // Get member's VSLA Shares Account (penalty is deducted from shares)
        $memberShareAccount = \App\Models\SavingsAccount::where('tenant_id', $tenant->id)
            ->where('member_id', $vslaTransaction->member_id)
            ->whereHas('savings_type', function($q) {
                $q->where('name', 'VSLA Shares');
            })
            ->first();
        
        if (!$memberShareAccount) {
            throw new \Exception('VSLA Shares Account not found for member. Please sync VSLA accounts first.');
        }

        // Check if member has sufficient balance for penalty
        $currentBalance = get_account_balance($memberShareAccount->id, $vslaTransaction->member_id);
        if ($currentBalance < $vslaTransaction->amount) {
            throw new \Exception('Insufficient balance for penalty fine. Available: ' . number_format($currentBalance, 2));
        }
        
        // Create bank transaction record for VSLA Social Fund Account (credit - penalty goes to social fund)
        $socialFundAccount = BankAccount::where('tenant_id', $tenant->id)
            ->where('account_name', 'VSLA Social Fund Account')
            ->first();
        
        if ($socialFundAccount) {
            $bankTransaction = BankTransaction::create([
                'tenant_id' => $tenant->id,
                'trans_date' => now(),
                'bank_account_id' => $cashboxAccount->id,
                'amount' => $vslaTransaction->amount,
                'dr_cr' => 'cr',
                'type' => BankTransaction::TYPE_DEPOSIT,
                'status' => BankTransaction::STATUS_APPROVED,
                'description' => 'VSLA Penalty Fine - ' . $vslaTransaction->description,
                'created_user_id' => auth()->id(),
            ]);
        }

        // Create transaction record linked to member's share account (debit - penalty deducted from shares)
        $transaction = Transaction::create([
            'tenant_id' => $tenant->id,
            'trans_date' => now(),
            'member_id' => $vslaTransaction->member_id,
            'savings_account_id' => $memberShareAccount->id,
            'amount' => $vslaTransaction->amount,
            'dr_cr' => 'dr',
            'type' => 'Penalty',
            'method' => 'Manual',
            'status' => 2,
            'note' => $vslaTransaction->description,
            'description' => 'VSLA Penalty Fine',
            'created_user_id' => auth()->id(),
            'branch_id' => auth()->user()->branch_id ?? null,
        ]);
        
        $vslaTransaction->update([
            'transaction_id' => $transaction->id,
            'savings_account_id' => $memberShareAccount->id,
            'bank_account_id' => $socialFundAccount->id ?? null,
            'status' => 'approved',
        ]);
    }

    /**
     * Approve a pending transaction
     */
    public function approve($id)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        $vslaTransaction = $tenant->vslaTransactions()->findOrFail($id);
        
        if ($vslaTransaction->status !== 'pending') {
            return back()->with('error', _lang('Transaction is not pending'));
        }
        
        DB::beginTransaction();
        
        try {
            $this->processVslaTransaction($vslaTransaction, $tenant);
            
            DB::commit();
            
            if (request()->ajax()) {
                return response()->json(['result' => 'success', 'message' => _lang('Transaction approved successfully')]);
            }
            
            return back()->with('success', _lang('Transaction approved successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            
            if (request()->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('An error occurred while approving the transaction')]);
            }
            
            return back()->with('error', _lang('An error occurred while approving the transaction'));
        }
    }

    /**
     * Reject a pending transaction
     */
    public function reject($id)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        $vslaTransaction = $tenant->vslaTransactions()->findOrFail($id);
        
        if ($vslaTransaction->status !== 'pending') {
            return back()->with('error', _lang('Transaction is not pending'));
        }
        
        $vslaTransaction->update(['status' => 'rejected']);
        
        if (request()->ajax()) {
            return response()->json(['result' => 'success', 'message' => _lang('Transaction rejected successfully')]);
        }
        
        return back()->with('success', _lang('Transaction rejected successfully'));
    }

    /**
     * Show bulk transaction form for a specific meeting
     */
    public function bulkCreate(Request $request)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        $meetingId = $request->get('meeting_id');
        
        if (!$meetingId) {
            return redirect()->route('vsla.meetings.index')->with('error', _lang('Please select a meeting'));
        }
        
        $meeting = $tenant->vslaMeetings()->findOrFail($meetingId);
        
        $members = Member::where('tenant_id', $tenant->id)
            ->where('status', 1)
            ->orderBy('first_name')
            ->get();
        
        $vslaSettings = $tenant->vslaSettings()->first();
        
        // Ensure VSLA settings have the new share limit fields
        if ($vslaSettings && !isset($vslaSettings->min_shares_per_member)) {
            $vslaSettings->update([
                'min_shares_per_member' => 1,
                'max_shares_per_member' => 5,
                'max_shares_per_meeting' => 3,
            ]);
            $vslaSettings->refresh();
        }
        
        return view('backend.admin.vsla.transactions.bulk_create', compact('meeting', 'members', 'vslaSettings'));
    }

    /**
     * Store bulk transactions for a meeting
     */
    public function bulkStore(Request $request)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        $validator = Validator::make($request->all(), [
            'meeting_id' => 'required|exists:vsla_meetings,id',
            'transactions' => 'required|array|min:1',
            'transactions.*.member_id' => 'required|exists:members,id',
            'transactions.*.transaction_type' => 'required|in:share_purchase,loan_issuance,loan_repayment,penalty_fine,welfare_contribution',
            'transactions.*.amount' => 'required|numeric|min:0.01',
            'transactions.*.shares' => 'nullable|integer|min:0',
            'transactions.*.description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        DB::beginTransaction();

        try {
            $createdTransactions = [];
            $errors = [];

            $vslaSettings = $tenant->vslaSettings()->first();
            
            foreach ($request->transactions as $index => $transactionData) {
                try {
                    // Validate share limits for share purchases
                    if ($transactionData['transaction_type'] === 'share_purchase') {
                        $shares = $transactionData['shares'] ?? 0;
                        $shareValidation = $this->validateShareLimits($transactionData['member_id'], $shares, $request->meeting_id, $vslaSettings, $tenant);
                        if (!$shareValidation['valid']) {
                            $errors[] = "Transaction " . ($index + 1) . ": " . $shareValidation['message'];
                            continue;
                        }
                    }
                    
                    $vslaTransaction = VslaTransaction::create([
                        'tenant_id' => $tenant->id,
                        'meeting_id' => $request->meeting_id,
                        'member_id' => $transactionData['member_id'],
                        'transaction_type' => $transactionData['transaction_type'],
                        'amount' => $transactionData['amount'],
                        'shares' => $transactionData['shares'] ?? 0,
                        'description' => $transactionData['description'] ?? '',
                        'status' => 'pending',
                        'created_user_id' => auth()->id(),
                    ]);

                    // Process the transaction based on type
                    $this->processVslaTransaction($vslaTransaction, $tenant);
                    
                    $createdTransactions[] = $vslaTransaction;
                } catch (\Exception $e) {
                    $errors[] = "Transaction " . ($index + 1) . ": " . $e->getMessage();
                }
            }

            if (!empty($errors)) {
                DB::rollback();
                
                if ($request->ajax()) {
                    return response()->json([
                        'result' => 'error', 
                        'message' => 'Some transactions failed: ' . implode(', ', $errors)
                    ]);
                }
                
                return back()->with('error', 'Some transactions failed: ' . implode(', ', $errors))->withInput();
            }

            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'result' => 'success', 
                    'message' => _lang('Bulk transactions created successfully'),
                    'data' => $createdTransactions
                ]);
            }

            return redirect()->route('vsla.transactions.index')
                ->with('success', _lang('Bulk transactions created successfully'));

        } catch (\Exception $e) {
            DB::rollback();
            
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('An error occurred while creating bulk transactions')]);
            }
            
            return back()->with('error', _lang('An error occurred while creating bulk transactions'))->withInput();
        }
    }

    /**
     * Get members for bulk transaction form
     */
    public function getMembersForBulk(Request $request)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return response()->json(['result' => 'error', 'message' => _lang('Permission denied!')]);
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return response()->json(['result' => 'error', 'message' => _lang('VSLA module is not enabled')]);
        }
        
        $members = Member::where('tenant_id', $tenant->id)
            ->where('status', 1)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'member_no']);
        
        return response()->json(['result' => 'success', 'data' => $members]);
    }

    /**
     * Show transaction history for a specific meeting
     */
    public function transactionHistory(Request $request)
    {
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Check permission - admin, treasurer, or VSLA User with appropriate permissions
        if (!$this->canEditTransactions()) {
            return back()->with('error', _lang('Permission denied! You do not have permission to view transaction history.'));
        }
        
        $meetingId = $request->get('meeting_id');
        
        if (!$meetingId) {
            return redirect()->route('vsla.meetings.index')->with('error', _lang('Please select a meeting'));
        }
        
        $meeting = $tenant->vslaMeetings()->findOrFail($meetingId);
        
        $transactions = $tenant->vslaTransactions()
            ->where('meeting_id', $meetingId)
            ->with(['member', 'transaction', 'loan', 'bankAccount', 'createdUser'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return view('backend.admin.vsla.transactions.history', compact('meeting', 'transactions'));
    }

    /**
     * Show edit form for a VSLA transaction
     */
    public function edit($id)
    {
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Check permission - admin, treasurer, or VSLA User with appropriate permissions
        if (!$this->canEditTransactions()) {
            return back()->with('error', _lang('Permission denied! You do not have permission to edit transactions.'));
        }
        
        $vslaTransaction = $tenant->vslaTransactions()
            ->with(['member', 'meeting', 'transaction', 'loan'])
            ->findOrFail($id);
        
        // Only allow editing of pending transactions or recent approved transactions
        if ($vslaTransaction->status === 'rejected') {
            return back()->with('error', _lang('Cannot edit rejected transactions'));
        }
        
        $members = Member::where('tenant_id', $tenant->id)
            ->where('status', 1)
            ->orderBy('first_name')
            ->get();
        
        return view('backend.admin.vsla.transactions.edit', compact('vslaTransaction', 'members'));
    }

    /**
     * Update a VSLA transaction
     */
    public function update(Request $request, $id)
    {
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Check permission - admin, treasurer, or VSLA User with appropriate permissions
        if (!$this->canEditTransactions()) {
            return back()->with('error', _lang('Permission denied! You do not have permission to edit transactions.'));
        }
        
        $vslaTransaction = $tenant->vslaTransactions()->findOrFail($id);
        
        // Only allow editing of pending transactions or recent approved transactions
        if ($vslaTransaction->status === 'rejected') {
            return back()->with('error', _lang('Cannot edit rejected transactions'));
        }
        
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|exists:members,id',
            'transaction_type' => 'required|in:share_purchase,loan_issuance,loan_repayment,penalty_fine,welfare_contribution',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            // If transaction is already approved, we need to reverse the previous transaction
            if ($vslaTransaction->status === 'approved') {
                $this->reverseVslaTransaction($vslaTransaction, $tenant);
            }
            
            // Update the VSLA transaction
            $vslaTransaction->update([
                'member_id' => $request->member_id,
                'transaction_type' => $request->transaction_type,
                'amount' => $request->amount,
                'description' => $request->description,
                'status' => 'pending', // Reset to pending for re-processing
                'updated_user_id' => auth()->id(),
            ]);
            
            // Process the updated transaction
            $this->processVslaTransaction($vslaTransaction, $tenant);

            DB::commit();

            return redirect()->route('vsla.transactions.history', ['meeting_id' => $vslaTransaction->meeting_id])
                ->with('success', _lang('Transaction updated successfully'));

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()->with('error', _lang('An error occurred while updating the transaction: ') . $e->getMessage())->withInput();
        }
    }

    /**
     * Delete a VSLA transaction
     */
    public function destroy($id)
    {
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Check permission - admin, treasurer, or VSLA User with appropriate permissions
        if (!$this->canEditTransactions()) {
            return back()->with('error', _lang('Permission denied! You do not have permission to delete transactions.'));
        }
        
        $vslaTransaction = $tenant->vslaTransactions()->findOrFail($id);
        
        // Only allow deletion of pending transactions
        if ($vslaTransaction->status !== 'pending') {
            return back()->with('error', _lang('Cannot delete approved or rejected transactions'));
        }

        DB::beginTransaction();

        try {
            $meetingId = $vslaTransaction->meeting_id;
            $vslaTransaction->delete();

            DB::commit();

            return redirect()->route('vsla.transactions.history', ['meeting_id' => $meetingId])
                ->with('success', _lang('Transaction deleted successfully'));

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()->with('error', _lang('An error occurred while deleting the transaction'));
        }
    }

    /**
     * Check if current user can access VSLA transactions
     */
    private function canAccessVslaTransactions($tenant)
    {
        $user = auth()->user();
        
        // Verify user belongs to the same tenant
        if ($user->tenant_id !== $tenant->id) {
            return false;
        }
        
        // Super admin and tenant admin always have access
        if ($user->user_type === 'superadmin' || $user->user_type === 'admin') {
            return true;
        }
        
        // Check if user has VSLA User role with appropriate permissions
        if ($user->role && $user->role->name === 'VSLA User' && has_permission('vsla.transactions.view')) {
            return true;
        }
        
        // Check if user is a treasurer
        if (is_vsla_treasurer()) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if current user can edit VSLA transactions
     */
    private function canEditTransactions()
    {
        $user = auth()->user();
        $tenant = app('tenant');
        
        // Verify user belongs to the same tenant
        if ($user->tenant_id !== $tenant->id) {
            return false;
        }
        
        // Super admin and tenant admin always have access
        if ($user->user_type === 'superadmin' || $user->user_type === 'admin') {
            return true;
        }
        
        // Check if user has VSLA User role with edit permissions
        if ($user->role && $user->role->name === 'VSLA User' && has_permission('vsla.transactions.edit')) {
            return true;
        }
        
        // Check if user is a treasurer
        if (is_vsla_treasurer()) {
            return true;
        }
        
        return false;
    }


    /**
     * Validate share limits for share purchase transactions with enhanced validation
     */
    private function validateShareLimits($memberId, $shares, $meetingId, $vslaSettings, $tenant)
    {
        // Validate input parameters
        if (!is_numeric($shares) || $shares < 0) {
            return [
                'valid' => false,
                'message' => "Invalid shares value: {$shares}. Must be a positive number."
            ];
        }

        if ($shares > 1000) { // Reasonable upper limit
            return [
                'valid' => false,
                'message' => "Shares value too high: {$shares}. Maximum allowed: 1000."
            ];
        }

        // Get VSLA settings with defaults
        $minSharesPerMember = $vslaSettings->min_shares_per_member ?? 1;
        $maxSharesPerMember = $vslaSettings->max_shares_per_member ?? 5;
        $maxSharesPerMeeting = $vslaSettings->max_shares_per_meeting ?? 3;
        
        // Validate minimum shares
        if ($shares < $minSharesPerMember) {
            return [
                'valid' => false,
                'message' => "Minimum shares required: {$minSharesPerMember}, attempted: {$shares}"
            ];
        }
        
        // Validate maximum shares per meeting
        if ($shares > $maxSharesPerMeeting) {
            return [
                'valid' => false,
                'message' => "Maximum shares per meeting: {$maxSharesPerMeeting}, attempted: {$shares}"
            ];
        }
        
        // Get member's total shares (from all approved transactions in current cycle)
        $currentCycle = VslaCycle::getActiveCycleForTenant($tenant->id);
        $currentMemberShares = 0;
        
        if ($currentCycle) {
            $currentMemberShares = VslaTransaction::where('tenant_id', $tenant->id)
                ->where('member_id', $memberId)
                ->where('cycle_id', $currentCycle->id)
                ->where('transaction_type', 'share_purchase')
                ->where('status', 'approved')
                ->sum('shares');
        }
        
        // Check if adding these shares would exceed the member's maximum
        if (($currentMemberShares + $shares) > $maxSharesPerMember) {
            return [
                'valid' => false,
                'message' => "Member would exceed maximum shares limit. Current: {$currentMemberShares}, Maximum: {$maxSharesPerMember}, Attempting to add: {$shares}"
            ];
        }

        // Validate member exists and is active
        $member = Member::where('tenant_id', $tenant->id)
            ->where('id', $memberId)
            ->where('status', 1)
            ->first();

        if (!$member) {
            return [
                'valid' => false,
                'message' => "Member not found or inactive."
            ];
        }
        
        return ['valid' => true];
    }

    /**
     * Reverse a VSLA transaction (for editing approved transactions)
     */
    private function reverseVslaTransaction($vslaTransaction, $tenant)
    {
        // This is a simplified reversal - in a production system, you might want more sophisticated reversal logic
        // For now, we'll just mark the linked transaction as cancelled if it exists
        if ($vslaTransaction->transaction_id) {
            $transaction = Transaction::find($vslaTransaction->transaction_id);
            if ($transaction) {
                $transaction->update(['status' => 0]); // Mark as cancelled
            }
        }
        
        // Reset the VSLA transaction status and clear linked IDs
        $vslaTransaction->update([
            'transaction_id' => null,
            'loan_id' => null,
            'savings_account_id' => null,
            'bank_account_id' => null,
            'status' => 'pending',
        ]);
    }

    /**
     * Calculate loan total payable based on loan product interest type
     * FIXED: Now uses centralized VslaLoanCalculator service
     *
     * @param float $amount
     * @param \App\Models\LoanProduct $loanProduct
     * @return float
     */
    private function calculateLoanTotalPayable($amount, $loanProduct)
    {
        return VslaLoanCalculator::calculateTotalPayable($amount, $loanProduct);
    }
}
