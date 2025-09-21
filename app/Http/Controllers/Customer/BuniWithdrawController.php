<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\WithdrawMethod;
use App\Models\WithdrawRequest;
use App\Models\SavingsAccount;
use App\Notifications\WithdrawMoney;
use App\Notifications\BuniTransactionNotification;
use App\Utilities\BuniActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BuniWithdrawController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        date_default_timezone_set(get_timezone());
    }

    /**
     * Show Buni withdraw form
     */
    public function showWithdrawForm()
    {
        $withdrawMethod = WithdrawMethod::where('name', 'KCB Buni Mobile Money')
            ->where('tenant_id', request()->tenant->id)
            ->where('status', 1)
            ->first();

        if (!$withdrawMethod) {
            return redirect()->route('withdraw.manual_methods')
                ->with('error', 'KCB Buni withdraw method is not available');
        }

        $member = auth()->user()->member;
        $savingsAccounts = SavingsAccount::where('member_id', $member->id)
            ->where('status', 1)
            ->get();

        return view('backend.customer.withdraw.buni_form', compact('withdrawMethod', 'savingsAccounts'));
    }

    /**
     * Process Buni withdraw request
     */
    public function processWithdraw(Request $request)
    {
        Log::info('Buni withdraw request started', [
            'request_data' => $request->all(),
            'user_id' => auth()->id(),
            'tenant_id' => request()->tenant->id ?? 'unknown'
        ]);

        $validator = Validator::make($request->all(), [
            'debit_account' => 'required|exists:savings_accounts,id',
            'amount' => 'required|numeric|min:10',
            'mobile_number' => 'required|string|min:9|max:10|regex:/^[0-9]{9,10}$/',
            'description' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            Log::warning('Buni withdraw validation failed', [
                'errors' => $validator->errors()->toArray(),
                'user_id' => auth()->id()
            ]);
            return back()->withErrors($validator)->withInput();
        }

        $member = auth()->user()->member;
        $withdrawMethod = WithdrawMethod::where('name', 'KCB Buni Mobile Money')
            ->where('tenant_id', request()->tenant->id)
            ->first();

        // Get account details
        $account = SavingsAccount::find($request->debit_account);
        if (!$account || $account->member_id != $member->id) {
            return back()->with('error', 'Invalid account selected');
        }

        // Check account balance
        $accountBalance = get_account_balance($request->debit_account, $member->id);
        if ($accountBalance < $request->amount) {
            return back()->with('error', 'Insufficient account balance');
        }

        // Calculate charges (you can implement charge calculation logic here)
        $charge = 0; // Buni might have their own charges
        $totalAmount = $request->amount + $charge;

        if ($accountBalance < $totalAmount) {
            return back()->with('error', 'Insufficient balance to cover amount and charges');
        }

        DB::beginTransaction();

        try {
            // Create debit transaction
            $debit = new Transaction();
            $debit->trans_date = now();
            $debit->member_id = $member->id;
            $debit->savings_account_id = $request->debit_account;
            $debit->charge = $charge;
            $debit->amount = $request->amount;
            $debit->dr_cr = 'dr';
            $debit->type = 'Withdraw';
            $debit->method = 'Buni';
            $debit->status = 0; // Pending
            $debit->created_user_id = auth()->id();
            $debit->branch_id = $member->branch_id;
            $debit->description = 'Withdraw via KCB Buni to ' . $request->mobile_number;
            $debit->save();

            // Create withdraw request
            $withdrawRequest = new WithdrawRequest();
            $withdrawRequest->member_id = $member->id;
            $withdrawRequest->method_id = $withdrawMethod->id;
            $withdrawRequest->debit_account_id = $request->debit_account;
            $withdrawRequest->amount = $request->amount;
            $withdrawRequest->converted_amount = $request->amount;
            $withdrawRequest->description = $request->description;
            $withdrawRequest->requirements = json_encode([
                'mobile_number' => $request->mobile_number,
                'amount' => $request->amount,
                'description' => $request->description
            ]);
            $withdrawRequest->transaction_id = $debit->id;
            $withdrawRequest->save();

            // Process withdrawal with Buni API
            $buniResponse = $this->processBuniWithdrawal($withdrawRequest, $request->mobile_number);

            if ($buniResponse['success']) {
                // Update transaction status
                $debit->status = 2; // Completed
                $debit->transaction_details = json_encode($buniResponse['data']);
                $debit->save();

                $withdrawRequest->status = 2; // Completed
                $withdrawRequest->save();

                // Send notification
                try {
                    $member->notify(new WithdrawMoney($debit));
                    $member->notify(new BuniTransactionNotification($debit, 'withdraw'));
                } catch (\Exception $e) {
                    Log::error('Withdraw notification failed: ' . $e->getMessage());
                }

                DB::commit();

                return redirect()->route('withdraw.manual_methods')
                    ->with('success', 'Withdrawal completed successfully. Money has been sent to ' . $request->mobile_number);
            } else {
                // Update transaction status to failed
                $debit->status = 3; // Failed
                $debit->transaction_details = json_encode($buniResponse);
                $debit->save();

                $withdrawRequest->status = 3; // Failed
                $withdrawRequest->save();

                DB::commit();

                return back()->with('error', 'Withdrawal failed: ' . $buniResponse['message']);
            }

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Buni withdrawal error: ' . $e->getMessage());
            return back()->with('error', 'An error occurred while processing withdrawal');
        }
    }

    /**
     * Process withdrawal with Buni API
     */
    private function processBuniWithdrawal($withdrawRequest, $mobileNumber)
    {
        try {
            // Get Buni configuration from automatic gateway (tenant-specific)
            $buniGateway = DB::table('automatic_gateways')
                ->where('slug', 'Buni')
                ->where('tenant_id', request()->tenant->id)
                ->where('status', 1)
                ->first();

            if (!$buniGateway) {
                return [
                    'success' => false,
                    'message' => 'KCB Buni gateway is not configured or enabled for your account. Please contact your administrator.'
                ];
            }

            $parameters = json_decode($buniGateway->parameters, true);
            $baseUrl = $parameters['buni_base_url'];
            $clientId = $parameters['buni_client_id'];
            $clientSecret = $parameters['buni_client_secret'];

            // Get access token
            $tokenResponse = Http::post($baseUrl . '/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if (!$tokenResponse->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to authenticate with Buni API'
                ];
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'];

            // Send money via Buni API
            $sendMoneyResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($baseUrl . '/api/v1/send-money', [
                'phoneNumber' => $mobileNumber,
                'amount' => $withdrawRequest->amount,
                'currency' => 'KES',
                'narration' => $withdrawRequest->description ?: 'Withdrawal from IntelliCash',
                'customerReference' => 'WTH-' . $withdrawRequest->id . '-' . time(),
                'callbackUrl' => route('callback.Buni.withdraw')
            ]);

            if ($sendMoneyResponse->successful()) {
                $responseData = $sendMoneyResponse->json();
                
                return [
                    'success' => true,
                    'data' => $responseData,
                    'message' => 'Money sent successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send money: ' . $sendMoneyResponse->body()
                ];
            }

        } catch (\Exception $e) {
            Log::error('Buni API error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'API error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle Buni withdraw callback
     */
    public function handleWithdrawCallback(Request $request)
    {
        Log::info('Buni withdraw callback received: ' . json_encode($request->all()));

        // Validate signature
        $signature = $request->header('Signature');
        if (!$this->validateSignature($request, $signature)) {
            Log::error('Invalid Buni withdraw callback signature');
            return response()->json([
                'transactionID' => $request->transactionReference ?? '',
                'statusCode' => 1,
                'statusMessage' => 'Invalid signature'
            ], 400);
        }

        try {
            // Find the withdraw request by customer reference
            $customerReference = $request->customerReference;
            $withdrawRequest = WithdrawRequest::where('requirements', 'like', '%' . $customerReference . '%')
                ->where('status', 0) // Pending
                ->first();

            if (!$withdrawRequest) {
                Log::error('Withdraw request not found for reference: ' . $customerReference);
                return response()->json([
                    'transactionID' => $request->transactionReference ?? '',
                    'statusCode' => 1,
                    'statusMessage' => 'Withdraw request not found'
                ], 404);
            }

            // Update transaction status based on Buni response
            $transaction = Transaction::find($withdrawRequest->transaction_id);
            if ($transaction) {
                if ($request->status === 'success' || $request->status === 'completed') {
                    $transaction->status = 2; // Completed
                    $withdrawRequest->status = 2; // Completed
                } else {
                    $transaction->status = 3; // Failed
                    $withdrawRequest->status = 3; // Failed
                }
                
                $transaction->transaction_details = json_encode($request->all());
                $transaction->save();
                $withdrawRequest->save();

                // Send notification
                try {
                    $withdrawRequest->member->notify(new WithdrawMoney($transaction));
                } catch (\Exception $e) {
                    Log::error('Withdraw notification failed: ' . $e->getMessage());
                }
            }

            return response()->json([
                'transactionID' => $request->transactionReference,
                'statusCode' => 0,
                'statusMessage' => 'Callback processed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Buni withdraw callback processing error: ' . $e->getMessage());
            return response()->json([
                'transactionID' => $request->transactionReference ?? '',
                'statusCode' => 1,
                'statusMessage' => 'Processing error'
            ], 500);
        }
    }

    /**
     * Validate Buni signature
     */
    private function validateSignature(Request $request, $signature)
    {
        // Implement signature validation based on Buni documentation
        // This is a placeholder - implement actual signature validation
        return true;
    }
}
