<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Services\PaymentMethodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BankAccountPaymentController extends Controller
{
    protected $paymentMethodService;

    public function __construct(PaymentMethodService $paymentMethodService)
    {
        $this->paymentMethodService = $paymentMethodService;
    }

    /**
     * Show payment method connection form
     */
    public function showConnectionForm($bankAccountId)
    {
        $bankAccount = BankAccount::findOrFail($bankAccountId);
        
        // Check if user has permission to manage this bank account
        if ($bankAccount->tenant_id !== request()->tenant->id) {
            abort(403, 'Unauthorized access to bank account');
        }

        return view('backend.admin.bank_account.payment_connection', compact('bankAccount'));
    }

    /**
     * Connect payment method to bank account
     */
    public function connectPaymentMethod(Request $request, $bankAccountId)
    {
        $bankAccount = BankAccount::findOrFail($bankAccountId);
        
        // Check if user has permission to manage this bank account
        if ($bankAccount->tenant_id !== request()->tenant->id) {
            abort(403, 'Unauthorized access to bank account');
        }

        $validator = Validator::make($request->all(), [
            'payment_method_type' => 'required|in:paystack,buni,manual',
            'payment_reference' => 'nullable|string|max:255',
            'config' => 'required|array'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $this->paymentMethodService->connectPaymentMethod(
                $bankAccount,
                $request->payment_method_type,
                $request->config,
                $request->payment_reference
            );

            return redirect()->route('bank_accounts.index')
                ->with('success', 'Payment method connected successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to connect payment method: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Disconnect payment method from bank account
     */
    public function disconnectPaymentMethod($bankAccountId)
    {
        $bankAccount = BankAccount::findOrFail($bankAccountId);
        
        // Check if user has permission to manage this bank account
        if ($bankAccount->tenant_id !== request()->tenant->id) {
            abort(403, 'Unauthorized access to bank account');
        }

        try {
            $bankAccount->disconnectPaymentMethod();

            return redirect()->route('bank_accounts.index')
                ->with('success', 'Payment method disconnected successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to disconnect payment method: ' . $e->getMessage());
        }
    }

    /**
     * Get payment method configuration form
     */
    public function getPaymentConfigForm(Request $request)
    {
        $paymentType = $request->input('payment_type');
        
        $configFields = $this->getPaymentConfigFields($paymentType);
        
        return view('backend.admin.bank_account.partials.payment_config_form', [
            'paymentType' => $paymentType,
            'configFields' => $configFields
        ]);
    }

    /**
     * Get configuration fields for payment method type
     */
    private function getPaymentConfigFields($paymentType)
    {
        switch ($paymentType) {
            case 'paystack':
                return [
                    'paystack_secret_key' => [
                        'label' => 'Paystack Secret Key',
                        'type' => 'password',
                        'required' => true,
                        'help' => 'Your Paystack secret key from the dashboard'
                    ],
                    'paystack_public_key' => [
                        'label' => 'Paystack Public Key',
                        'type' => 'text',
                        'required' => false,
                        'help' => 'Your Paystack public key (optional)'
                    ]
                ];
            case 'buni':
                return [
                    'buni_base_url' => [
                        'label' => 'Buni Base URL',
                        'type' => 'url',
                        'required' => true,
                        'help' => 'The base URL for Buni API'
                    ],
                    'buni_client_id' => [
                        'label' => 'Client ID',
                        'type' => 'text',
                        'required' => true,
                        'help' => 'Your Buni client ID'
                    ],
                    'buni_client_secret' => [
                        'label' => 'Client Secret',
                        'type' => 'password',
                        'required' => true,
                        'help' => 'Your Buni client secret'
                    ],
                    'company_code' => [
                        'label' => 'Company Code',
                        'type' => 'text',
                        'required' => false,
                        'help' => 'Your company code (default: KE0010001)',
                        'default' => 'KE0010001'
                    ]
                ];
            case 'manual':
                return [
                    'processing_instructions' => [
                        'label' => 'Processing Instructions',
                        'type' => 'textarea',
                        'required' => false,
                        'help' => 'Instructions for manual processing'
                    ]
                ];
            default:
                return [];
        }
    }

    /**
     * Test payment method connection
     */
    public function testConnection(Request $request, $bankAccountId)
    {
        $bankAccount = BankAccount::findOrFail($bankAccountId);
        
        // Check if user has permission to manage this bank account
        if ($bankAccount->tenant_id !== request()->tenant->id) {
            abort(403, 'Unauthorized access to bank account');
        }

        $validator = Validator::make($request->all(), [
            'payment_method_type' => 'required|in:paystack,buni,manual',
            'config' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid configuration data'
            ]);
        }

        try {
            // Test the connection without saving
            $this->testPaymentMethodConnection($request->payment_method_type, $request->config);
            
            return response()->json([
                'success' => true,
                'message' => 'Connection test successful'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Test payment method connection without saving
     */
    private function testPaymentMethodConnection($paymentType, $config)
    {
        switch ($paymentType) {
            case 'paystack':
                $this->testPaystackConnection($config);
                break;
            case 'buni':
                $this->testBuniConnection($config);
                break;
            case 'manual':
                // Manual doesn't need testing
                break;
            default:
                throw new \Exception('Unsupported payment method type');
        }
    }

    /**
     * Test Paystack connection
     */
    private function testPaystackConnection($config)
    {
        $secretKey = $config['paystack_secret_key'] ?? null;
        if (!$secretKey) {
            throw new \Exception('Paystack secret key is required');
        }

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . $secretKey,
            'Content-Type' => 'application/json',
        ])->get('https://api.paystack.co/balance');

        if (!$response->successful()) {
            throw new \Exception('Failed to connect to Paystack API');
        }
    }

    /**
     * Test Buni connection
     */
    private function testBuniConnection($config)
    {
        $baseUrl = $config['buni_base_url'] ?? null;
        $clientId = $config['buni_client_id'] ?? null;
        $clientSecret = $config['buni_client_secret'] ?? null;

        if (!$baseUrl || !$clientId || !$clientSecret) {
            throw new \Exception('Buni configuration incomplete');
        }

        $response = \Illuminate\Support\Facades\Http::post($baseUrl . '/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to authenticate with Buni API');
        }
    }
}
