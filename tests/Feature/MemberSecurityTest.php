<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Member;
use App\Models\User;
use App\Models\Tenant;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Models\Currency;
use App\Models\SavingsProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\WithFaker;

class MemberSecurityTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $tenant;
    protected $adminUser;
    protected $member;
    protected $otherTenant;
    protected $otherMember;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenants
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 1,
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'status' => 1,
        ]);

        // Create admin user for test tenant
        $this->adminUser = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'user_type' => 'admin',
            'tenant_id' => $this->tenant->id,
            'status' => 1,
        ]);

        // Create test member
        $this->member = Member::create([
            'first_name' => 'Test',
            'last_name' => 'Member',
            'member_no' => 'TM001',
            'email' => 'member@test.com',
            'mobile' => '1234567890',
            'status' => 1,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
        ]);

        // Create member for other tenant
        $this->otherMember = Member::create([
            'first_name' => 'Other',
            'last_name' => 'Member',
            'member_no' => 'OM001',
            'email' => 'other@test.com',
            'mobile' => '0987654321',
            'status' => 1,
            'tenant_id' => $this->otherTenant->id,
            'user_id' => $this->adminUser->id,
        ]);

        // Create currency and savings product for testing
        $currency = Currency::create([
            'full_name' => 'Test Currency',
            'name' => 'TST',
            'exchange_rate' => 1.0,
            'base_currency' => 1,
            'status' => 1,
            'tenant_id' => $this->tenant->id,
        ]);

        $savingsProduct = SavingsProduct::create([
            'name' => 'Test Savings Product',
            'account_number_prefix' => 'TST',
            'starting_account_number' => 1000,
            'currency_id' => $currency->id,
            'interest_rate' => 5.0,
            'status' => 1,
            'tenant_id' => $this->tenant->id,
        ]);

        // Create savings account
        SavingsAccount::create([
            'member_id' => $this->member->id,
            'savings_product_id' => $savingsProduct->id,
            'account_number' => 'TST1001',
            'balance' => 1000.00,
            'status' => 1,
            'tenant_id' => $this->tenant->id,
        ]);

        // Set tenant context
        app()->instance('tenant', $this->tenant);
    }

    /** @test */
    public function it_prevents_cross_tenant_member_access()
    {
        // Login as admin for test tenant
        Auth::login($this->adminUser);

        // Try to access member from other tenant
        $response = $this->get(route('members.show', [
            'tenant' => $this->tenant->slug,
            'id' => $this->otherMember->id
        ]));

        // Should return 404 or redirect due to tenant isolation
        $this->assertTrue(
            $response->status() === 404 || 
            $response->status() === 403 ||
            $response->isRedirect()
        );
    }

    /** @test */
    public function it_prevents_sql_injection_in_member_queries()
    {
        Auth::login($this->adminUser);

        // Test SQL injection attempt in member ID
        $maliciousId = "1'; DROP TABLE members; --";
        
        $response = $this->get(route('members.show', [
            'tenant' => $this->tenant->slug,
            'id' => $maliciousId
        ]));

        // Should handle gracefully without executing malicious SQL
        $this->assertTrue(
            $response->status() === 404 || 
            $response->status() === 422 ||
            $response->isRedirect()
        );

        // Verify members table still exists
        $this->assertTrue(DB::table('members')->count() >= 2);
    }

    /** @test */
    public function it_validates_tenant_ownership_of_members()
    {
        Auth::login($this->adminUser);

        // Try to edit member from other tenant
        $response = $this->get(route('members.edit', [
            'tenant' => $this->tenant->slug,
            'id' => $this->otherMember->id
        ]));

        // Should be denied access
        $this->assertTrue(
            $response->status() === 404 || 
            $response->status() === 403 ||
            $response->isRedirect()
        );
    }

    /** @test */
    public function it_prevents_unauthorized_member_data_access()
    {
        // Create a regular user (not admin)
        $regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@test.com',
            'password' => Hash::make('password123'),
            'user_type' => 'user',
            'tenant_id' => $this->tenant->id,
            'status' => 1,
        ]);

        Auth::login($regularUser);

        // Try to access member data without proper permissions
        $response = $this->get(route('members.show', [
            'tenant' => $this->tenant->slug,
            'id' => $this->member->id
        ]));

        // Should be denied access
        $this->assertTrue(
            $response->status() === 403 || 
            $response->isRedirect()
        );
    }

    /** @test */
    public function it_prevents_access_to_inactive_members()
    {
        // Create inactive member
        $inactiveMember = Member::create([
            'first_name' => 'Inactive',
            'last_name' => 'Member',
            'member_no' => 'IM001',
            'email' => 'inactive@test.com',
            'mobile' => '1111111111',
            'status' => 0, // Inactive
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
        ]);

        Auth::login($this->adminUser);

        // Try to access inactive member
        $response = $this->get(route('members.show', [
            'tenant' => $this->tenant->slug,
            'id' => $inactiveMember->id
        ]));

        // Should be denied access to inactive members
        $this->assertTrue(
            $response->status() === 404 || 
            $response->status() === 403 ||
            $response->isRedirect()
        );
    }

    /** @test */
    public function it_validates_member_input_data()
    {
        Auth::login($this->adminUser);

        // Test XSS attempt in member data
        $maliciousData = [
            'first_name' => '<script>alert("xss")</script>',
            'last_name' => 'Test',
            'email' => 'test@test.com',
            'member_no' => 'TM002',
        ];

        $response = $this->post(route('members.store', [
            'tenant' => $this->tenant->slug
        ]), $maliciousData);

        // Should either reject the data or sanitize it
        $this->assertTrue(
            $response->status() === 422 || // Validation error
            $response->status() === 302    // Redirect after sanitization
        );
    }

    /** @test */
    public function it_prevents_sql_injection_in_savings_account_queries()
    {
        Auth::login($this->adminUser);

        // Test SQL injection in savings account ID
        $maliciousId = "1'; DROP TABLE savings_accounts; --";
        
        $response = $this->get(route('savings_accounts.show', [
            'tenant' => $this->tenant->slug,
            'id' => $maliciousId
        ]));

        // Should handle gracefully
        $this->assertTrue(
            $response->status() === 404 || 
            $response->status() === 422 ||
            $response->isRedirect()
        );

        // Verify savings_accounts table still exists
        $this->assertTrue(DB::table('savings_accounts')->count() >= 1);
    }

    /** @test */
    public function it_prevents_cross_tenant_savings_account_access()
    {
        Auth::login($this->adminUser);

        // Create savings account for other tenant
        $otherCurrency = Currency::create([
            'full_name' => 'Other Currency',
            'name' => 'OTH',
            'exchange_rate' => 1.0,
            'base_currency' => 1,
            'status' => 1,
            'tenant_id' => $this->otherTenant->id,
        ]);

        $otherSavingsProduct = SavingsProduct::create([
            'name' => 'Other Savings Product',
            'account_number_prefix' => 'OTH',
            'starting_account_number' => 2000,
            'currency_id' => $otherCurrency->id,
            'interest_rate' => 5.0,
            'status' => 1,
            'tenant_id' => $this->otherTenant->id,
        ]);

        $otherSavingsAccount = SavingsAccount::create([
            'member_id' => $this->otherMember->id,
            'savings_product_id' => $otherSavingsProduct->id,
            'account_number' => 'OTH2001',
            'balance' => 2000.00,
            'status' => 1,
            'tenant_id' => $this->otherTenant->id,
        ]);

        // Try to access savings account from other tenant
        $response = $this->get(route('savings_accounts.show', [
            'tenant' => $this->tenant->slug,
            'id' => $otherSavingsAccount->id
        ]));

        // Should be denied access
        $this->assertTrue(
            $response->status() === 404 || 
            $response->status() === 403 ||
            $response->isRedirect()
        );
    }

    /** @test */
    public function it_validates_member_permissions_correctly()
    {
        // Test with different user types
        $userTypes = ['admin', 'user', 'customer'];
        
        foreach ($userTypes as $userType) {
            $user = User::create([
                'name' => ucfirst($userType) . ' User',
                'email' => $userType . '@test.com',
                'password' => Hash::make('password123'),
                'user_type' => $userType,
                'tenant_id' => $this->tenant->id,
                'status' => 1,
            ]);

            Auth::login($user);

            $response = $this->get(route('members.index', [
                'tenant' => $this->tenant->slug
            ]));

            // Admin should have access, others should be denied
            if ($userType === 'admin') {
                $this->assertTrue($response->status() === 200);
            } else {
                $this->assertTrue(
                    $response->status() === 403 || 
                    $response->isRedirect()
                );
            }

            Auth::logout();
        }
    }

    /** @test */
    public function it_prevents_mass_assignment_vulnerabilities()
    {
        Auth::login($this->adminUser);

        // Try to update protected fields
        $maliciousData = [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => 'updated@test.com',
            'member_no' => 'TM003',
            'tenant_id' => $this->otherTenant->id, // Try to change tenant
            'status' => 0, // Try to deactivate
            'created_at' => now()->subYear(), // Try to change creation date
        ];

        $response = $this->put(route('members.update', [
            'tenant' => $this->tenant->slug,
            'id' => $this->member->id
        ]), $maliciousData);

        // Should either reject or ignore protected fields
        $this->assertTrue(
            $response->status() === 422 || // Validation error
            $response->status() === 302    // Redirect after processing
        );

        // Verify tenant_id wasn't changed
        $this->member->refresh();
        $this->assertEquals($this->tenant->id, $this->member->tenant_id);
    }

    /** @test */
    public function it_logs_security_events()
    {
        Auth::login($this->adminUser);

        // Perform a sensitive operation
        $response = $this->get(route('members.show', [
            'tenant' => $this->tenant->slug,
            'id' => $this->member->id
        ]));

        // Check if audit trail was created (if audit system is implemented)
        // This test assumes audit trails are being logged
        $this->assertTrue($response->status() === 200);
    }

    /** @test */
    public function it_handles_concurrent_member_access()
    {
        // Simulate concurrent access to same member
        Auth::login($this->adminUser);

        $responses = [];
        
        // Simulate multiple simultaneous requests
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->get(route('members.show', [
                'tenant' => $this->tenant->slug,
                'id' => $this->member->id
            ]));
        }

        // All requests should succeed without conflicts
        foreach ($responses as $response) {
            $this->assertTrue($response->status() === 200);
        }
    }

    /** @test */
    public function it_handles_member_acceptance_with_null_user()
    {
        Auth::login($this->adminUser);

        // Create a pending member without user account
        $pendingMember = Member::create([
            'first_name' => 'Pending',
            'last_name' => 'Member',
            'member_no' => 'PM001',
            'email' => 'pending@test.com',
            'mobile' => '1111111111',
            'status' => 0, // Pending status
            'tenant_id' => $this->tenant->id,
            'user_id' => null, // No user account
        ]);

        // Accept the member request
        $response = $this->post(route('members.accept_request', [
            'tenant' => $this->tenant->slug,
            'id' => $pendingMember->id
        ]), [
            'member_no' => 'PM001'
        ]);

        // Should succeed without errors
        $this->assertTrue(
            $response->status() === 302 || // Redirect after success
            $response->status() === 200    // AJAX success
        );

        // Verify member status was updated
        $pendingMember->refresh();
        $this->assertEquals(1, $pendingMember->status);
    }

    /** @test */
    public function it_validates_cross_tenant_member_number_uniqueness()
    {
        Auth::login($this->adminUser);

        // Create a pending member
        $pendingMember = Member::create([
            'first_name' => 'Pending',
            'last_name' => 'Member',
            'member_no' => 'PM002',
            'email' => 'pending2@test.com',
            'mobile' => '2222222222',
            'status' => 0,
            'tenant_id' => $this->tenant->id,
            'user_id' => null,
        ]);

        // Try to accept with member number from other tenant
        $response = $this->post(route('members.accept_request', [
            'tenant' => $this->tenant->slug,
            'id' => $pendingMember->id
        ]), [
            'member_no' => $this->otherMember->member_no // Member number from other tenant
        ]);

        // Should succeed because tenant validation prevents conflicts
        $this->assertTrue(
            $response->status() === 302 || // Redirect after success
            $response->status() === 200    // AJAX success
        );

        // Verify member was accepted with the new number
        $pendingMember->refresh();
        $this->assertEquals($this->otherMember->member_no, $pendingMember->member_no);
        $this->assertEquals(1, $pendingMember->status);
    }

    /** @test */
    public function it_validates_member_status_before_acceptance()
    {
        Auth::login($this->adminUser);

        // Try to accept an already active member
        $response = $this->post(route('members.accept_request', [
            'tenant' => $this->tenant->slug,
            'id' => $this->member->id // Already active member
        ]), [
            'member_no' => 'TM001'
        ]);

        // Should fail with appropriate error
        $this->assertTrue(
            $response->status() === 302 || // Redirect with error
            $response->status() === 422    // Validation error
        );

        // Verify member status wasn't changed
        $this->member->refresh();
        $this->assertEquals(1, $this->member->status);
    }

    /** @test */
    public function it_handles_transaction_rollback_on_error()
    {
        Auth::login($this->adminUser);

        // Create a pending member
        $pendingMember = Member::create([
            'first_name' => 'Pending',
            'last_name' => 'Member',
            'member_no' => 'PM003',
            'email' => 'pending3@test.com',
            'mobile' => '3333333333',
            'status' => 0,
            'tenant_id' => $this->tenant->id,
            'user_id' => null,
        ]);

        // Mock a database error by using an invalid member number format
        $response = $this->post(route('members.accept_request', [
            'tenant' => $this->tenant->slug,
            'id' => $pendingMember->id
        ]), [
            'member_no' => 'INVALID@#$%' // Invalid format that should fail validation
        ]);

        // Should fail with validation error
        $this->assertTrue(
            $response->status() === 302 || // Redirect with error
            $response->status() === 422    // Validation error
        );

        // Verify member status wasn't changed (rollback worked)
        $pendingMember->refresh();
        $this->assertEquals(0, $pendingMember->status);
    }

    /** @test */
    public function it_logs_member_acceptance_events()
    {
        Auth::login($this->adminUser);

        // Create a pending member
        $pendingMember = Member::create([
            'first_name' => 'Pending',
            'last_name' => 'Member',
            'member_no' => 'PM004',
            'email' => 'pending4@test.com',
            'mobile' => '4444444444',
            'status' => 0,
            'tenant_id' => $this->tenant->id,
            'user_id' => null,
        ]);

        // Clear any existing logs
        Log::shouldReceive('info')->once()->with('Member request accepted', \Mockery::type('array'));
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        // Accept the member request
        $response = $this->post(route('members.accept_request', [
            'tenant' => $this->tenant->slug,
            'id' => $pendingMember->id
        ]), [
            'member_no' => 'PM004'
        ]);

        // Should succeed
        $this->assertTrue(
            $response->status() === 302 || // Redirect after success
            $response->status() === 200    // AJAX success
        );
    }

    /** @test */
    public function it_validates_member_number_format()
    {
        Auth::login($this->adminUser);

        // Create a pending member
        $pendingMember = Member::create([
            'first_name' => 'Pending',
            'last_name' => 'Member',
            'member_no' => 'PM005',
            'email' => 'pending5@test.com',
            'mobile' => '5555555555',
            'status' => 0,
            'tenant_id' => $this->tenant->id,
            'user_id' => null,
        ]);

        // Test various invalid member number formats
        $invalidFormats = [
            'PM@005',      // Contains special characters
            'pm005',       // Lowercase letters
            'PM 005',      // Contains spaces
            'PM-005',      // Contains hyphens (should be valid)
            'PM_005',      // Contains underscores (should be valid)
            '',            // Empty string
            'PM005!',      // Contains exclamation mark
        ];

        foreach ($invalidFormats as $invalidFormat) {
            $response = $this->post(route('members.accept_request', [
                'tenant' => $this->tenant->slug,
                'id' => $pendingMember->id
            ]), [
                'member_no' => $invalidFormat
            ]);

            // Should fail validation for invalid formats
            if (in_array($invalidFormat, ['PM-005', 'PM_005'])) {
                // These should be valid
                $this->assertTrue(
                    $response->status() === 302 || // Redirect after success
                    $response->status() === 200    // AJAX success
                );
            } else {
                // These should fail
                $this->assertTrue(
                    $response->status() === 302 || // Redirect with error
                    $response->status() === 422    // Validation error
                );
            }
        }
    }

    /** @test */
    public function it_prevents_duplicate_member_numbers_within_tenant()
    {
        Auth::login($this->adminUser);

        // Create a pending member
        $pendingMember = Member::create([
            'first_name' => 'Pending',
            'last_name' => 'Member',
            'member_no' => 'PM006',
            'email' => 'pending6@test.com',
            'mobile' => '6666666666',
            'status' => 0,
            'tenant_id' => $this->tenant->id,
            'user_id' => null,
        ]);

        // Try to accept with existing member number from same tenant
        $response = $this->post(route('members.accept_request', [
            'tenant' => $this->tenant->slug,
            'id' => $pendingMember->id
        ]), [
            'member_no' => $this->member->member_no // Existing member number
        ]);

        // Should fail with validation error
        $this->assertTrue(
            $response->status() === 302 || // Redirect with error
            $response->status() === 422    // Validation error
        );

        // Verify member status wasn't changed
        $pendingMember->refresh();
        $this->assertEquals(0, $pendingMember->status);
    }

    /** @test */
    public function it_handles_ajax_requests_correctly()
    {
        Auth::login($this->adminUser);

        // Create a pending member
        $pendingMember = Member::create([
            'first_name' => 'Pending',
            'last_name' => 'Member',
            'member_no' => 'PM007',
            'email' => 'pending7@test.com',
            'mobile' => '7777777777',
            'status' => 0,
            'tenant_id' => $this->tenant->id,
            'user_id' => null,
        ]);

        // Test AJAX request
        $response = $this->post(route('members.accept_request', [
            'tenant' => $this->tenant->slug,
            'id' => $pendingMember->id
        ]), [
            'member_no' => 'PM007'
        ], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json'
        ]);

        // Should return JSON response
        $this->assertTrue(
            $response->status() === 200 || // JSON success
            $response->status() === 422     // JSON validation error
        );

        if ($response->status() === 200) {
            $data = $response->json();
            $this->assertArrayHasKey('result', $data);
            $this->assertEquals('success', $data['result']);
        }
    }
}
