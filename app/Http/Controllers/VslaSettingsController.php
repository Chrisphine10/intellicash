<?php

namespace App\Http\Controllers;

use App\Models\VslaSetting;
use App\Models\BankAccount;
use App\Models\LoanProduct;
use App\Notifications\VslaRoleAssignmentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class VslaSettingsController extends Controller
{
    /**
     * Display VSLA settings
     */
    public function index()
    {
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Check permission - admin has full access, VSLA User has limited access
        if (!is_admin() && !has_permission('vsla.settings.index')) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        // Ensure VSLA default data exists
        $settings = $this->ensureVslaDefaultData($tenant);
        
        return view('backend.admin.vsla.settings.index', compact('settings'));
    }

    /**
     * Update VSLA settings
     */
    public function update(Request $request)
    {
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Check permission - only admin can update VSLA settings
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied! Only administrators can update VSLA settings.'));
        }
        
        $validator = Validator::make($request->all(), [
            'share_amount' => 'required|numeric|min:0',
            'min_shares_per_member' => 'required|integer|min:1',
            'max_shares_per_member' => 'required|integer|min:1',
            'max_shares_per_meeting' => 'required|integer|min:1',
            'penalty_amount' => 'required|numeric|min:0',
            'welfare_amount' => 'required|numeric|min:0',
            'meeting_frequency' => 'required|in:weekly,monthly,custom',
            'meeting_day_of_week' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'meeting_days' => 'nullable|array',
            'meeting_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'meeting_time' => 'required|date_format:H:i',
            'auto_approve_loans' => 'boolean',
            'max_loan_amount' => 'nullable|numeric|min:0',
            'max_loan_duration_days' => 'nullable|integer|min:1',
        ], [
            'meeting_days.*.in' => 'Invalid meeting day selected.',
            'meeting_frequency.in' => 'Invalid meeting frequency selected.',
            'min_shares_per_member.min' => 'Minimum shares per member must be at least 1.',
            'max_shares_per_member.min' => 'Maximum shares per member must be at least 1.',
            'max_shares_per_meeting.min' => 'Maximum shares per meeting must be at least 1.',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        // Additional validation for share limits
        $minShares = (int) $request->input('min_shares_per_member');
        $maxShares = (int) $request->input('max_shares_per_member');
        $maxPerMeeting = (int) $request->input('max_shares_per_meeting');

        if ($maxShares < $minShares) {
            $error = _lang('Maximum shares per member must be greater than or equal to minimum shares per member.');
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => [$error]]);
            } else {
                return back()->with('error', $error)->withInput();
            }
        }

        if ($maxPerMeeting > $maxShares) {
            $error = _lang('Maximum shares per meeting cannot exceed maximum shares per member.');
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => [$error]]);
            } else {
                return back()->with('error', $error)->withInput();
            }
        }

        $settings = $tenant->vslaSettings;
        
        if (!$settings) {
            $settings = new VslaSetting();
            $settings->tenant_id = $tenant->id;
        }

        // Handle meeting days for all frequency types
        $data = $request->all();
        
        // Process meeting days based on frequency
        if (isset($data['meeting_days']) && is_array($data['meeting_days'])) {
            $data['meeting_days'] = array_values(array_unique($data['meeting_days']));
            
            // For weekly/monthly, ensure only one day is selected
            if (($data['meeting_frequency'] === 'weekly' || $data['meeting_frequency'] === 'monthly') && count($data['meeting_days']) > 1) {
                $data['meeting_days'] = [reset($data['meeting_days'])]; // Take only the first selected day
            }
            
            // For custom frequency, ensure at least one day is selected
            if ($data['meeting_frequency'] === 'custom' && empty($data['meeting_days'])) {
                if ($request->ajax()) {
                    return response()->json(['result' => 'error', 'message' => [_lang('Please select at least one meeting day for custom frequency.')]]);
                } else {
                    return back()->withErrors(['meeting_days' => _lang('Please select at least one meeting day for custom frequency.')])->withInput();
                }
            }
        } else {
            $data['meeting_days'] = null;
        }
        
        // Ensure meeting_time is properly formatted
        if (isset($data['meeting_time']) && !empty($data['meeting_time'])) {
            // Ensure time has seconds if not provided
            if (strlen($data['meeting_time']) === 5) { // HH:MM format
                $data['meeting_time'] = $data['meeting_time'] . ':00';
            }
        } else {
            // Set default time if not provided
            $data['meeting_time'] = '10:00:00';
        }

        $settings->fill($data);
        $settings->save();

        if ($request->ajax()) {
            return response()->json(['result' => 'success', 'message' => _lang('Settings updated successfully')]);
        }

        return back()->with('success', _lang('Settings updated successfully'));
    }

    /**
     * Create default VSLA accounts
     */
    private function createDefaultVslaAccounts($tenant)
    {
        // Create a single main VSLA bank account
        $mainAccount = BankAccount::where('tenant_id', $tenant->id)
            ->where('account_name', 'VSLA Main Account')
            ->first();

        if (!$mainAccount) {
            $mainAccount = BankAccount::create([
                'tenant_id' => $tenant->id,
                'opening_date' => now(),
                'bank_name' => 'VSLA Internal',
                'currency_id' => base_currency_id(),
                'account_name' => 'VSLA Main Account',
                'account_number' => 'VSLA-MAIN-' . $tenant->id,
                'opening_balance' => 0,
                'current_balance' => 0,
                'blocked_balance' => 0,
                'description' => 'Main VSLA account for all VSLA transactions and fund management',
                'is_active' => true,
                'allow_negative_balance' => false,
                'minimum_balance' => 0,
            ]);
        }

    }


    /**
     * Create VSLA savings products for different components
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
                'color' => '#fd7e14',
            ],
            [
                'name' => 'VSLA Welfare',
                'account_number_prefix' => 'VSLA-WELF',
                'starting_account_number' => 2000,
                'description' => 'Welfare contributions, social fund, and penalty fines',
                'color' => '#17a2b8',
            ],
            [
                'name' => 'VSLA Shares',
                'account_number_prefix' => 'VSLA-SHAR',
                'starting_account_number' => 3000,
                'description' => 'Member share contributions and purchases',
                'color' => '#28a745',
            ],
            [
                'name' => 'VSLA Others',
                'account_number_prefix' => 'VSLA-OTHR',
                'starting_account_number' => 4000,
                'description' => 'Other miscellaneous VSLA funds and contributions',
                'color' => '#6c757d',
            ],
            [
                'name' => 'VSLA Loan Fund',
                'account_number_prefix' => 'VSLA-LOAN',
                'starting_account_number' => 5000,
                'description' => 'Loan disbursements and repayments',
                'color' => '#dc3545',
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
     * Create default VSLA savings products (legacy method for compatibility)
     */
    private function createDefaultVslaSavingsProducts($tenant)
    {
        $this->createVslaSavingsProducts($tenant);
    }

    /**
     * Ensure VSLA default data exists (settings, accounts, products)
     */
    private function ensureVslaDefaultData($tenant)
    {
        // Ensure VSLA settings exist
        $settings = $tenant->vslaSettings;
        if (!$settings) {
            $settings = VslaSetting::create([
                'tenant_id' => $tenant->id,
                'share_amount' => 100, // Default share amount
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
        if ($settings->create_default_savings_products) {
            $this->createVslaSavingsProducts($tenant);
        }
        
        if ($settings->create_default_bank_accounts) {
            $this->createDefaultVslaAccounts($tenant);
        }
        
        if ($settings->create_default_loan_product) {
            $this->createDefaultVslaLoanProduct($tenant);
        }
        
        if ($settings->create_default_expense_categories) {
            $this->createDefaultExpenseCategories($tenant);
        }

        return $settings;
    }

    /**
     * Create default VSLA loan product
     */
    private function createDefaultVslaLoanProduct($tenant)
    {
        $existingProduct = LoanProduct::where('tenant_id', $tenant->id)
            ->where('name', 'VSLA Default Loan Product')
            ->first();

        if (!$existingProduct) {
            LoanProduct::create([
                'tenant_id' => $tenant->id,
                'name' => 'VSLA Default Loan Product',
                'loan_id_prefix' => 'VSLALI-',
                'starting_loan_id' => 1,
                'minimum_amount' => 100,
                'maximum_amount' => 50000,
                'interest_rate' => 2.0, // 2% per month
                'interest_type' => 'flat_rate',
                'late_payment_penalties' => 1.0, // 1% penalty
                'status' => 1,
                'description' => 'Default loan product for VSLA transactions',
            ]);
        }
    }

    /**
     * Sync VSLA accounts for all members
     */
    public function syncMemberAccounts(Request $request)
    {
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Check permission - only admin can sync member accounts
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied! Only administrators can sync member accounts.'));
        }

        try {
            DB::beginTransaction();

            // Ensure VSLA default data exists
            $this->ensureVslaDefaultData($tenant);

            // Get VSLA savings products
            $vslaProducts = \App\Models\SavingsProduct::where('tenant_id', $tenant->id)
                ->where('name', 'like', 'VSLA%')
                ->get();

            if ($vslaProducts->isEmpty()) {
                return back()->with('error', _lang('No VSLA savings products found. Please enable VSLA module first.'));
            }

            // Get all members
            $members = \App\Models\Member::where('tenant_id', $tenant->id)->get();
            
            $createdAccounts = 0;
            $skippedAccounts = 0;

            foreach ($members as $member) {
                foreach ($vslaProducts as $vslaProduct) {
                    // Check if member already has this VSLA account type
                    $existingAccount = \App\Models\SavingsAccount::where('tenant_id', $tenant->id)
                        ->where('member_id', $member->id)
                        ->where('savings_product_id', $vslaProduct->id)
                        ->first();

                    if (!$existingAccount) {
                        // Generate account number
                        $lastAccount = \App\Models\SavingsAccount::where('tenant_id', $tenant->id)
                            ->where('savings_product_id', $vslaProduct->id)
                            ->orderBy('account_number', 'desc')
                            ->first();

                        $accountNumber = $vslaProduct->account_number_prefix . '-' . 
                            str_pad(($lastAccount ? intval(substr($lastAccount->account_number, -4)) + 1 : $vslaProduct->starting_account_number), 4, '0', STR_PAD_LEFT);

                        // Create the account
                        \App\Models\SavingsAccount::create([
                            'tenant_id' => $tenant->id,
                            'account_number' => $accountNumber,
                            'member_id' => $member->id,
                            'savings_product_id' => $vslaProduct->id,
                            'status' => 1,
                            'opening_balance' => 0,
                            'description' => $vslaProduct->name . ' account for ' . $member->first_name . ' ' . $member->last_name,
                            'created_user_id' => auth()->id(),
                        ]);

                        $createdAccounts++;
                    } else {
                        $skippedAccounts++;
                    }
                }
            }

            // Create default expense categories for SACCO/Cooperative needs
            $expenseCategoriesCreated = $this->createDefaultExpenseCategories($tenant);

            DB::commit();

            $message = "VSLA account sync completed. Created: {$createdAccounts} member accounts, Skipped: {$skippedAccounts} existing accounts. VSLA savings products (Projects, Welfare, Shares, Others, Loan Fund) have been ensured.";
            if ($expenseCategoriesCreated > 0) {
                $message .= " Created: {$expenseCategoriesCreated} default expense categories.";
            }
            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while syncing VSLA accounts: ') . $e->getMessage());
        }
    }

    /**
     * Assign VSLA role to a member
     */
    public function assignRole(Request $request)
    {
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Check permission - only admin can assign VSLA roles
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied! Only administrators can assign VSLA roles.'));
        }

        $validator = Validator::make($request->all(), [
            'member_id' => 'required|exists:members,id',
            'role' => 'required|in:chairperson,treasurer,secretary',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // Check if member already has this role (active assignment)
            $existingAssignment = \App\Models\VslaRoleAssignment::where('tenant_id', $tenant->id)
                ->where('member_id', $request->member_id)
                ->where('role', $request->role)
                ->where('is_active', true)
                ->first();

            if ($existingAssignment) {
                return back()->with('error', _lang('This member already has this role assigned.'));
            }

            // Check if there's an inactive assignment for the same member and role
            $inactiveAssignment = \App\Models\VslaRoleAssignment::where('tenant_id', $tenant->id)
                ->where('member_id', $request->member_id)
                ->where('role', $request->role)
                ->where('is_active', false)
                ->first();

            if ($inactiveAssignment) {
                // Reactivate the existing assignment
                $inactiveAssignment->update([
                    'is_active' => true,
                    'assigned_by' => auth()->id(),
                    'assigned_at' => now(),
                    'notes' => $request->notes,
                ]);
            } else {
                // Create new role assignment
                \App\Models\VslaRoleAssignment::create([
                    'tenant_id' => $tenant->id,
                    'member_id' => $request->member_id,
                    'role' => $request->role,
                    'assigned_by' => auth()->id(),
                    'notes' => $request->notes,
                ]);
            }

            DB::commit();

            $member = \App\Models\Member::find($request->member_id);
            
            // Send notification to the member about role assignment
            try {
                $assignment = \App\Models\VslaRoleAssignment::where('tenant_id', $tenant->id)
                    ->where('member_id', $request->member_id)
                    ->where('role', $request->role)
                    ->where('is_active', true)
                    ->latest()
                    ->first();
                
                if ($assignment) {
                    $member->notify(new VslaRoleAssignmentNotification($assignment));
                }
            } catch (\Exception $e) {
                \Log::error('Failed to send role assignment notification: ' . $e->getMessage());
            }
            
            return back()->with('success', _lang('Role assigned successfully to ') . $member->first_name . ' ' . $member->last_name);

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while assigning role: ') . $e->getMessage());
        }
    }

    /**
     * Remove VSLA role from a member
     */
    public function removeRole(Request $request)
    {
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        // Check permission - only admin can remove VSLA roles
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied! Only administrators can remove VSLA roles.'));
        }

        $validator = Validator::make($request->all(), [
            'assignment_id' => 'required|exists:vsla_role_assignments,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            $assignment = \App\Models\VslaRoleAssignment::where('id', $request->assignment_id)
                ->where('tenant_id', $tenant->id)
                ->first();

            if (!$assignment) {
                return back()->with('error', _lang('Role assignment not found.'));
            }

            $member = $assignment->member;
            $role = $assignment->role;

            // Deactivate the role assignment
            $assignment->update(['is_active' => false]);

            DB::commit();

            return back()->with('success', _lang('Role removed successfully from ') . $member->first_name . ' ' . $member->last_name);

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred while removing role: ') . $e->getMessage());
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

        $createdCount = 0;

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

                $createdCount++;
            }
        }

        return $createdCount;
    }
}
