<?php

namespace App\Services;

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
     * Connect a bank account to a payment method
     */
    public function connectPaymentMethod(BankAccount $bankAccount, $paymentType, $config = [], $reference = null)
    {
        try {
            // Validate payment method type
            if (!in_array($paymentType, ['paystack', 'buni', 'manual'])) {
                throw new \InvalidArgumentException('Invalid payment method type');
            }

            // Validate configuration based on payment type
            $this->validatePaymentConfig($paymentType, $config);

            // Connect the payment method
            $bankAccount->connectPaymentMethod($paymentType, $config, $reference);

            Log::info('Payment method connected', [
                'bank_account_id' => $bankAccount->id,
                'payment_type' => $paymentType,
                'tenant_id' => $bankAccount->tenant_id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to connect payment method', [
                'bank_account_id' => $bankAccount->id,
                'payment_type' => $paymentType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Process withdrawal through connected payment method
     */
    public function processWithdrawal(WithdrawRequest $withdrawRequest, $recipientDetails)
    {
        $bankAccount = $this->getBankAccountForWithdrawal($withdrawRequest);
        
        if (!$bankAccount || !$bankAccount->hasPaymentMethod()) {
            throw new \Exception('No payment method connected to bank account');
        }

        $paymentType = $bankAccount->payment_method_type;
        $config = $bankAccount->getPaymentConfig();

        switch ($paymentType) {
            case 'paystack':
                return $this->processPaystackWithdrawal($withdrawRequest, $bankAccount, $config, $recipientDetails);
            case 'buni':
                return $this->processBuniWithdrawal($withdrawRequest, $bankAccount, $config, $recipientDetails);
            case 'manual':
                return $this->processManualWithdrawal($withdrawRequest, $bankAccount, $config, $recipientDetails);
            default:
                throw new \Exception('Unsupported payment method type');
        }
    }

    /**
     * Get bank account for withdrawal processing
     */
    private function getBankAccountForWithdrawal(WithdrawRequest $withdrawRequest)
    {
        // Get the first bank account with payment method enabled for the tenant
        return BankAccount::withPaymentMethods()
            ->where('tenant_id', $withdrawRequest->member->tenant_id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Process Paystack withdrawal
     */
    private function processPaystackWithdrawal(WithdrawRequest $withdrawRequest, BankAccount $bankAccount, $config, $recipientDetails)
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
    private function processBuniWithdrawal(WithdrawRequest $withdrawRequest, BankAccount $bankAccount, $config, $recipientDetails)
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
                'debitAccountNumber' => $bankAccount->account_number,
                'creditAccountNumber' => $recipientDetails['account_number'],
                'debitAmount' => $withdrawRequest->amount,
                'paymentDetails' => 'Member withdrawal via IntelliCash',
                'transactionReference' => 'TXN-' . $withdrawRequest->id . '-' . time(),
                'currency' => $bankAccount->currency->name ?? 'KES',
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
    private function processManualWithdrawal(WithdrawRequest $withdrawRequest, BankAccount $bankAccount, $config, $recipientDetails)
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
        return BankAccount::withPaymentMethods()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->bank_name . ' - ' . $account->account_name,
                    'type' => $account->payment_method_type,
                    'currency' => $account->currency->name ?? 'KES',
                    'available_balance' => $account->available_balance
                ];
            });
    }
}
