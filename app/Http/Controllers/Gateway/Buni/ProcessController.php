<?php

namespace App\Http\Controllers\Gateway\Buni;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Notifications\DepositMoney;
use App\Notifications\BuniTransactionNotification;
use App\Utilities\BuniActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        ini_set('error_reporting', E_ALL);
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');

        date_default_timezone_set(get_timezone());
    }

    /**
     * Process Payment Gateway
     *
     * @return \Illuminate\Http\Response
     */
    public static function process($deposit) {
        $data                 = array();
        $data['callback_url'] = route('callback.' . $deposit->gateway->slug);
        $data['custom']       = $deposit->id;
        $data['view']         = 'backend.customer.gateway.' . $deposit->gateway->slug;

        return json_encode($data);
    }

    /**
     * Initiate payment with Buni
     *
     * @return \Illuminate\Http\Response
     */
    public function initiate(Request $request) {
        try {
            // Get tenant ID from request or transaction
            $tenantId = $request->tenant->id ?? null;
            
            Log::info('Buni payment initiation started', [
                'request_data' => $request->all(),
                'user_id' => auth()->id(),
                'tenant_id' => $tenantId,
                'tenant_slug' => $request->tenant->slug ?? 'unknown',
                'request_tenant_object' => $request->tenant ? 'present' : 'null'
            ]);

            BuniActivityLogger::logPaymentInitiation($request->deposit_id, $request->amount ?? 0, auth()->id());

            $depositId = $request->deposit_id;
            $transaction = Transaction::find($depositId);

            if (!$transaction) {
                Log::error('Buni payment initiation failed: Transaction not found', [
                    'deposit_id' => $depositId,
                    'user_id' => auth()->id()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            Log::info('Transaction found for Buni payment', [
                'transaction_id' => $transaction->id,
                'member_id' => $transaction->member_id,
                'amount' => $transaction->gateway_amount
            ]);

            // Get tenant ID from transaction if not available in request
            if (!$tenantId) {
                $tenantId = $transaction->branch_id ?? null; // Assuming branch_id contains tenant info
                if (!$tenantId) {
                    // Try to get from user's branch
                    $tenantId = auth()->user()->branch_id ?? null;
                }
            }

            if (!$tenantId) {
                Log::error('Buni payment initiation failed: Tenant ID not found', [
                    'transaction_id' => $transaction->id,
                    'user_id' => auth()->id()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant information not found'
                ], 400);
            }

            // Get Buni configuration (payment gateways are system-wide)
            $buniGateway = DB::table('payment_gateways')
                ->where('slug', 'Buni')
                ->first();

            if (!$buniGateway) {
                Log::error('Buni payment initiation failed: Gateway not configured', [
                    'tenant_id' => $tenantId,
                    'transaction_id' => $transaction->id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Buni gateway not configured'
                ], 400);
            }

            Log::info('Buni gateway configuration found', [
                'gateway_id' => $buniGateway->id,
                'gateway_name' => $buniGateway->name,
                'tenant_id' => $tenantId
            ]);

            $parameters = json_decode($buniGateway->parameters, true);
            $baseUrl = $parameters['buni_base_url'] ?? '';
            $clientId = $parameters['buni_client_id'] ?? '';
            $clientSecret = $parameters['buni_client_secret'] ?? '';

            Log::info('Buni API parameters extracted', [
                'base_url' => $baseUrl,
                'client_id' => $clientId ? '***' . substr($clientId, -4) : 'empty',
                'client_secret' => $clientSecret ? '***' . substr($clientSecret, -4) : 'empty'
            ]);

            if (empty($baseUrl) || empty($clientId) || empty($clientSecret)) {
                Log::error('Buni payment initiation failed: Missing API credentials', [
                    'base_url_empty' => empty($baseUrl),
                    'client_id_empty' => empty($clientId),
                    'client_secret_empty' => empty($clientSecret)
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Buni API credentials not configured properly'
                ], 400);
            }

            // Get access token
            Log::info('Attempting to get Buni access token', [
                'token_url' => $baseUrl . '/oauth/token'
            ]);

            $tokenResponse = Http::post($baseUrl . '/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            Log::info('Buni token response received', [
                'status_code' => $tokenResponse->status(),
                'response_body' => $tokenResponse->body()
            ]);

            if (!$tokenResponse->successful()) {
                Log::error('Buni payment initiation failed: Token authentication failed', [
                    'status_code' => $tokenResponse->status(),
                    'response_body' => $tokenResponse->body()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to authenticate with Buni API'
                ], 400);
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'];

            Log::info('Buni access token obtained successfully');

            // Check if transaction has member
            if (!$transaction->member) {
                Log::error('Buni payment initiation failed: Transaction has no member', [
                    'transaction_id' => $transaction->id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction member not found'
                ], 400);
            }

            Log::info('Transaction member found', [
                'member_id' => $transaction->member->id,
                'member_name' => $transaction->member->first_name . ' ' . $transaction->member->last_name,
                'member_mobile' => $transaction->member->mobile
            ]);

            // Create payment request with Buni
            $paymentData = [
                'amount' => $transaction->gateway_amount,
                'currency' => 'KES',
                'customerReference' => 'DEP-' . $transaction->id . '-' . time(),
                'customerName' => $transaction->member->first_name . ' ' . $transaction->member->last_name,
                'customerMobile' => $transaction->member->mobile,
                'narration' => 'Deposit to IntelliCash Account',
                'callbackUrl' => route('callback.Buni.ipn')
            ];

            Log::info('Buni payment data prepared', [
                'payment_data' => $paymentData
            ]);

            Log::info('Sending payment request to Buni API', [
                'payment_url' => $baseUrl . '/api/v1/request-payment'
            ]);

            $paymentResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($baseUrl . '/api/v1/request-payment', $paymentData);

            Log::info('Buni payment response received', [
                'status_code' => $paymentResponse->status(),
                'response_body' => $paymentResponse->body()
            ]);

            if ($paymentResponse->successful()) {
                $responseData = $paymentResponse->json();
                
                // Store payment reference
                $transaction->transaction_details = json_encode($responseData);
                $transaction->save();

                Log::info('Buni payment initiated successfully', [
                    'transaction_id' => $depositId,
                    'payment_reference' => $responseData['transactionReference'] ?? 'unknown',
                    'response_data' => $responseData
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment initiated successfully',
                    'transaction_id' => $depositId,
                    'payment_reference' => $responseData['transactionReference'] ?? null
                ]);
            } else {
                Log::error('Buni payment initiation failed', [
                    'status_code' => $paymentResponse->status(),
                    'response_body' => $paymentResponse->body(),
                    'transaction_id' => $depositId
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to initiate payment with Buni'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Buni payment initiation error', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'transaction_id' => $depositId ?? 'unknown',
                'user_id' => auth()->id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Callback function from Payment Gateway
     *
     * @return \Illuminate\Http\Response
     */
    public function callback(Request $request) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $transaction = Transaction::find($request->deposit_id);

        if (!$transaction) {
            return redirect()->route('deposit.automatic_methods')->with('error', _lang('Transaction not found!'));
        }

        // Verify the transaction with Buni API
        try {
            $buniBaseUrl = $transaction->gateway->parameters->buni_base_url;
            $clientId = $transaction->gateway->parameters->buni_client_id;
            $clientSecret = $transaction->gateway->parameters->buni_client_secret;

            // Get access token first
            $tokenResponse = Http::post($buniBaseUrl . '/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if (!$tokenResponse->successful()) {
                Log::error('Buni token request failed: ' . $tokenResponse->body());
                return redirect()->route('deposit.automatic_methods')->with('error', _lang('Authentication failed with Buni API'));
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'];

            // Verify transaction
            $verifyResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->get($buniBaseUrl . '/api/v1/transactions/' . $request->transaction_reference);

            if (!$verifyResponse->successful()) {
                Log::error('Buni transaction verification failed: ' . $verifyResponse->body());
                return redirect()->route('deposit.automatic_methods')->with('error', _lang('Transaction verification failed'));
            }

            $transactionData = $verifyResponse->json();

            // Check if transaction is successful
            if ($transactionData['status'] === 'success' || $transactionData['status'] === 'completed') {
                $amount = $transactionData['amount'];

                // Update Transaction
                if (round($transaction->gateway_amount) <= round($amount)) {
                    $transaction->status = 2; // Completed
                    $transaction->transaction_details = json_encode($transactionData);
                    $transaction->save();
                }

                // Trigger Deposit Money notifications
                try {
                    $transaction->member->notify(new DepositMoney($transaction));
                    $transaction->member->notify(new BuniTransactionNotification($transaction, 'deposit'));
                } catch (\Exception $e) {
                    Log::error('Deposit notification failed: ' . $e->getMessage());
                }

                return redirect()->route('dashboard.index')->with('success', _lang('Money Deposited Successfully'));
            } else {
                return redirect()->route('deposit.automatic_methods')->with('error', _lang('Transaction not completed'));
            }

        } catch (\Exception $e) {
            Log::error('Buni payment processing error: ' . $e->getMessage());
            return redirect()->route('deposit.automatic_methods')->with('error', _lang('Payment processing failed'));
        }
    }

    /**
     * Handle IPN (Instant Payment Notification) from Buni
     *
     * @return \Illuminate\Http\Response
     */
    public function ipn(Request $request) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        // Log the incoming IPN request
        Log::info('Buni IPN received: ' . json_encode($request->all()));

        // Validate the signature (implement signature validation based on Buni documentation)
        $signature = $request->header('Signature');
        if (!$this->validateSignature($request, $signature)) {
            Log::error('Invalid Buni IPN signature');
            return response()->json([
                'transactionID' => $request->transactionReference ?? '',
                'statusCode' => 1,
                'statusMessage' => 'Invalid signature'
            ], 400);
        }

        try {
            // Find transaction by customer reference
            $transaction = Transaction::where('transaction_details', 'like', '%' . $request->customerReference . '%')
                ->where('status', 0) // Pending
                ->first();

            if (!$transaction) {
                Log::error('Transaction not found for customer reference: ' . $request->customerReference);
                return response()->json([
                    'transactionID' => $request->transactionReference ?? '',
                    'statusCode' => 1,
                    'statusMessage' => 'Transaction not found'
                ], 404);
            }

            // Verify amount matches
            $expectedAmount = $transaction->gateway_amount;
            $receivedAmount = floatval($request->transactionAmount);

            if (round($expectedAmount) !== round($receivedAmount)) {
                Log::error('Amount mismatch. Expected: ' . $expectedAmount . ', Received: ' . $receivedAmount);
                return response()->json([
                    'transactionID' => $request->transactionReference ?? '',
                    'statusCode' => 1,
                    'statusMessage' => 'Amount mismatch'
                ], 400);
            }

            // Update transaction status
            $transaction->status = 2; // Completed
            $transaction->transaction_details = json_encode($request->all());
            $transaction->save();

            // Trigger Deposit Money notifications
            try {
                $transaction->member->notify(new DepositMoney($transaction));
            } catch (\Exception $e) {
                Log::error('Deposit notification failed: ' . $e->getMessage());
            }

            Log::info('Buni IPN processed successfully for transaction: ' . $transaction->id);

            return response()->json([
                'transactionID' => $request->transactionReference,
                'statusCode' => 0,
                'statusMessage' => 'Notification received'
            ]);

        } catch (\Exception $e) {
            Log::error('Buni IPN processing error: ' . $e->getMessage());
            return response()->json([
                'transactionID' => $request->transactionReference ?? '',
                'statusCode' => 1,
                'statusMessage' => 'Processing error'
            ], 500);
        }
    }

    /**
     * Validate Buni signature
     *
     * @param Request $request
     * @param string $signature
     * @return bool
     */
    private function validateSignature(Request $request, $signature) {
        // Implement signature validation based on Buni documentation
        // This is a placeholder - implement actual signature validation
        // based on the shared secret from Buni
        
        // For now, we'll accept all requests (implement proper validation in production)
        return true;
    }
}
