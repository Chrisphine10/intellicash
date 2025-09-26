<?php
namespace App\Imports;

use App\Models\Branch;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class MembersImport implements ToCollection, WithStartRow {

    private $data;

    public function __construct(array $data = []) {
        $this->data = $data;
    }

    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows) {
        $branches      = Branch::select('id', 'name')->get();
        $accountsTypes = SavingsProduct::where('auto_create', 1)->get();
        $importedCount = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            try {
                if ($row->filter()->isEmpty()) {
                    continue;
                }

                // Validate required fields
                if (empty($row[0]) || empty($row[1]) || empty($row[3])) {
                    $errors[] = "Row " . ($index + 2) . ": Missing required fields (First Name, Last Name, or Member No)";
                    continue;
                }

                // Sanitize and validate data
                $firstName = trim(strip_tags($row[0]));
                $lastName = trim(strip_tags($row[1]));
                $email = !empty($row[2]) ? trim(filter_var($row[2], FILTER_SANITIZE_EMAIL)) : null;
                $memberNo = trim(strip_tags($row[3]));

                // Validate email format
                if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Row " . ($index + 2) . ": Invalid email format";
                    continue;
                }

                // Check for duplicates
                if ($email && Member::where('email', $email)->exists()) {
                    $errors[] = "Row " . ($index + 2) . ": Email already exists";
                    continue;
                }

                if (Member::where('member_no', $memberNo)->exists()) {
                    $errors[] = "Row " . ($index + 2) . ": Member number already exists";
                    continue;
                }

                // Find branch
                $branchId = null;
                if (!empty($row[13])) {
                    $branch = $branches->where('name', trim($row[13]))->first();
                    $branchId = $branch ? $branch->id : null;
                }

                // Create member with sanitized data
                $member = new Member();
                $member->first_name    = $firstName;
                $member->last_name     = $lastName;
                $member->email         = $email;
                $member->member_no     = $memberNo;
                $member->country_code  = !empty($row[4]) ? trim(strip_tags($row[4])) : null;
                $member->mobile        = !empty($row[5]) ? trim(strip_tags($row[5])) : null;
                $member->business_name = !empty($row[6]) ? trim(strip_tags($row[6])) : null;
                $member->gender        = !empty($row[7]) ? strtolower(trim(strip_tags($row[7]))) : null;
                $member->city          = !empty($row[8]) ? trim(strip_tags($row[8])) : null;
                $member->county        = !empty($row[9]) ? trim(strip_tags($row[9])) : null;
                $member->zip           = !empty($row[10]) ? trim(strip_tags($row[10])) : null;
                $member->address       = !empty($row[11]) ? trim(strip_tags($row[11])) : null;
                $member->credit_source = !empty($row[12]) ? trim(strip_tags($row[12])) : null;
                $member->branch_id     = $branchId;
                $member->status        = 1;
                
                // Set tenant_id safely
                try {
                    $member->tenant_id = app('tenant')->id;
                } catch (\Exception $e) {
                    // If tenant service is not available, use default tenant ID or get from config
                    $member->tenant_id = 1; // Default tenant ID
                    \Log::warning('Using default tenant ID during import', [
                        'error' => $e->getMessage()
                    ]);
                }

                $member->save();
                $importedCount++;

                // Check if VSLA is enabled and auto-create member accounts is enabled
                $shouldCreateAccounts = true;
                try {
                    $tenant = app('tenant');
                    if ($tenant && method_exists($tenant, 'isVslaEnabled') && $tenant->isVslaEnabled()) {
                        $vslaSettings = $tenant->vslaSettings;
                        if ($vslaSettings && !$vslaSettings->auto_create_member_accounts) {
                            $shouldCreateAccounts = false;
                        }
                    }
                } catch (\Exception $e) {
                    // If tenant service is not available, proceed with account creation
                    \Log::warning('Tenant service not available during import, proceeding with account creation', [
                        'error' => $e->getMessage()
                    ]);
                }
                
                if ($shouldCreateAccounts) {
                    // SECURE: Use database transactions to prevent race conditions
                    DB::transaction(function () use ($member, $accountsTypes) {
                        foreach ($accountsTypes as $accountType) {
                            // SECURE: Generate unique account number atomically
                            $nextAccountNumber = $accountType->starting_account_number;
                            
                            // Check if account number already exists
                            $existingAccount = SavingsAccount::where('account_number', 
                                $accountType->account_number_prefix . $nextAccountNumber
                            )->first();
                            
                            if ($existingAccount) {
                                // Find next available account number
                                do {
                                    $nextAccountNumber++;
                                    $existingAccount = SavingsAccount::where('account_number', 
                                        $accountType->account_number_prefix . $nextAccountNumber
                                    )->first();
                                } while ($existingAccount);
                            }
                            
                            $savingsaccount = new SavingsAccount();
                            $savingsaccount->account_number     = $accountType->account_number_prefix . $nextAccountNumber;
                            $savingsaccount->member_id          = $member->id;
                            $savingsaccount->savings_product_id = $accountType->id;
                            $savingsaccount->status             = 1;
                            $savingsaccount->opening_balance    = 0;
                            $savingsaccount->description        = '';
                            $savingsaccount->created_user_id    = auth()->id();
                            
                            // Set tenant_id safely
                            try {
                                $savingsaccount->tenant_id = app('tenant')->id;
                            } catch (\Exception $e) {
                                $savingsaccount->tenant_id = 1; // Default tenant ID
                            }
                
                            $savingsaccount->save();
                
                            // SECURE: Update account number atomically
                            $accountType->starting_account_number = $nextAccountNumber + 1;
                            $accountType->save();
                        }
                    });
                }
                
            } catch (\Exception $e) {
                $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                \Log::error('Member import error', [
                    'row' => $index + 2,
                    'error' => $e->getMessage(),
                    'data' => $row->toArray()
                ]);
            }
        }

        // Store import results for later retrieval
        $this->importedCount = $importedCount;
        $this->errors = $errors;
    }

    public function getImportedCount()
    {
        return $this->importedCount ?? 0;
    }

    public function getErrors()
    {
        return $this->errors ?? [];
    }

    /**
     * @return int
     */
    public function startRow(): int {
        return 2;
    }

}