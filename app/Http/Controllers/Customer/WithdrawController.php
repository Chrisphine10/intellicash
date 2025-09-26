<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Models\WithdrawMethod;
use App\Models\WithdrawRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WithdrawController extends Controller {

	/**
	 * Create a new controller instance.
	 *
	 * @return void
	 */
	public function __construct() {
		date_default_timezone_set(get_timezone());
		
		// Apply authorization middleware
		$this->middleware('auth');
		$this->middleware('transaction.auth:transactions.create')->only(['manual_withdraw', 'processPaymentMethodWithdrawal']);
		$this->middleware('transaction.auth:transactions.view')->only(['withdrawalHistory', 'withdrawalRequests', 'withdrawalRequestDetails']);
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function manual_methods() {
		$alert_col = 'col-lg-8 offset-lg-2';
		$withdraw_methods = WithdrawMethod::where('status', 1)->get();
		
		// Get tenant-specific payment methods
		$tenantPaymentMethods = \App\Models\PaymentMethod::active()
			->where('tenant_id', request()->tenant->id)
			->where('type', '!=', 'manual') // Only show automated payment methods for withdrawals
			->get()
			->map(function ($paymentMethod) {
				return [
					'id' => 'payment_' . $paymentMethod->id,
					'name' => $paymentMethod->display_name,
					'type' => 'payment_method',
					'payment_method' => $paymentMethod,
					'currency' => $paymentMethod->currency->name ?? 'KES',
				];
			});
		
		return view('backend.customer.withdraw.manual_methods', compact('withdraw_methods', 'tenantPaymentMethods', 'alert_col'));
	}

	public function manual_withdraw(Request $request, $tenant, $methodId, $otp = '') {
		if ($request->isMethod('get')) {
			$alert_col = 'col-lg-8 offset-lg-2';
			
			// Check if it's a tenant payment method
			if (str_starts_with($methodId, 'payment_')) {
				$paymentMethodId = str_replace('payment_', '', $methodId);
				$paymentMethod = \App\Models\PaymentMethod::active()
					->where('id', $paymentMethodId)
					->where('tenant_id', request()->tenant->id)
					->first();
				
				if (!$paymentMethod) {
					return redirect()->route('withdraw.manual_methods')->with('error', 'Payment method not found');
				}
				
				$accounts = SavingsAccount::with('savings_type')
					->whereHas('savings_type', function (Builder $query) {
						$query->where('allow_withdraw', 1);
					})
					->where('member_id', auth()->user()->member->id)
					->get();
				
				return view('backend.customer.withdraw.payment_method_withdraw', compact('paymentMethod', 'accounts', 'alert_col'));
			} else {
				// Traditional withdrawal method
				$withdraw_method = WithdrawMethod::find($methodId);
				$accounts = SavingsAccount::with('savings_type')
					->whereHas('savings_type', function (Builder $query) {
						$query->where('allow_withdraw', 1);
					})
					->where('member_id', auth()->user()->member->id)
					->get();
				return view('backend.customer.withdraw.manual_withdraw', compact('withdraw_method', 'accounts', 'alert_col'));
			}
		} else if ($request->isMethod('post')) {

			//Initial validation
			$validated = $request->validate([
				'debit_account' => 'required',
			]);

			$member_id = auth()->user()->member->id;
			
			// Check if it's a tenant payment method
			if (str_starts_with($methodId, 'payment_')) {
				return $this->processPaymentMethodWithdrawal($request, $methodId, $member_id);
			}
			
			$withdraw_method = WithdrawMethod::find($methodId);

		//Secondary validation with enhanced security
		$validator = Validator::make($request->all(), [
			'debit_account' => 'required|integer|min:1',
			'requirements.*' => 'required|string|max:255',
			'amount' => 'required|numeric|min:0.01|max:999999.99|regex:/^\d+(\.\d{1,2})?$/',
			'attachment' => 'nullable|mimes:jpeg,JPEG,png,PNG,jpg,doc,pdf,docx|max:4096',
			'description' => 'nullable|string|max:500',
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

		// Use database transaction with pessimistic locking to prevent race conditions
		try {
			return DB::transaction(function() use ($request, $member_id, $methodId, $withdraw_method) {
				// Lock the account to prevent concurrent modifications
				$account = SavingsAccount::where('id', $request->debit_account)
					->where('member_id', $member_id)
					->where('tenant_id', request()->tenant->id)
					->lockForUpdate()
					->first();

				if (!$account) {
					throw new \Exception('Account not found or unauthorized access');
				}

				$accountType = $account->savings_type;

				//Convert account currency to gateway currency
				$convertedAdmount = convert_currency($accountType->currency->name, $withdraw_method->currency->name, $request->amount);

				$chargeLimit = $withdraw_method->chargeLimits()->where('minimum_amount', '<=', $convertedAdmount)->where('maximum_amount', '>=', $convertedAdmount)->first();

				if ($chargeLimit) {
					$fixedCharge = $chargeLimit->fixed_charge;
					$percentageCharge = ($convertedAdmount * $chargeLimit->charge_in_percentage) / 100;
					$charge = $fixedCharge + $percentageCharge;
				} else {
					//Convert minimum amount to selected currency
					$minimumAmount = convert_currency($withdraw_method->currency->name, $accountType->currency->name, $withdraw_method->chargeLimits()->min('minimum_amount'));
					$maximumAmount = convert_currency($withdraw_method->currency->name, $accountType->currency->name, $withdraw_method->chargeLimits()->max('maximum_amount'));
					throw new \Exception(_lang('Withdraw limit') . ' ' . $minimumAmount . ' ' . $accountType->currency->name . ' -- ' . $maximumAmount . ' ' . $accountType->currency->name);
				}

				//Convert gateway currency to account currency
				$charge = convert_currency($withdraw_method->currency->name, $accountType->currency->name, $charge);

				// Validate charge calculation
				if ($charge < 0) {
					throw new \Exception('Invalid charge calculation');
				}
				if ($charge > $request->amount) {
					throw new \Exception('Charge exceeds withdrawal amount');
				}

				if ($accountType->allow_withdraw == 0) {
					throw new \Exception(_lang('Withdraw is not allowed for') . ' ' . $accountType->name);
				}

				// Check available balance with locked account - use atomic calculation
				$account_balance = $this->getAccountBalanceAtomic($request->debit_account, $member_id);
				if (($account_balance - $request->amount) < $accountType->minimum_account_balance) {
					throw new \Exception(_lang('Sorry Minimum account balance will be exceeded'));
				}

				//Check Available Balance
				if ($account_balance < $request->amount) {
					throw new \Exception(_lang('Insufficient account balance'));
				}

				// Secure file upload handling
				$attachment = "";
				if ($request->hasfile('attachment')) {
					$file = $request->file('attachment');
					$filename = \Str::uuid() . '.' . $file->getClientOriginalExtension();
					$file->storeAs('private/withdraw-attachments', $filename);
					$attachment = $filename;
				}

				// Log withdrawal attempt
				\Log::info('Withdrawal request initiated', [
					'member_id' => $member_id,
					'account_id' => $request->debit_account,
					'amount' => $request->amount,
					'charge' => $charge,
					'ip_address' => $request->ip(),
					'user_agent' => $request->userAgent(),
					'tenant_id' => request()->tenant->id
				]);

				//Create Debit Transaction
				$debit = new Transaction();
				$debit->trans_date = now();
				$debit->member_id = $member_id;
				$debit->savings_account_id = $request->debit_account;
				$debit->charge = $charge;
				$debit->amount = $request->amount - $charge;
				$debit->dr_cr = 'dr';
				$debit->type = 'Withdraw';
				$debit->method = 'Manual';
				$debit->status = 0;
				$debit->created_user_id = auth()->id();
				$debit->branch_id = auth()->user()->member->branch_id;
				$debit->description = _lang('Withdraw Money via') . ' ' . $withdraw_method->name;
				$debit->save();

				//Create Charge Transaction
				if ($charge > 0) {
					$fee = new Transaction();
					$fee->trans_date = now();
					$fee->member_id = $member_id;
					$fee->savings_account_id = $request->debit_account;
					$fee->amount = $charge;
					$fee->dr_cr = 'dr';
					$fee->type = 'Fee';
					$fee->method = 'Manual';
					$fee->status = 0;
					$fee->created_user_id = auth()->id();
					$fee->branch_id = auth()->user()->member->branch_id;
					$fee->description = $withdraw_method->name . ' ' . _lang('Withdraw Fee');
					$fee->parent_id = $debit->id;
					$fee->save();
				}

				$withdrawRequest = new WithdrawRequest();
				$withdrawRequest->member_id = $member_id;
				$withdrawRequest->method_id = $methodId;
				$withdrawRequest->debit_account_id = $request->debit_account;
				$withdrawRequest->amount = $request->amount;
				$withdrawRequest->converted_amount = convert_currency($accountType->currency->name, $withdraw_method->currency->name, $request->amount);
				$withdrawRequest->description = $request->description;
				$withdrawRequest->requirements = json_encode($request->requirements);
				$withdrawRequest->attachment = $attachment;
				$withdrawRequest->transaction_id = $debit->id;
				$withdrawRequest->save();

				// Log successful withdrawal request
				\Log::info('Withdrawal request created successfully', [
					'withdraw_request_id' => $withdrawRequest->id,
					'transaction_id' => $debit->id,
					'member_id' => $member_id,
					'amount' => $request->amount,
					'charge' => $charge
				]);

				if (!$request->ajax()) {
					return redirect()->route('withdraw.manual_methods')->with('success', _lang('Withdraw Request submitted successfully'));
				} else {
					return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Withdraw Request submitted successfully'), 'data' => $withdrawRequest, 'table' => '#unknown_table']);
				}
			}, 5); // 5 second timeout for transaction
		} catch (\Exception $e) {
			\Log::error('Withdrawal request failed', [
				'member_id' => $member_id,
				'account_id' => $request->debit_account,
				'amount' => $request->amount,
				'error' => $e->getMessage(),
				'ip_address' => $request->ip(),
				'tenant_id' => request()->tenant->id
			]);

			if ($request->ajax()) {
				return response()->json(['result' => 'error', 'message' => $e->getMessage()]);
			} else {
				return back()->with('error', $e->getMessage())->withInput();
			}
		}
	}
	}

	/**
	 * Process payment method withdrawal
	 */
	public function processPaymentMethodWithdrawal($request, $methodId, $member_id)
	{
		$bankAccountId = str_replace('payment_', '', $methodId);
		$bankAccount = \App\Models\BankAccount::withPaymentMethods()
			->where('id', $bankAccountId)
			->where('tenant_id', request()->tenant->id)
			->first();

		if (!$bankAccount) {
			return back()->with('error', 'Payment method not found');
		}

		// Enhanced validation for payment method withdrawals
		$validator = Validator::make($request->all(), [
			'debit_account' => 'required|integer|min:1',
			'amount' => 'required|numeric|min:0.01|max:999999.99|regex:/^\d+(\.\d{1,2})?$/',
			'description' => 'nullable|string|max:500',
			'recipient_name' => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
			'recipient_mobile' => 'required|regex:/^[0-9]{10,15}$/',
			'recipient_account' => 'required|regex:/^[0-9]{10,20}$/',
			'recipient_bank_code' => 'nullable|string|max:10|regex:/^[0-9]{3,4}$/'
		]);

		if ($validator->fails()) {
			return back()->withErrors($validator)->withInput();
		}

		// Use database transaction with pessimistic locking
		try {
			return DB::transaction(function() use ($request, $methodId, $member_id, $bankAccountId) {
			// Lock the account to prevent concurrent modifications
			$account = SavingsAccount::where('id', $request->debit_account)
				->where('member_id', $member_id)
				->where('tenant_id', request()->tenant->id)
				->lockForUpdate()
				->first();

			if (!$account) {
				throw new \Exception('Account not found or unauthorized access');
			}

			// Check available balance with locked account
			$availableBalance = $this->getAccountBalanceAtomic($request->debit_account, $member_id);
			if ($availableBalance < $request->amount) {
				throw new \Exception('Insufficient balance for withdrawal');
			}

			// Log withdrawal attempt
			\Log::info('Payment method withdrawal request initiated', [
				'member_id' => $member_id,
				'account_id' => $request->debit_account,
				'amount' => $request->amount,
				'payment_method_id' => $bankAccountId,
				'ip_address' => $request->ip(),
				'user_agent' => $request->userAgent(),
				'tenant_id' => request()->tenant->id
			]);

			// Create withdraw request
			$withdrawRequest = new WithdrawRequest();
			$withdrawRequest->member_id = $member_id;
			$withdrawRequest->method_id = null; // No traditional method for payment method withdrawals
			$withdrawRequest->debit_account_id = $request->debit_account;
			$withdrawRequest->amount = $request->amount;
			$withdrawRequest->converted_amount = $request->amount;
			$withdrawRequest->description = $request->description;
			$withdrawRequest->requirements = json_encode([
				'payment_method_id' => $bankAccount->id,
				'payment_method_type' => $bankAccount->payment_method_type,
				'recipient_details' => [
					'name' => $request->recipient_name,
					'mobile' => $request->recipient_mobile,
					'account_number' => $request->recipient_account,
					'bank_code' => $request->recipient_bank_code
				]
			]);
			$withdrawRequest->status = 0; // Pending approval
			$withdrawRequest->save();

			// Create pending transaction
			$debit = new Transaction();
			$debit->trans_date = now();
			$debit->member_id = $member_id;
			$debit->savings_account_id = $request->debit_account;
			$debit->amount = $request->amount;
			$debit->dr_cr = 'dr';
			$debit->type = 'Withdraw';
			$debit->method = ucfirst($bankAccount->payment_method_type);
			$debit->status = 0; // Pending approval
			$debit->created_user_id = auth()->id();
			$debit->branch_id = auth()->user()->member->branch_id;
			$debit->description = 'Withdrawal request via ' . ucfirst($bankAccount->payment_method_type) . ' to ' . $request->recipient_name;
			$debit->save();

			$withdrawRequest->transaction_id = $debit->id;
			$withdrawRequest->save();

			// Log successful withdrawal request
			\Log::info('Payment method withdrawal request created successfully', [
				'withdraw_request_id' => $withdrawRequest->id,
				'transaction_id' => $debit->id,
				'member_id' => $member_id,
				'amount' => $request->amount,
				'payment_method_id' => $bankAccountId
			]);

			return redirect()->route('withdraw.manual_methods')
				->with('success', 'Withdrawal request submitted successfully. It will be processed after tenant approval.');

			}, 5); // 5 second timeout for transaction
		} catch (\Exception $e) {
			\Log::error('Payment method withdrawal request failed', [
				'member_id' => $member_id,
				'account_id' => $request->debit_account,
				'amount' => $request->amount,
				'error' => $e->getMessage(),
				'ip_address' => $request->ip(),
				'tenant_id' => request()->tenant->id
			]);
			return back()->with('error', 'An error occurred while processing withdrawal request: ' . $e->getMessage())->withInput();
		}
	}

	/**
	 * Display withdrawal history for the authenticated customer
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function withdrawalHistory()
	{
		$member = auth()->user()->member;
		
		// Get all withdrawal transactions for the member
		$withdrawals = Transaction::where('member_id', $member->id)
			->where('type', 'Withdraw')
			->orderBy('trans_date', 'desc')
			->paginate(20);

		return view('backend.customer.withdraw.history', compact('withdrawals'));
	}

	/**
	 * Display withdrawal requests for the authenticated customer
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function withdrawalRequests()
	{
		$member = auth()->user()->member;
		
		// Get all withdrawal requests for the member
		$withdrawRequests = WithdrawRequest::where('member_id', $member->id)
			->with(['method', 'account', 'transaction'])
			->orderBy('created_at', 'desc')
			->paginate(20);

		return view('backend.customer.withdraw.requests', compact('withdrawRequests'));
	}

	/**
	 * Display withdrawal request details
	 *
	 * @param int $id
	 * @return \Illuminate\Http\Response
	 */
	public function withdrawalRequestDetails(Request $request, $tenant, $id)
	{
		$member = auth()->user()->member;
		
		$withdrawal = Transaction::where('id', $id)
			->where('member_id', $member->id)
			->where('type', 'Withdraw')
			->firstOrFail();

		// If it's an AJAX request, return just the modal content
		if ($request->ajax()) {
			return view('backend.customer.withdraw.request_details', compact('withdrawal'));
		}

		// Otherwise return the full page
		return view('backend.customer.withdraw.details', compact('withdrawal'));
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