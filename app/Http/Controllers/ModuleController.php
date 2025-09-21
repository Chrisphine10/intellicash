<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\VslaSetting;
use App\Models\SavingsProduct;
use App\Models\LoanProduct;
use App\Models\BankAccount;
use App\Models\QrCodeSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModuleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Check permission - only admin can view modules
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        // Check if qr_code_settings table exists
        $qrCodeSettings = null;
        try {
            if (Schema::hasTable('qr_code_settings')) {
                $qrCodeSettings = QrCodeSetting::where('tenant_id', $tenant->id)->first();
            }
        } catch (\Exception $e) {
            // Table doesn't exist, continue with null settings
        }
        
        $modules = [
            'vsla' => [
                'name' => 'VSLA Module',
                'description' => 'Village Savings and Loan Association management',
                'enabled' => $tenant->isVslaEnabled(),
            ],
            'api' => [
                'name' => 'API Module',
                'description' => 'RESTful API for system integration',
                'enabled' => $tenant->api_enabled ?? false,
            ],
            'qr_code' => [
                'name' => 'QR Code Module',
                'description' => 'QR code generation and verification for receipts',
                'enabled' => $qrCodeSettings ? $qrCodeSettings->enabled : false,
                'configured' => $qrCodeSettings ? $qrCodeSettings->isFullyConfigured() : false,
                'ethereum_enabled' => $qrCodeSettings ? $qrCodeSettings->ethereum_enabled : false,
            ],
            'advanced_loan_management' => [
                'name' => 'Advanced Loan Management',
                'description' => 'Comprehensive business loan management with collateral support',
                'enabled' => $tenant->advanced_loan_management_enabled ?? true, // Default enabled since it's already implemented
            ]
        ];
        
        return view('backend.admin.modules.index', compact('modules'));
    }

    /**
     * Toggle VSLA module status
     */
    public function toggleVsla(Request $request)
    {
        // Check permission - only admin can toggle modules
        if (!is_admin()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Permission denied!')]);
            }
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        $enabled = $request->boolean('enabled');
        
        // Check if already in the desired state
        if ($tenant->vsla_enabled == $enabled) {
            $message = $enabled ? 'VSLA module is already enabled' : 'VSLA module is already disabled';
            
            if ($request->ajax()) {
                return response()->json(['result' => 'info', 'message' => _lang($message)]);
            }
            
            return back()->with('info', _lang($message));
        }
        
        DB::beginTransaction();
        
        try {
            $tenant->vsla_enabled = $enabled;
            $tenant->save();
            
            if ($enabled) {
                $this->provisionVslaModule($tenant);
            }
            
            DB::commit();
            
            $message = $enabled ? 'VSLA module activated successfully' : 'VSLA module deactivated successfully';
            
            if ($request->ajax()) {
                return response()->json(['result' => 'success', 'message' => _lang($message)]);
            }
            
            return back()->with('success', _lang($message));
            
        } catch (\Exception $e) {
            DB::rollback();
            
            \Log::error('VSLA Module Toggle Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('An error occurred while updating the module: ') . $e->getMessage()]);
            }
            
            return back()->with('error', _lang('An error occurred while updating the module: ') . $e->getMessage());
        }
    }
    
    /**
     * Provision VSLA module when activated
     */
    private function provisionVslaModule($tenant)
    {
        try {
            // Create VSLA settings if not exists
            if (!$tenant->vslaSettings) {
                VslaSetting::create([
                    'tenant_id' => $tenant->id,
                    'share_amount' => 0,
                    'penalty_amount' => 0,
                    'welfare_amount' => 0,
                    'meeting_frequency' => 'weekly',
                    'meeting_day_of_week' => null,
                    'meeting_time' => '10:00:00',
                    'chairperson_role' => 'Chairperson',
                    'treasurer_role' => 'Treasurer',
                    'secretary_role' => 'Secretary',
                    'auto_approve_loans' => false,
                    'max_loan_amount' => null,
                    'max_loan_duration_days' => null,
                ]);
            }
            
            // Create VSLA accounts if they don't exist
            $this->createVslaAccounts($tenant);
            
            // Create default VSLA loan product if it doesn't exist
            $this->createVslaLoanProduct($tenant);
            
        } catch (\Exception $e) {
            \Log::error('VSLA Module Provision Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
    
    /**
     * Create VSLA specific accounts
     */
    private function createVslaAccounts($tenant)
    {
        $currency = \App\Models\Currency::where('tenant_id', $tenant->id)->first();
        
        if (!$currency) {
            \Log::error('No currency found for tenant: ' . $tenant->id);
            throw new \Exception('No currency found for this tenant. Please add a currency first.');
        }
        
        // Share Account
        $shareAccount = BankAccount::where('tenant_id', $tenant->id)
            ->where('account_name', 'VSLA Share Account')
            ->first();
            
        if (!$shareAccount) {
            try {
                BankAccount::create([
                    'tenant_id' => $tenant->id,
                    'opening_date' => now(),
                    'bank_name' => 'VSLA Internal',
                    'currency_id' => $currency->id,
                    'account_name' => 'VSLA Share Account',
                    'account_number' => 'VSLA-SHARE-' . $tenant->id,
                    'opening_balance' => 0,
                    'description' => 'VSLA member share contributions',
                ]);
            } catch (\Exception $e) {
                \Log::warning('VSLA Share Account already exists or creation failed: ' . $e->getMessage());
            }
        }
        
        // Loan Fund Account
        $loanFundAccount = BankAccount::where('tenant_id', $tenant->id)
            ->where('account_name', 'VSLA Loan Fund Account')
            ->first();
            
        if (!$loanFundAccount) {
            try {
                BankAccount::create([
                    'tenant_id' => $tenant->id,
                    'opening_date' => now(),
                    'bank_name' => 'VSLA Internal',
                    'currency_id' => $currency->id,
                    'account_name' => 'VSLA Loan Fund Account',
                    'account_number' => 'VSLA-LOAN-' . $tenant->id,
                    'opening_balance' => 0,
                    'description' => 'VSLA loan fund for member loans',
                ]);
            } catch (\Exception $e) {
                \Log::warning('VSLA Loan Fund Account already exists or creation failed: ' . $e->getMessage());
            }
        }
        
        // Welfare/Penalty Account
        $welfareAccount = BankAccount::where('tenant_id', $tenant->id)
            ->where('account_name', 'VSLA Welfare Account')
            ->first();
            
        if (!$welfareAccount) {
            try {
                BankAccount::create([
                    'tenant_id' => $tenant->id,
                    'opening_date' => now(),
                    'bank_name' => 'VSLA Internal',
                    'currency_id' => $currency->id,
                    'account_name' => 'VSLA Welfare Account',
                    'account_number' => 'VSLA-WELFARE-' . $tenant->id,
                    'opening_balance' => 0,
                    'description' => 'VSLA welfare and penalty contributions',
                ]);
            } catch (\Exception $e) {
                \Log::warning('VSLA Welfare Account already exists or creation failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Create default VSLA loan product
     */
    private function createVslaLoanProduct($tenant)
    {
        $currency = \App\Models\Currency::where('tenant_id', $tenant->id)->first();
        
        if (!$currency) {
            \Log::error('No currency found for tenant when creating loan product: ' . $tenant->id);
            throw new \Exception('No currency found for this tenant. Please add a currency first.');
        }
        
        $loanProduct = LoanProduct::where('tenant_id', $tenant->id)
            ->where('name', 'VSLA Default Loan Product')
            ->first();
            
        if (!$loanProduct) {
            try {
                LoanProduct::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'VSLA Default Loan Product',
                    'loan_id_prefix' => 'VSLALI-',
                    'starting_loan_id' => 1,
                    'minimum_amount' => 0,
                    'maximum_amount' => 100000,
                    'late_payment_penalties' => 0,
                    'interest_rate' => 0,
                    'interest_type' => 'flat_rate',
                    'term' => 30,
                    'term_period' => 'days',
                    'status' => 1,
                    'loan_application_fee' => 0,
                    'loan_application_fee_type' => 0,
                    'loan_processing_fee' => 0,
                    'loan_processing_fee_type' => 0,
                    'description' => 'Default loan product for VSLA members',
                ]);
            } catch (\Exception $e) {
                \Log::warning('VSLA Loan Product already exists or creation failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Toggle API module status
     */
    public function toggleApi(Request $request)
    {
        // Check permission - only admin can toggle modules
        if (!is_admin()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Permission denied!')]);
            }
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        $enabled = $request->boolean('enabled');
        
        // Check if already in the desired state
        if (($tenant->api_enabled ?? false) == $enabled) {
            $message = $enabled ? 'API module is already enabled' : 'API module is already disabled';
            
            if ($request->ajax()) {
                return response()->json(['result' => 'info', 'message' => _lang($message)]);
            }
            
            return back()->with('info', _lang($message));
        }
        
        DB::beginTransaction();
        
        try {
            $tenant->api_enabled = $enabled;
            $tenant->save();
            
            if ($enabled) {
                $this->provisionApiModule($tenant);
            }
            
            DB::commit();
            
            $message = $enabled ? 'API module activated successfully' : 'API module deactivated successfully';
            
            if ($request->ajax()) {
                return response()->json(['result' => 'success', 'message' => _lang($message)]);
            }
            
            return back()->with('success', _lang($message));
            
        } catch (\Exception $e) {
            DB::rollback();
            
            \Log::error('API Module Toggle Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('An error occurred while updating the module: ') . $e->getMessage()]);
            }
            
            return back()->with('error', _lang('An error occurred while updating the module: ') . $e->getMessage());
        }
    }
    
    /**
     * Provision API module when activated
     */
    private function provisionApiModule($tenant)
    {
        try {
            // Create default API permissions if they don't exist
            $this->createApiPermissions($tenant);
            
            // Log API module activation
            \Log::info('API module activated for tenant: ' . $tenant->id);
            
        } catch (\Exception $e) {
            \Log::error('API Module Provision Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
    
    /**
     * Create API permissions
     */
    private function createApiPermissions($tenant)
    {
        // This method can be extended to create default permissions
        // For now, we'll just log the activation
        \Log::info('API module permissions provisioned for tenant: ' . $tenant->id);
    }

    /**
     * Toggle QR Code module status
     */
    public function toggleQrCode(Request $request)
    {
        // Check permission - only admin can toggle modules
        if (!is_admin()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Permission denied!')]);
            }
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        $enabled = $request->boolean('enabled');
        
        DB::beginTransaction();
        
        try {
            $qrCodeSettings = QrCodeSetting::where('tenant_id', $tenant->id)->first();
            
            if (!$qrCodeSettings) {
                $qrCodeSettings = new QrCodeSetting();
                $qrCodeSettings->tenant_id = $tenant->id;
            }
            
            $qrCodeSettings->enabled = $enabled;
            $qrCodeSettings->save();
            
            if ($enabled) {
                $this->provisionQrCodeModule($tenant, $qrCodeSettings);
            }
            
            DB::commit();
            
            $message = $enabled ? 'QR Code module activated successfully' : 'QR Code module deactivated successfully';
            
            if ($request->ajax()) {
                return response()->json(['result' => 'success', 'message' => _lang($message)]);
            }
            
            return back()->with('success', _lang($message));
            
        } catch (\Exception $e) {
            DB::rollback();
            
            \Log::error('QR Code Module Toggle Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('An error occurred while updating the module: ') . $e->getMessage()]);
            }
            
            return back()->with('error', _lang('An error occurred while updating the module: ') . $e->getMessage());
        }
    }

    /**
     * Show QR Code module configuration
     */
    public function configureQrCode()
    {
        // Check permission - only admin can configure modules
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        // Check if qr_code_settings table exists
        if (!Schema::hasTable('qr_code_settings')) {
            return back()->with('error', _lang('QR Code module tables not found. Please run the migration first.'));
        }
        
        $qrCodeSettings = QrCodeSetting::where('tenant_id', $tenant->id)->first();
        
        if (!$qrCodeSettings) {
            $qrCodeSettings = new QrCodeSetting();
            $qrCodeSettings->tenant_id = $tenant->id;
            $qrCodeSettings->enabled = false;
        }
        
        $networks = QrCodeSetting::getAvailableNetworks();
        
        return view('backend.admin.modules.qr_code.configure', compact('qrCodeSettings', 'networks'));
    }

    /**
     * Update QR Code module configuration
     */
    public function updateQrCodeConfig(Request $request)
    {
        // Check permission - only admin can configure modules
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $request->validate([
            'enabled' => 'boolean',
            'ethereum_enabled' => 'boolean',
            'ethereum_network' => 'nullable|in:mainnet,goerli,sepolia,polygon,polygon_mumbai',
            'ethereum_rpc_url' => 'nullable|url',
            'ethereum_contract_address' => 'nullable|string',
            'ethereum_account_address' => 'nullable|string',
            'ethereum_private_key' => 'nullable|string',
            'qr_code_size' => 'integer|min:100|max:500',
            'qr_code_error_correction' => 'in:L,M,Q,H',
            'verification_cache_days' => 'integer|min:1|max:365',
            'auto_generate_qr' => 'boolean',
            'include_blockchain_verification' => 'boolean',
        ]);
        
        $tenant = app('tenant');
        
        DB::beginTransaction();
        
        try {
            $qrCodeSettings = QrCodeSetting::where('tenant_id', $tenant->id)->first();
            
            if (!$qrCodeSettings) {
                $qrCodeSettings = new QrCodeSetting();
                $qrCodeSettings->tenant_id = $tenant->id;
            }
            
            // Get all request data
            $data = $request->all();
            
            // If Ethereum is disabled, clear Ethereum fields
            if (!$request->boolean('ethereum_enabled')) {
                $data['ethereum_enabled'] = false;
                $data['ethereum_network'] = 'mainnet';
                $data['ethereum_rpc_url'] = null;
                $data['ethereum_contract_address'] = null;
                $data['ethereum_account_address'] = null;
                $data['ethereum_private_key'] = null;
                $data['include_blockchain_verification'] = false;
            }
            
            $qrCodeSettings->fill($data);
            $qrCodeSettings->save();
            
            DB::commit();
            
            return back()->with('success', _lang('QR Code module configuration updated successfully'));
            
        } catch (\Exception $e) {
            DB::rollback();
            
            \Log::error('QR Code Module Configuration Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return back()->with('error', _lang('An error occurred while updating the configuration: ') . $e->getMessage());
        }
    }

    /**
     * Test Ethereum connection
     */
    public function testEthereumConnection(Request $request)
    {
        // Check permission - only admin can test connections
        if (!is_admin()) {
            return response()->json(['result' => 'error', 'message' => _lang('Permission denied!')]);
        }
        
        $request->validate([
            'ethereum_network' => 'nullable|in:mainnet,goerli,sepolia,polygon,polygon_mumbai',
            'ethereum_rpc_url' => 'nullable|url',
            'ethereum_account_address' => 'nullable|string',
        ]);
        
        try {
            // Check if required fields are provided
            if (empty($request->ethereum_rpc_url)) {
                return response()->json([
                    'result' => 'error',
                    'message' => _lang('RPC URL is required for connection testing')
                ]);
            }
            
            $ethereumService = app(\App\Services\EthereumService::class);
            
            // Test RPC connection
            $response = \Illuminate\Support\Facades\Http::post($request->ethereum_rpc_url, [
                'jsonrpc' => '2.0',
                'method' => 'eth_blockNumber',
                'params' => [],
                'id' => 1
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['result'])) {
                    return response()->json([
                        'result' => 'success',
                        'message' => _lang('Ethereum connection successful'),
                        'block_number' => hexdec($data['result'])
                    ]);
                }
            }
            
            return response()->json([
                'result' => 'error',
                'message' => _lang('Failed to connect to Ethereum network')
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Ethereum Connection Test Error: ' . $e->getMessage());
            
            return response()->json([
                'result' => 'error',
                'message' => _lang('Connection test failed: ') . $e->getMessage()
            ]);
        }
    }

    /**
     * Provision QR Code module when activated
     */
    private function provisionQrCodeModule($tenant, $qrCodeSettings)
    {
        try {
            // Set default settings if not configured
            if (!$qrCodeSettings->qr_code_size) {
                $qrCodeSettings->qr_code_size = 200;
            }
            
            if (!$qrCodeSettings->qr_code_error_correction) {
                $qrCodeSettings->qr_code_error_correction = 'H';
            }
            
            if (!$qrCodeSettings->verification_cache_days) {
                $qrCodeSettings->verification_cache_days = 30;
            }
            
            $qrCodeSettings->save();
            
            // Log QR code module activation
            \Log::info('QR Code module activated for tenant: ' . $tenant->id);
            
        } catch (\Exception $e) {
            \Log::error('QR Code Module Provision Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Show QR Code Module Guide
     */
    public function qrCodeGuide()
    {
        return view('backend.admin.modules.qr-code-guide');
    }

    /**
     * Toggle Advanced Loan Management module status
     */
    public function toggleAdvancedLoanManagement(Request $request)
    {
        // Check permission - only admin can toggle modules
        if (!is_admin()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Permission denied!')]);
            }
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        $enabled = $request->boolean('enabled');
        
        // Check if already in the desired state
        if (($tenant->advanced_loan_management_enabled ?? true) == $enabled) {
            $message = $enabled ? 'Advanced Loan Management module is already enabled' : 'Advanced Loan Management module is already disabled';
            
            if ($request->ajax()) {
                return response()->json(['result' => 'info', 'message' => _lang($message)]);
            }
            
            return back()->with('info', _lang($message));
        }
        
        DB::beginTransaction();
        
        try {
            $tenant->advanced_loan_management_enabled = $enabled;
            $tenant->save();
            
            if ($enabled) {
                $this->provisionAdvancedLoanManagementModule($tenant);
            }
            
            DB::commit();
            
            $message = $enabled ? 'Advanced Loan Management module activated successfully' : 'Advanced Loan Management module deactivated successfully';
            
            if ($request->ajax()) {
                return response()->json(['result' => 'success', 'message' => _lang($message)]);
            }
            
            return back()->with('success', _lang($message));
            
        } catch (\Exception $e) {
            DB::rollback();
            
            \Log::error('Advanced Loan Management Module Toggle Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('An error occurred while updating the module: ') . $e->getMessage()]);
            }
            
            return back()->with('error', _lang('An error occurred while updating the module: ') . $e->getMessage());
        }
    }
    
    /**
     * Provision Advanced Loan Management module when activated
     */
    private function provisionAdvancedLoanManagementModule($tenant)
    {
        try {
            // Create default advanced loan products if they don't exist
            $this->createDefaultAdvancedLoanProducts($tenant);
            
            // Log Advanced Loan Management module activation
            \Log::info('Advanced Loan Management module activated for tenant: ' . $tenant->id);
            
        } catch (\Exception $e) {
            \Log::error('Advanced Loan Management Module Provision Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
    
    /**
     * Create default advanced loan products
     */
    private function createDefaultAdvancedLoanProducts($tenant)
    {
        $currency = \App\Models\Currency::where('tenant_id', $tenant->id)->first();
        
        if (!$currency) {
            \Log::error('No currency found for tenant when creating advanced loan products: ' . $tenant->id);
            throw new \Exception('No currency found for this tenant. Please add a currency first.');
        }
        
        // Check if loan_products table exists
        if (!Schema::hasTable('loan_products')) {
            \Log::warning('Loan products table does not exist. Please run the migration first.');
            return;
        }
        
        // Create Business Loan Product
        $businessLoanProduct = \App\Models\LoanProduct::where('tenant_id', $tenant->id)
            ->where('name', 'Business Loan')
            ->first();
            
        if (!$businessLoanProduct) {
            try {
                \App\Models\LoanProduct::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Business Loan',
                    'loan_id_prefix' => 'BL',
                    'starting_loan_id' => 1,
                    'minimum_amount' => 10000,
                    'maximum_amount' => 500000,
                    'late_payment_penalties' => 500,
                    'description' => 'General business loan for established businesses',
                    'interest_rate' => 15.0,
                    'interest_type' => 'fixed',
                    'term' => 24,
                    'term_period' => 'month',
                    'status' => 1,
                    'loan_application_fee' => 1000,
                    'loan_application_fee_type' => 0,
                    'loan_processing_fee' => 2500,
                    'loan_processing_fee_type' => 0,
                    'created_user_id' => 1, // System user
                ]);
            } catch (\Exception $e) {
                \Log::warning('Business Loan Product creation failed: ' . $e->getMessage());
            }
        }
        
        // Create Value Addition Enterprise Product
        $valueAdditionProduct = \App\Models\LoanProduct::where('tenant_id', $tenant->id)
            ->where('name', 'Value Addition Enterprise')
            ->first();
            
        if (!$valueAdditionProduct) {
            try {
                \App\Models\LoanProduct::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Value Addition Enterprise',
                    'loan_id_prefix' => 'VAE',
                    'starting_loan_id' => 1,
                    'minimum_amount' => 25000,
                    'maximum_amount' => 1000000,
                    'late_payment_penalties' => 1000,
                    'description' => 'Loan for value addition enterprises and agricultural processing',
                    'interest_rate' => 12.0,
                    'interest_type' => 'fixed',
                    'term' => 36,
                    'term_period' => 'month',
                    'status' => 1,
                    'loan_application_fee' => 2000,
                    'loan_application_fee_type' => 0,
                    'loan_processing_fee' => 5000,
                    'loan_processing_fee_type' => 0,
                ]);
            } catch (\Exception $e) {
                \Log::warning('Value Addition Enterprise Product creation failed: ' . $e->getMessage());
            }
        }
        
        // Create Startup Loan Product
        $startupLoanProduct = \App\Models\LoanProduct::where('tenant_id', $tenant->id)
            ->where('name', 'Startup Loan')
            ->first();
            
        if (!$startupLoanProduct) {
            try {
                \App\Models\LoanProduct::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Startup Loan',
                    'loan_id_prefix' => 'SL',
                    'starting_loan_id' => 1,
                    'minimum_amount' => 5000,
                    'maximum_amount' => 200000,
                    'late_payment_penalties' => 250,
                    'description' => 'Loan for new business startups and entrepreneurs',
                    'interest_rate' => 18.0,
                    'interest_type' => 'fixed',
                    'term' => 18,
                    'term_period' => 'month',
                    'status' => 1,
                    'loan_application_fee' => 500,
                    'loan_application_fee_type' => 0,
                    'loan_processing_fee' => 1500,
                    'loan_processing_fee_type' => 0,
                ]);
            } catch (\Exception $e) {
                \Log::warning('Startup Loan Product creation failed: ' . $e->getMessage());
            }
        }
    }
}
