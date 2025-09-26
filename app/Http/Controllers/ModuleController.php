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
            'asset_management' => [
                'name' => 'Asset Management Module',
                'description' => 'Comprehensive asset management including vehicles, investments, and leasable items',
                'enabled' => $tenant->isAssetManagementEnabled(),
            ],
            'esignature' => [
                'name' => 'E-Signature Module',
                'description' => 'Electronic signature management for documents and agreements',
                'enabled' => $tenant->esignature_enabled ?? false,
            ],
            'payroll' => [
                'name' => 'Payroll Module',
                'description' => 'Comprehensive payroll management for employees and staff',
                'enabled' => $tenant->isPayrollEnabled(),
            ],
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
            
            // Create VSLA default items based on settings
            $this->createVslaDefaultItems($tenant);
            
        } catch (\Exception $e) {
            \Log::error('VSLA Module Provision Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
    
    /**
     * Create VSLA default items based on settings
     */
    private function createVslaDefaultItems($tenant)
    {
        $settings = $tenant->vslaSettings;
        
        if (!$settings) {
            // If no settings exist, create them with defaults
            $settings = \App\Models\VslaSetting::create([
                'tenant_id' => $tenant->id,
                'share_amount' => 100,
                'min_shares_per_member' => 1,
                'max_shares_per_member' => 5,
                'max_shares_per_meeting' => 3,
                'penalty_amount' => 50,
                'welfare_amount' => 20,
                'meeting_frequency' => 'weekly',
                'meeting_day_of_week' => null,
                'meeting_days' => null,
                'meeting_time' => '10:00:00',
                'auto_approve_loans' => false,
                'max_loan_amount' => null,
                'max_loan_duration_days' => null,
                'create_default_loan_product' => true,
                'create_default_savings_products' => true,
                'create_default_bank_accounts' => true,
                'create_default_expense_categories' => true,
                'auto_create_member_accounts' => true,
            ]);
        }
        
        // Create default items based on settings
        if ($settings->create_default_bank_accounts) {
            $this->createVslaAccounts($tenant);
        }
        
        if ($settings->create_default_loan_product) {
            $this->createVslaLoanProduct($tenant);
        }
        
        if ($settings->create_default_savings_products) {
            $this->createVslaSavingsProducts($tenant);
        }
        
        if ($settings->create_default_expense_categories) {
            $this->createDefaultExpenseCategories($tenant);
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
     * Toggle Asset Management module status
     */
    public function toggleAssetManagement(Request $request)
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
        if ($tenant->isAssetManagementEnabled() == $enabled) {
            $message = $enabled ? 'Asset Management module is already enabled' : 'Asset Management module is already disabled';
            
            if ($request->ajax()) {
                return response()->json(['result' => 'info', 'message' => _lang($message)]);
            }
            
            return back()->with('info', _lang($message));
        }
        
        DB::beginTransaction();
        
        try {
            $tenant->asset_management_enabled = $enabled;
            $tenant->save();
            
            if ($enabled) {
                $this->provisionAssetManagementModule($tenant);
            }
            
            DB::commit();
            
            $message = $enabled ? 'Asset Management module activated successfully' : 'Asset Management module deactivated successfully';
            
            if ($request->ajax()) {
                return response()->json(['result' => 'success', 'message' => _lang($message)]);
            }
            
            return back()->with('success', _lang($message));
            
        } catch (\Exception $e) {
            DB::rollback();
            
            \Log::error('Asset Management Module Toggle Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('An error occurred while updating the module: ') . $e->getMessage()]);
            }
            
            return back()->with('error', _lang('An error occurred while updating the module: ') . $e->getMessage());
        }
    }
    
    /**
     * Provision Asset Management module when activated
     */
    private function provisionAssetManagementModule($tenant)
    {
        try {
            // Create default asset categories if they don't exist
            $this->createDefaultAssetCategories($tenant);
            
            // Log Asset Management module activation
            \Log::info('Asset Management module activated for tenant: ' . $tenant->id);
            
        } catch (\Exception $e) {
            \Log::error('Asset Management Module Provision Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
    
    /**
     * Create default asset categories
     */
    private function createDefaultAssetCategories($tenant)
    {
        $defaultCategories = [
            [
                'name' => 'Vehicles',
                'description' => 'Cars, motorcycles, trucks, and other vehicles',
                'type' => 'leasable',
            ],
            [
                'name' => 'Office Equipment',
                'description' => 'Computers, printers, furniture, and office supplies',
                'type' => 'fixed',
            ],
            [
                'name' => 'Investment Portfolio',
                'description' => 'Stocks, bonds, mutual funds, and other investments',
                'type' => 'investment',
            ],
            [
                'name' => 'Event Equipment',
                'description' => 'Tents, chairs, tables, and event supplies',
                'type' => 'leasable',
            ],
            [
                'name' => 'Real Estate',
                'description' => 'Buildings, land, and property investments',
                'type' => 'fixed',
            ],
            [
                'name' => 'Agricultural Equipment',
                'description' => 'Farming tools, tractors, and agricultural machinery',
                'type' => 'leasable',
            ],
        ];

        foreach ($defaultCategories as $categoryData) {
            $existingCategory = \App\Models\AssetCategory::where('tenant_id', $tenant->id)
                                                         ->where('name', $categoryData['name'])
                                                         ->first();
            
            if (!$existingCategory) {
                \App\Models\AssetCategory::create([
                    'tenant_id' => $tenant->id,
                    'name' => $categoryData['name'],
                    'description' => $categoryData['description'],
                    'type' => $categoryData['type'],
                    'is_active' => true,
                ]);
            }
        }
    }
    
    /**
     * Create VSLA savings products
     */
    private function createVslaSavingsProducts($tenant)
    {
        $baseCurrencyId = base_currency_id();
        
        $vslaProducts = [
            [
                'name' => 'VSLA Projects',
                'account_number_prefix' => 'VSLA-PROJ',
                'starting_account_number' => 1000,
                'description' => 'Project funding and investment accounts',
            ],
            [
                'name' => 'VSLA Welfare',
                'account_number_prefix' => 'VSLA-WELF',
                'starting_account_number' => 2000,
                'description' => 'Welfare contributions, social fund, and penalty fines',
            ],
            [
                'name' => 'VSLA Shares',
                'account_number_prefix' => 'VSLA-SHAR',
                'starting_account_number' => 3000,
                'description' => 'Member share contributions and purchases',
            ],
            [
                'name' => 'VSLA Others',
                'account_number_prefix' => 'VSLA-OTHR',
                'starting_account_number' => 4000,
                'description' => 'Other miscellaneous VSLA funds and contributions',
            ],
            [
                'name' => 'VSLA Loan Fund',
                'account_number_prefix' => 'VSLA-LOAN',
                'starting_account_number' => 5000,
                'description' => 'Loan disbursements and repayments',
            ]
        ];

        foreach ($vslaProducts as $productData) {
            // Check if product already exists
            $existingProduct = \App\Models\SavingsProduct::where('tenant_id', $tenant->id)
                ->where('name', $productData['name'])
                ->first();

            if (!$existingProduct) {
                // VSLA Shares should not allow withdrawals (share purchases are permanent until shareout)
                $allowWithdraw = ($productData['name'] === 'VSLA Shares') ? 0 : 1;
                
                \App\Models\SavingsProduct::create([
                    'tenant_id' => $tenant->id,
                    'name' => $productData['name'],
                    'account_number_prefix' => $productData['account_number_prefix'],
                    'starting_account_number' => $productData['starting_account_number'],
                    'currency_id' => $baseCurrencyId,
                    'interest_rate' => 0,
                    'interest_method' => 'none',
                    'allow_withdraw' => $allowWithdraw,
                    'minimum_account_balance' => 0,
                    'minimum_deposit_amount' => 10,
                    'maintenance_fee' => 0,
                    'auto_create' => 1,
                    'status' => 1,
                ]);
            } elseif ($productData['name'] === 'VSLA Shares' && $existingProduct->allow_withdraw == 1) {
                // Update existing VSLA Shares product to disallow withdrawals
                $existingProduct->update(['allow_withdraw' => 0]);
            }
        }
    }
    
    /**
     * Create default expense categories for SACCO/Cooperative needs
     */
    private function createDefaultExpenseCategories($tenant)
    {
        $defaultCategories = [
            [
                'name' => 'Administrative Expenses',
                'description' => 'General administrative and office expenses',
                'color' => '#3498db'
            ],
            [
                'name' => 'Staff Salaries & Benefits',
                'description' => 'Employee salaries, wages, and benefits',
                'color' => '#e74c3c'
            ],
            [
                'name' => 'Office Rent & Utilities',
                'description' => 'Office rent, electricity, water, internet, and phone bills',
                'color' => '#f39c12'
            ],
            [
                'name' => 'Office Supplies & Equipment',
                'description' => 'Office supplies, equipment, and maintenance',
                'color' => '#9b59b6'
            ],
            [
                'name' => 'Training & Development',
                'description' => 'Staff training, workshops, and professional development',
                'color' => '#1abc9c'
            ],
            [
                'name' => 'Marketing & Promotion',
                'description' => 'Marketing campaigns, advertising, and promotional activities',
                'color' => '#e67e22'
            ],
            [
                'name' => 'Legal & Professional Fees',
                'description' => 'Legal fees, audit fees, and professional services',
                'color' => '#34495e'
            ],
            [
                'name' => 'Insurance & Security',
                'description' => 'Insurance premiums and security services',
                'color' => '#2c3e50'
            ],
            [
                'name' => 'Transportation & Travel',
                'description' => 'Vehicle maintenance, fuel, and business travel expenses',
                'color' => '#16a085'
            ],
            [
                'name' => 'Bank Charges & Fees',
                'description' => 'Banking fees, transaction charges, and financial services',
                'color' => '#27ae60'
            ],
            [
                'name' => 'VSLA Meeting Expenses',
                'description' => 'Expenses related to VSLA meetings and activities',
                'color' => '#8e44ad'
            ],
            [
                'name' => 'Community Development',
                'description' => 'Community projects and development initiatives',
                'color' => '#2980b9'
            ],
            [
                'name' => 'Emergency Fund',
                'description' => 'Emergency expenses and contingency funds',
                'color' => '#c0392b'
            ],
            [
                'name' => 'Technology & IT',
                'description' => 'Software licenses, IT support, and technology upgrades',
                'color' => '#7f8c8d'
            ],
            [
                'name' => 'Miscellaneous',
                'description' => 'Other miscellaneous expenses not categorized elsewhere',
                'color' => '#95a5a6'
            ]
        ];

        foreach ($defaultCategories as $categoryData) {
            // Check if category already exists
            $existingCategory = \App\Models\ExpenseCategory::where('tenant_id', $tenant->id)
                ->where('name', $categoryData['name'])
                ->first();

            if (!$existingCategory) {
                \App\Models\ExpenseCategory::create([
                    'tenant_id' => $tenant->id,
                    'name' => $categoryData['name'],
                    'description' => $categoryData['description'],
                    'color' => $categoryData['color'],
                ]);
            }
        }
    }

    /**
     * Toggle E-Signature module status
     */
    public function toggleESignature(Request $request)
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
        if (($tenant->esignature_enabled ?? false) == $enabled) {
            $message = $enabled ? 'E-Signature module is already enabled' : 'E-Signature module is already disabled';
            
            if ($request->ajax()) {
                return response()->json(['result' => 'info', 'message' => _lang($message)]);
            }
            
            return back()->with('info', _lang($message));
        }
        
        DB::beginTransaction();
        
        try {
            $tenant->esignature_enabled = $enabled;
            $tenant->save();
            
            if ($enabled) {
                $this->provisionESignatureModule($tenant);
            }
            
            DB::commit();
            
            $message = $enabled ? 'E-Signature module activated successfully' : 'E-Signature module deactivated successfully';
            
            if ($request->ajax()) {
                return response()->json(['result' => 'success', 'message' => _lang($message)]);
            }
            
            return back()->with('success', _lang($message));
            
        } catch (\Exception $e) {
            DB::rollback();
            
            \Log::error('E-Signature Module Toggle Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('An error occurred while updating the module: ') . $e->getMessage()]);
            }
            
            return back()->with('error', _lang('An error occurred while updating the module: ') . $e->getMessage());
        }
    }
    
    /**
     * Provision E-Signature module when activated
     */
    private function provisionESignatureModule($tenant)
    {
        try {
            // Create default E-Signature settings if they don't exist
            $this->createESignatureSettings($tenant);
            
            // Log E-Signature module activation
            \Log::info('E-Signature module activated for tenant: ' . $tenant->id);
            
        } catch (\Exception $e) {
            \Log::error('E-Signature Module Provision Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
    
    /**
     * Create E-Signature settings
     */
    private function createESignatureSettings($tenant)
    {
        // For now, we'll just log the activation
        // In the future, you can add default E-Signature settings here
        \Log::info('E-Signature module settings provisioned for tenant: ' . $tenant->id);
    }

    /**
     * Toggle Payroll module status
     */
    public function togglePayroll(Request $request)
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
        if ($tenant->isPayrollEnabled() == $enabled) {
            $message = $enabled ? 'Payroll module is already enabled' : 'Payroll module is already disabled';
            
            if ($request->ajax()) {
                return response()->json(['result' => 'info', 'message' => _lang($message)]);
            }
            
            return back()->with('info', _lang($message));
        }
        
        DB::beginTransaction();
        
        try {
            $tenant->payroll_enabled = $enabled;
            $tenant->save();
            
            if ($enabled) {
                $this->provisionPayrollModule($tenant);
            }
            
            DB::commit();
            
            $message = $enabled ? 'Payroll module activated successfully' : 'Payroll module deactivated successfully';
            
            if ($request->ajax()) {
                return response()->json(['result' => 'success', 'message' => _lang($message)]);
            }
            
            return back()->with('success', _lang($message));
            
        } catch (\Exception $e) {
            DB::rollback();
            
            \Log::error('Payroll Module Toggle Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('An error occurred while updating the module: ') . $e->getMessage()]);
            }
            
            return back()->with('error', _lang('An error occurred while updating the module: ') . $e->getMessage());
        }
    }
    
    /**
     * Provision Payroll module when activated
     */
    private function provisionPayrollModule($tenant)
    {
        try {
            // Create default payroll deductions and benefits
            $this->createDefaultPayrollDeductions($tenant);
            $this->createDefaultPayrollBenefits($tenant);
            
            // Log Payroll module activation
            \Log::info('Payroll module activated for tenant: ' . $tenant->id);
            
        } catch (\Exception $e) {
            \Log::error('Payroll Module Provision Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
    
    /**
     * Create default payroll deductions
     */
    private function createDefaultPayrollDeductions($tenant)
    {
        \App\Models\PayrollDeduction::createDefaultDeductions($tenant->id, auth()->id());
    }
    
    /**
     * Create default payroll benefits
     */
    private function createDefaultPayrollBenefits($tenant)
    {
        \App\Models\PayrollBenefit::createDefaultBenefits($tenant->id, auth()->id());
    }

    /**
     * Show Payroll module configuration
     */
    public function configurePayroll()
    {
        // Check permission - only admin can configure modules
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        // Get current employee account type setting
        $employeeAccountType = get_tenant_option('employee_account_type', 'system_users', $tenant->id);
        
        return view('backend.admin.modules.payroll.configure', compact('employeeAccountType'));
    }

    /**
     * Update Payroll module configuration
     */
    public function updatePayrollConfig(Request $request)
    {
        // Check permission - only admin can configure modules
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $request->validate([
            'employee_account_type' => 'required|in:system_users,member_accounts',
        ]);
        
        $tenant = app('tenant');
        
        try {
            // Update the tenant setting
            update_tenant_option('employee_account_type', $request->employee_account_type, $tenant->id);
            
            return back()->with('success', _lang('Payroll module configuration updated successfully'));
            
        } catch (\Exception $e) {
            \Log::error('Payroll Module Configuration Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return back()->with('error', _lang('An error occurred while updating the configuration: ') . $e->getMessage());
        }
    }

}
