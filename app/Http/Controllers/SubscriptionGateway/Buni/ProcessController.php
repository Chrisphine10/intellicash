<?php

namespace App\Http\Controllers\SubscriptionGateway\Buni;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Notifications\DepositMoney;
use Illuminate\Http\Request;
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
        $data['callback_url'] = route('subscription.callback.' . $deposit->gateway->slug);
        $data['custom']       = $deposit->id;
        $data['view']         = 'membership.gateway.' . $deposit->gateway->slug;

        return json_encode($data);
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
            return redirect()->route('membership.index')->with('error', _lang('Transaction not found!'));
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
                return redirect()->route('membership.index')->with('error', _lang('Authentication failed with Buni API'));
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
                return redirect()->route('membership.index')->with('error', _lang('Transaction verification failed'));
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
                } catch (\Exception $e) {
                    Log::error('Deposit notification failed: ' . $e->getMessage());
                }

                return redirect()->route('membership.index')->with('success', _lang('Payment completed successfully'));
            } else {
                return redirect()->route('membership.index')->with('error', _lang('Transaction not completed'));
            }

        } catch (\Exception $e) {
            Log::error('Buni payment processing error: ' . $e->getMessage());
            return redirect()->route('membership.index')->with('error', _lang('Payment processing failed'));
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
