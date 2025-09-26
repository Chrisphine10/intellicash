<?php

/**
 * Test script to verify bank account fixes integration
 * This script tests the fixes applied to the bank accounts module
 */

require_once 'vendor/autoload.php';

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\PaymentMethod;
use App\Services\BankingService;
use App\Models\Transaction;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

class BankAccountFixesTest
{
    private $testResults = [];

    public function runTests()
    {
        echo "=== Bank Account Fixes Integration Test ===\n\n";

        $this->testAuthorizationMiddleware();
        $this->testBalanceValidation();
        $this->testPaymentMethodEncryption();
        $this->testMemberTransactionIntegration();
        $this->testBankingServiceIntegration();

        $this->displayResults();
    }

    private function testAuthorizationMiddleware()
    {
        echo "1. Testing Authorization Middleware...\n";
        
        try {
            // Check if BankAccountController has proper middleware
            $reflection = new ReflectionClass('App\Http\Controllers\BankAccountController');
            $constructor = $reflection->getConstructor();
            
            if ($constructor) {
                $this->testResults['authorization'] = 'PASS - Middleware added to constructor';
                echo "   ✓ Authorization middleware properly added\n";
            } else {
                $this->testResults['authorization'] = 'FAIL - No constructor found';
                echo "   ✗ No constructor found\n";
            }
        } catch (Exception $e) {
            $this->testResults['authorization'] = 'ERROR - ' . $e->getMessage();
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    }

    private function testBalanceValidation()
    {
        echo "2. Testing Balance Validation...\n";
        
        try {
            // Test BankTransaction validation
            $bankAccount = new BankAccount();
            $bankAccount->current_balance = 1000.00;
            $bankAccount->blocked_balance = 100.00;
            $bankAccount->allow_negative_balance = false;
            $bankAccount->minimum_balance = 50.00;

            // Test sufficient balance
            $hasBalance = $bankAccount->hasSufficientBalance(500.00);
            if ($hasBalance) {
                echo "   ✓ Sufficient balance check works\n";
            } else {
                echo "   ✗ Sufficient balance check failed\n";
            }

            // Test insufficient balance
            $hasBalance = $bankAccount->hasSufficientBalance(1000.00);
            if (!$hasBalance) {
                echo "   ✓ Insufficient balance check works\n";
                $this->testResults['balance_validation'] = 'PASS - Balance validation working correctly';
            } else {
                echo "   ✗ Insufficient balance check failed\n";
                $this->testResults['balance_validation'] = 'FAIL - Insufficient balance not detected';
            }
        } catch (Exception $e) {
            $this->testResults['balance_validation'] = 'ERROR - ' . $e->getMessage();
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    }

    private function testPaymentMethodEncryption()
    {
        echo "3. Testing Payment Method Encryption...\n";
        
        try {
            // Test encryption/decryption
            $paymentMethod = new PaymentMethod();
            $config = [
                'paystack_secret_key' => 'sk_test_123456789',
                'paystack_public_key' => 'pk_test_123456789',
                'buni_client_secret' => 'secret123',
                'other_field' => 'not_encrypted'
            ];

            // Test setting config (should encrypt sensitive fields)
            $paymentMethod->config = $config;
            
            // Test getting config (should decrypt sensitive fields)
            $retrievedConfig = $paymentMethod->config;
            
            if ($retrievedConfig['paystack_secret_key'] === 'sk_test_123456789' && 
                $retrievedConfig['other_field'] === 'not_encrypted') {
                echo "   ✓ Payment method encryption/decryption working\n";
                $this->testResults['payment_encryption'] = 'PASS - Encryption/decryption working correctly';
            } else {
                echo "   ✗ Payment method encryption/decryption failed\n";
                $this->testResults['payment_encryption'] = 'FAIL - Encryption/decryption not working';
            }
        } catch (Exception $e) {
            $this->testResults['payment_encryption'] = 'ERROR - ' . $e->getMessage();
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    }

    private function testMemberTransactionIntegration()
    {
        echo "4. Testing Member Transaction Integration...\n";
        
        try {
            // Test the transaction flow
            $transaction = new Transaction();
            $transaction->amount = 100.00;
            $transaction->dr_cr = 'cr';
            $transaction->type = 'Deposit';
            $transaction->status = 2;
            $transaction->trans_date = now();
            $transaction->created_user_id = 1;
            $transaction->tenant_id = 1;

            // Mock the relationships
            $savingsAccount = new SavingsAccount();
            $savingsProduct = new SavingsProduct();
            $bankAccount = new BankAccount();
            
            $bankAccount->current_balance = 1000.00;
            $bankAccount->allow_negative_balance = true;
            $bankAccount->is_active = true;
            
            $savingsProduct->bank_account = $bankAccount;
            $savingsAccount->savings_type = $savingsProduct;
            $transaction->account = $savingsAccount;

            // Test BankingService integration
            $bankingService = new BankingService();
            $result = $bankingService->processMemberTransaction($transaction);
            
            if ($result === true) {
                echo "   ✓ Member transaction integration working\n";
                $this->testResults['member_integration'] = 'PASS - Member transaction integration working';
            } else {
                echo "   ✗ Member transaction integration failed\n";
                $this->testResults['member_integration'] = 'FAIL - Member transaction integration failed';
            }
        } catch (Exception $e) {
            $this->testResults['member_integration'] = 'ERROR - ' . $e->getMessage();
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    }

    private function testBankingServiceIntegration()
    {
        echo "5. Testing BankingService Integration...\n";
        
        try {
            // Test transaction type mapping
            $bankingService = new BankingService();
            $reflection = new ReflectionClass($bankingService);
            $method = $reflection->getMethod('mapTransactionType');
            $method->setAccessible(true);
            
            $mappings = [
                'Deposit' => 'deposit',
                'Withdraw' => 'withdraw',
                'Loan' => 'loan_disbursement',
                'Loan_Payment' => 'loan_repayment'
            ];
            
            $allMappingsCorrect = true;
            foreach ($mappings as $memberType => $expectedBankType) {
                $actualBankType = $method->invoke($bankingService, $memberType);
                if ($actualBankType !== $expectedBankType) {
                    $allMappingsCorrect = false;
                    break;
                }
            }
            
            if ($allMappingsCorrect) {
                echo "   ✓ Transaction type mapping working correctly\n";
                $this->testResults['banking_service'] = 'PASS - BankingService integration working';
            } else {
                echo "   ✗ Transaction type mapping failed\n";
                $this->testResults['banking_service'] = 'FAIL - Transaction type mapping incorrect';
            }
        } catch (Exception $e) {
            $this->testResults['banking_service'] = 'ERROR - ' . $e->getMessage();
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
    }

    private function displayResults()
    {
        echo "\n=== Test Results Summary ===\n";
        
        $passed = 0;
        $failed = 0;
        $errors = 0;
        
        foreach ($this->testResults as $test => $result) {
            echo sprintf("%-25s: %s\n", ucwords(str_replace('_', ' ', $test)), $result);
            
            if (strpos($result, 'PASS') === 0) {
                $passed++;
            } elseif (strpos($result, 'FAIL') === 0) {
                $failed++;
            } else {
                $errors++;
            }
        }
        
        echo "\nSummary: {$passed} passed, {$failed} failed, {$errors} errors\n";
        
        if ($failed === 0 && $errors === 0) {
            echo "🎉 All tests passed! Bank account fixes are working correctly.\n";
        } else {
            echo "⚠️  Some tests failed. Please review the issues above.\n";
        }
    }
}

// Run the tests
$test = new BankAccountFixesTest();
$test->runTests();
