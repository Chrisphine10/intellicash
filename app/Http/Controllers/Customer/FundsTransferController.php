<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\SavingsAccount;
use App\Models\Member;
use App\Models\FundsTransferRequest;
use App\Notifications\FundsTransferNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FundsTransferController extends Controller
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
     * Show funds transfer form
     */
    public function showTransferForm()
    {
        $member = auth()->user()->member;
        $savingsAccounts = SavingsAccount::where('member_id', $member->id)
            ->where('status', 1)
            ->get();

        return view('backend.customer.funds_transfer.form', compact('savingsAccounts'));
    }

    /**
     * Process funds transfer request
     */
    public function processTransfer(Request $request)
    {
        Log::info('Funds transfer request started', [
            'request_data' => $request->all(),
            'user_id' => auth()->id(),
            'tenant_id' => request()->tenant->id ?? 'unknown'
        ]);

        $validator = Validator::make($request->all(), [
            'debit_account' => 'required|exists:savings_accounts,id',
            'transfer_type' => 'required|in:kcb_buni,paystack_mpesa',
            'recipient_account' => 'required_if:transfer_type,kcb_buni',
            'recipient_mobile' => 'required_if:transfer_type,paystack_mpesa|min:9|max:10|regex:/^[0-9]{9,10}$/',
            'amount' => 'required|numeric|min:10',
            'description' => 'nullable|string|max:255',
            'beneficiary_name' => 'required|string|max:255',
            'beneficiary_bank_code' => 'required_if:transfer_type,kcb_buni|string|max:10'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $member = auth()->user()->member;
        $debitAccount = SavingsAccount::find($request->debit_account);

        // Check if account belongs to member
        if ($debitAccount->member_id !== $member->id) {
            return back()->with('error', 'Invalid account selected');
        }

        // Check available balance
        $availableBalance = get_account_balance($request->debit_account, $member->id);
        if ($availableBalance < $request->amount) {
            return back()->with('error', 'Insufficient balance for transfer');
        }

        DB::beginTransaction();

        try {
            // Create debit transaction
            $debit = new Transaction();
            $debit->trans_date = now();
            $debit->member_id = $member->id;
            $debit->savings_account_id = $request->debit_account;
            $debit->amount = $request->amount;
            $debit->dr_cr = 'dr';
            $debit->type = 'Funds_Transfer';
            $debit->method = $request->transfer_type === 'kcb_buni' ? 'KCB_Buni' : 'Paystack';
            $debit->status = 0; // Pending
            $debit->created_user_id = auth()->id();
            $debit->branch_id = $member->branch_id;
            $debit->description = 'Funds transfer to ' . $request->beneficiary_name;
            $debit->save();

            // Create transfer request
            $transferRequest = new FundsTransferRequest();
            $transferRequest->member_id = $member->id;
            $transferRequest->debit_account_id = $request->debit_account;
            $transferRequest->transfer_type = $request->transfer_type;
            $transferRequest->amount = $request->amount;
            $transferRequest->beneficiary_name = $request->beneficiary_name;
            $transferRequest->beneficiary_account = $request->recipient_account ?? null;
            $transferRequest->beneficiary_mobile = $request->recipient_mobile ?? null;
            $transferRequest->beneficiary_bank_code = $request->beneficiary_bank_code ?? null;
            $transferRequest->description = $request->description;
            $transferRequest->transaction_id = $debit->id;
            $transferRequest->status = 0; // Pending
            $transferRequest->save();

            // Process transfer based on type
            if ($request->transfer_type === 'kcb_buni') {
                $transferResponse = $this->processKcbBuniTransfer($transferRequest);
            } else {
                $transferResponse = $this->processPaystackMpesaTransfer($transferRequest);
            }

            if ($transferResponse['success']) {
                // Update transaction status
                $debit->status = 2; // Completed
                $debit->transaction_details = json_encode($transferResponse['data']);
                $debit->save();

                $transferRequest->status = 2; // Completed
                $transferRequest->api_response = json_encode($transferResponse['data']);
                $transferRequest->save();

                // Send notification
                try {
                    $member->notify(new FundsTransferNotification($debit, 'completed'));
                } catch (\Exception $e) {
                    Log::error('Transfer notification failed: ' . $e->getMessage());
                }

                DB::commit();

                return redirect()->route('funds_transfer.form')
                    ->with('success', 'Funds transfer completed successfully to ' . $request->beneficiary_name);
            } else {
                // Update transaction status to failed
                $debit->status = 3; // Failed
                $debit->transaction_details = json_encode($transferResponse);
                $debit->save();

                $transferRequest->status = 3; // Failed
                $transferRequest->api_response = json_encode($transferResponse);
                $transferRequest->save();

                DB::commit();

                return back()->with('error', 'Transfer failed: ' . $transferResponse['message']);
            }

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Funds transfer error: ' . $e->getMessage());
            return back()->with('error', 'An error occurred while processing transfer');
        }
    }

    /**
     * Process KCB Buni transfer
     */
    private function processKcbBuniTransfer($transferRequest)
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
                    'message' => 'Failed to authenticate with KCB Buni API'
                ];
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'];

            // Prepare funds transfer request
            $transferData = [
                'companyCode' => $parameters['company_code'] ?? 'KE0010001',
                'transactionType' => 'IF', // Interbank Funds Transfer
                'debitAccountNumber' => $transferRequest->debitAccount->account_number,
                'creditAccountNumber' => $transferRequest->beneficiary_account,
                'debitAmount' => $transferRequest->amount,
                'paymentDetails' => $transferRequest->description ?: 'Funds transfer via IntelliCash',
                'transactionReference' => 'TXN-' . $transferRequest->id . '-' . time(),
                'currency' => 'KES',
                'beneficiaryDetails' => $transferRequest->beneficiary_name,
                'beneficiaryBankCode' => $transferRequest->beneficiary_bank_code
            ];

            Log::info('KCB Buni transfer data prepared', [
                'transfer_data' => $transferData
            ]);

            // Send funds transfer request
            $transferResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($baseUrl . '/api/v1/transfer', $transferData);

            Log::info('KCB Buni transfer response received', [
                'status_code' => $transferResponse->status(),
                'response_body' => $transferResponse->body()
            ]);

            if ($transferResponse->successful()) {
                $responseData = $transferResponse->json();
                return [
                    'success' => true,
                    'data' => $responseData
                ];
            } else {
                $errorData = $transferResponse->json();
                return [
                    'success' => false,
                    'message' => $errorData['message'] ?? 'Transfer failed',
                    'data' => $errorData
                ];
            }

        } catch (\Exception $e) {
            Log::error('KCB Buni transfer error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Transfer processing error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process Paystack MPesa transfer
     */
    private function processPaystackMpesaTransfer($transferRequest)
    {
        try {
            // Get Paystack configuration from automatic gateway (tenant-specific)
            $paystackGateway = DB::table('automatic_gateways')
                ->where('slug', 'Paystack')
                ->where('tenant_id', request()->tenant->id)
                ->where('status', 1)
                ->first();

            if (!$paystackGateway) {
                return [
                    'success' => false,
                    'message' => 'Paystack gateway is not configured or enabled for your account. Please contact your administrator.'
                ];
            }

            $parameters = json_decode($paystackGateway->parameters, true);
            $secretKey = $parameters['paystack_secret_key'];

            // Create transfer recipient
            $recipientData = [
                'type' => 'mobile_money',
                'name' => $transferRequest->beneficiary_name,
                'account_number' => $transferRequest->beneficiary_mobile,
                'bank_code' => 'MPESA',
                'currency' => 'KES'
            ];

            $recipientResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.paystack.co/transferrecipient', $recipientData);

            if (!$recipientResponse->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to create transfer recipient'
                ];
            }

            $recipient = $recipientResponse->json();

            // Initiate transfer
            $transferData = [
                'source' => 'balance',
                'amount' => $transferRequest->amount * 100, // Convert to kobo
                'recipient' => $recipient['data']['recipient_code'],
                'reason' => $transferRequest->description ?: 'Funds transfer via IntelliCash'
            ];

            $transferResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.paystack.co/transfer', $transferData);

            if ($transferResponse->successful()) {
                $responseData = $transferResponse->json();
                return [
                    'success' => true,
                    'data' => $responseData
                ];
            } else {
                $errorData = $transferResponse->json();
                return [
                    'success' => false,
                    'message' => $errorData['message'] ?? 'Transfer failed',
                    'data' => $errorData
                ];
            }

        } catch (\Exception $e) {
            Log::error('Paystack MPesa transfer error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Transfer processing error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get transfer history
     */
    public function transferHistory()
    {
        $member = auth()->user()->member;
        $transfers = FundsTransferRequest::with(['debitAccount.savings_type'])
            ->where('member_id', $member->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('backend.customer.funds_transfer.history', compact('transfers'));
    }

    /**
     * Get transfer details
     */
    public function transferDetails($id)
    {
        $member = auth()->user()->member;
        $transfer = FundsTransferRequest::with(['debitAccount.savings_type'])
            ->where('id', $id)
            ->where('member_id', $member->id)
            ->firstOrFail();

        return view('backend.customer.funds_transfer.details', compact('transfer'));
    }
}
