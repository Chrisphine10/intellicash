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
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function manual_methods() {
		$alert_col = 'col-lg-8 offset-lg-2';
		$withdraw_methods = WithdrawMethod::where('status', 1)->get();
		
		// Get tenant-specific withdrawal methods from connected bank accounts
		$tenantPaymentMethods = \App\Models\BankAccount::withPaymentMethods()
			->where('tenant_id', request()->tenant->id)
			->where('is_active', true)
			->get()
			->map(function ($account) {
				return [
					'id' => 'payment_' . $account->id,
					'name' => $account->bank_name . ' - ' . $account->account_name . ' (' . ucfirst($account->payment_method_type) . ')',
					'type' => 'payment_method',
					'bank_account' => $account,
					'currency' => $account->currency->name ?? 'KES',
					'available_balance' => $account->available_balance
				];
			});
		
		return view('backend.customer.withdraw.manual_methods', compact('withdraw_methods', 'tenantPaymentMethods', 'alert_col'));
	}

	public function manual_withdraw(Request $request, $tenant, $methodId, $otp = '') {
		if ($request->isMethod('get')) {
			$alert_col = 'col-lg-8 offset-lg-2';
			
			// Check if it's a tenant payment method
			if (str_starts_with($methodId, 'payment_')) {
				$bankAccountId = str_replace('payment_', '', $methodId);
				$bankAccount = \App\Models\BankAccount::withPaymentMethods()
					->where('id', $bankAccountId)
					->where('tenant_id', request()->tenant->id)
					->first();
				
				if (!$bankAccount) {
					return redirect()->route('withdraw.manual_methods')->with('error', 'Payment method not found');
				}
				
				$accounts = SavingsAccount::with('savings_type')
					->whereHas('savings_type', function (Builder $query) {
						$query->where('allow_withdraw', 1);
					})
					->where('member_id', auth()->user()->member->id)
					->get();
				
				return view('backend.customer.withdraw.payment_method_withdraw', compact('bankAccount', 'accounts', 'alert_col'));
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

			$account = SavingsAccount::where('id', $request->debit_account)
				->where('member_id', $member_id)
				->first();
			$accountType = $account->savings_type;

			//Secondary validation
			$validator = Validator::make($request->all(), [
				'debit_account' => 'required',
				'requirements.*' => 'required',
				'amount' => "required|numeric",
				'attachment' => 'nullable|mimes:jpeg,JPEG,png,PNG,jpg,doc,pdf,docx|max:4096',
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
				return back()->with('error', _lang('Withdraw limit') . ' ' . $minimumAmount . ' ' . $accountType->currency->name . ' -- ' . $maximumAmount . ' ' . $accountType->currency->name)->withInput();
			}

			//Convert gateway currency to account currency
			$charge = convert_currency($withdraw_method->currency->name, $accountType->currency->name, $charge);

			if ($accountType->allow_withdraw == 0) {
				return back()
					->with('error', _lang('Withdraw is not allowed for') . ' ' . $accountType->name)
					->withInput();
			}

			$account_balance = get_account_balance($request->debit_account, $member_id);
			if (($account_balance - $request->amount) < $accountType->minimum_account_balance) {
				return back()
					->with('error', _lang('Sorry Minimum account balance will be exceeded'))
					->withInput();
			}

			//Check Available Balance
			if ($account_balance < $request->amount) {
				return back()
					->with('error', _lang('Insufficient account balance'))
					->withInput();
			}

			$attachment = "";
			if ($request->hasfile('attachment')) {
				$file = $request->file('attachment');
				$attachment = time() . $file->getClientOriginalName();
				$file->move(public_path() . "/uploads/media/", $attachment);
			}

			DB::beginTransaction();

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
			$withdrawRequest->converted_amount = convert_currency($withdrawRequest->account->savings_type->currency->name, $withdraw_method->currency->name, $request->amount);
			$withdrawRequest->description = $request->description;
			$withdrawRequest->requirements = json_encode($request->requirements);
			$withdrawRequest->attachment = $attachment;
			$withdrawRequest->transaction_id = $debit->id;
			$withdrawRequest->save();

			DB::commit();

			if (!$request->ajax()) {
				return redirect()->route('withdraw.manual_methods')->with('success', _lang('Withdraw Request submitted successfully'));
			} else {
				return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Withdraw Request submitted successfully'), 'data' => $withdrawRequest, 'table' => '#unknown_table']);
			}
		}
	}

	/**
	 * Process payment method withdrawal
	 */
	private function processPaymentMethodWithdrawal($request, $methodId, $member_id)
	{
		$bankAccountId = str_replace('payment_', '', $methodId);
		$bankAccount = \App\Models\BankAccount::withPaymentMethods()
			->where('id', $bankAccountId)
			->where('tenant_id', request()->tenant->id)
			->first();

		if (!$bankAccount) {
			return back()->with('error', 'Payment method not found');
		}

		// Validate payment method specific fields
		$validator = Validator::make($request->all(), [
			'debit_account' => 'required|exists:savings_accounts,id',
			'amount' => 'required|numeric|min:1',
			'description' => 'nullable|string|max:255',
			'recipient_name' => 'required|string|max:255',
			'recipient_mobile' => 'required|string|max:20',
			'recipient_account' => 'required|string|max:50',
			'recipient_bank_code' => 'nullable|string|max:10'
		]);

		if ($validator->fails()) {
			return back()->withErrors($validator)->withInput();
		}

		$account = SavingsAccount::where('id', $request->debit_account)
			->where('member_id', $member_id)
			->first();

		if (!$account) {
			return back()->with('error', 'Invalid account selected');
		}

		// Check available balance
		$availableBalance = get_account_balance($request->debit_account, $member_id);
		if ($availableBalance < $request->amount) {
			return back()->with('error', 'Insufficient balance for withdrawal');
		}

		DB::beginTransaction();

		try {
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

			DB::commit();

			return redirect()->route('withdraw.manual_methods')
				->with('success', 'Withdrawal request submitted successfully. It will be processed after tenant approval.');

		} catch (\Exception $e) {
			DB::rollback();
			Log::error('Payment method withdrawal error: ' . $e->getMessage());
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

}