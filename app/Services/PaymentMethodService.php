<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\BankAccount;
use App\Models\WithdrawRequest;
use App\Models\Transaction;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentMethodService
{
    /**
     * Create a new payment method
     */
    public function createPaymentMethod($name, $type, $currencyId, $config = [], $description = null, $tenantId = null)
    {
        try {
            // Validate payment method type
            if (!in_array($type, ['paystack', 'buni', 'manual'])) {
                throw new \InvalidArgumentException('Invalid payment method type');
            }

            // Validate configuration based on payment type
            $this->validatePaymentConfig($type, $config);

            // Create the payment method
            $paymentMethod = PaymentMethod::create([
                'tenant_id' => $tenantId ?? request()->tenant->id,
                'name' => $name,
                'type' => $type,
                'currency_id' => $currencyId,
                'config' => $config,
                'description' => $description,
                'is_active' => true
            ]);

            Log::info('Payment method created', [
                'payment_method_id' => $paymentMethod->id,
                'payment_type' => $type,
                'tenant_id' => $paymentMethod->tenant_id
            ]);

            return $paymentMethod;
        } catch (\Exception $e) {
            Log::error('Failed to create payment method', [
                'payment_type' => $type,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Process withdrawal through payment method
     */
    public function processWithdrawal(WithdrawRequest $withdrawRequest, $recipientDetails)
    {
        // Get payment method from transaction or requirements
        $requirements = json_decode($withdrawRequest->requirements, true);
        $paymentMethodId = $requirements['payment_method_id'] ?? null;
        
        if (!$paymentMethodId) {
            throw new \Exception('No payment method specified for withdrawal');
        }

        $paymentMethod = PaymentMethod::where('tenant_id', $withdrawRequest->member->tenant_id)
            ->where('is_active', true)
            ->find($paymentMethodId);

        if (!$paymentMethod) {
            throw new \Exception('Payment method not found or inactive');
        }

        $config = $paymentMethod->config;

        switch ($paymentMethod->type) {
            case 'paystack':
                return $this->processPaystackWithdrawal($withdrawRequest, $paymentMethod, $config, $recipientDetails);
            case 'buni':
                return $this->processBuniWithdrawal($withdrawRequest, $paymentMethod, $config, $recipientDetails);
            case 'manual':
                return $this->processManualWithdrawal($withdrawRequest, $paymentMethod, $config, $recipientDetails);
            default:
                throw new \Exception('Unsupported payment method type');
        }
    }

    /**
     * Get bank account for withdrawal processing
     */
    private function getBankAccountForWithdrawal(WithdrawRequest $withdrawRequest)
    {
        // Get the first active bank account for the tenant
        return BankAccount::where('tenant_id', $withdrawRequest->member->tenant_id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Process Paystack withdrawal
     */
    private function processPaystackWithdrawal(WithdrawRequest $withdrawRequest, PaymentMethod $paymentMethod, $config, $recipientDetails)
    {
        try {
            $secretKey = $config['paystack_secret_key'] ?? null;
            if (!$secretKey) {
                throw new \Exception('Paystack secret key not configured');
            }

            // Create transfer recipient
            $recipientData = [
                'type' => 'mobile_money',
                'name' => $recipientDetails['name'] ?? 'Member Withdrawal',
                'account_number' => $recipientDetails['mobile'] ?? $recipientDetails['account_number'],
                'bank_code' => $recipientDetails['bank_code'] ?? 'MPESA',
                'currency' => $bankAccount->currency->name ?? 'KES'
            ];

            $recipientResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.paystack.co/transferrecipient', $recipientData);

            if (!$recipientResponse->successful()) {
                throw new \Exception('Failed to create transfer recipient: ' . $recipientResponse->body());
            }

            $recipient = $recipientResponse->json();

            // Initiate transfer
            $transferData = [
                'source' => 'balance',
                'amount' => $withdrawRequest->amount * 100, // Convert to kobo
                'recipient' => $recipient['data']['recipient_code'],
                'reason' => 'Member withdrawal via IntelliCash'
            ];

            $transferResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.paystack.co/transfer', $transferData);

            if ($transferResponse->successful()) {
                $responseData = $transferResponse->json();
                
                // Update transaction with Paystack details
                $this->updateTransactionWithPaymentDetails($withdrawRequest, $responseData, 'paystack');
                
                return [
                    'success' => true,
                    'data' => $responseData,
                    'message' => 'Withdrawal processed successfully via Paystack'
                ];
            } else {
                $errorData = $transferResponse->json();
                throw new \Exception('Paystack transfer failed: ' . ($errorData['message'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            Log::error('Paystack withdrawal failed', [
                'withdraw_request_id' => $withdrawRequest->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Process Buni withdrawal
     */
    private function processBuniWithdrawal(WithdrawRequest $withdrawRequest, PaymentMethod $paymentMethod, $config, $recipientDetails)
    {
        try {
            $baseUrl = $config['buni_base_url'] ?? null;
            $clientId = $config['buni_client_id'] ?? null;
            $clientSecret = $config['buni_client_secret'] ?? null;

            if (!$baseUrl || !$clientId || !$clientSecret) {
                throw new \Exception('Buni configuration incomplete');
            }

            // Get access token
            $tokenResponse = Http::post($baseUrl . '/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if (!$tokenResponse->successful()) {
                throw new \Exception('Failed to authenticate with Buni API');
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'];

            // Prepare transfer data
            $transferData = [
                'companyCode' => $config['company_code'] ?? 'KE0010001',
                'transactionType' => 'IF', // Interbank Funds Transfer
                'debitAccountNumber' => $recipientDetails['debit_account_number'] ?? '0000000000',
                'creditAccountNumber' => $recipientDetails['account_number'],
                'debitAmount' => $withdrawRequest->amount,
                'paymentDetails' => 'Member withdrawal via IntelliCash',
                'transactionReference' => 'TXN-' . $withdrawRequest->id . '-' . time(),
                'currency' => $paymentMethod->currency->name ?? 'KES',
                'beneficiaryDetails' => $recipientDetails['name'] ?? 'Member Withdrawal',
                'beneficiaryBankCode' => $recipientDetails['bank_code'] ?? '001'
            ];

            // Send transfer request
            $transferResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($baseUrl . '/api/v1/transfer', $transferData);

            if ($transferResponse->successful()) {
                $responseData = $transferResponse->json();
                
                // Update transaction with Buni details
                $this->updateTransactionWithPaymentDetails($withdrawRequest, $responseData, 'buni');
                
                return [
                    'success' => true,
                    'data' => $responseData,
                    'message' => 'Withdrawal processed successfully via Buni'
                ];
            } else {
                $errorData = $transferResponse->json();
                throw new \Exception('Buni transfer failed: ' . ($errorData['message'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            Log::error('Buni withdrawal failed', [
                'withdraw_request_id' => $withdrawRequest->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Process manual withdrawal (for admin processing)
     */
    private function processManualWithdrawal(WithdrawRequest $withdrawRequest, PaymentMethod $paymentMethod, $config, $recipientDetails)
    {
        // For manual withdrawals, just mark as pending for admin review
        $withdrawRequest->update([
            'status' => 0, // Pending
            'requirements' => json_encode(array_merge(
                json_decode($withdrawRequest->requirements, true) ?? [],
                $recipientDetails
            ))
        ]);

        return [
            'success' => true,
            'data' => ['status' => 'pending'],
            'message' => 'Withdrawal request submitted for manual processing'
        ];
    }

    /**
     * Update transaction with payment method details
     */
    private function updateTransactionWithPaymentDetails(WithdrawRequest $withdrawRequest, $paymentData, $method)
    {
        $transaction = $withdrawRequest->transaction;
        if ($transaction) {
            $transaction->update([
                'status' => 2, // Completed
                'transaction_details' => json_encode($paymentData),
                'method' => ucfirst($method)
            ]);
        }

        $withdrawRequest->update([
            'status' => 2, // Completed
            'api_response' => json_encode($paymentData)
        ]);
    }

    /**
     * Validate payment method configuration
     */
    private function validatePaymentConfig($paymentType, $config)
    {
        switch ($paymentType) {
            case 'paystack':
                if (empty($config['paystack_secret_key'])) {
                    throw new \InvalidArgumentException('Paystack secret key is required');
                }
                break;
            case 'buni':
                $required = ['buni_base_url', 'buni_client_id', 'buni_client_secret'];
                foreach ($required as $field) {
                    if (empty($config[$field])) {
                        throw new \InvalidArgumentException("Buni {$field} is required");
                    }
                }
                break;
            case 'manual':
                // No specific validation needed for manual
                break;
        }
    }

    /**
     * Get available payment methods for a tenant
     */
    public function getAvailablePaymentMethods($tenantId)
    {
        return PaymentMethod::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->map(function ($paymentMethod) {
                return [
                    'id' => $paymentMethod->id,
                    'name' => $paymentMethod->name,
                    'type' => $paymentMethod->type,
                    'currency' => $paymentMethod->currency->name ?? 'KES',
                    'display_name' => $paymentMethod->display_name
                ];
            });
    }
}
