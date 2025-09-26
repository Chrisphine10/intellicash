<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\SavingsAccount;
use App\Models\Member;
use App\Models\SavingsProduct;
use App\Models\Currency;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class SavingsAccountTableFixTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;
    protected $member;
    protected $savingsProduct;
    protected $currency;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 1,
        ]);

        // Create test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'user_type' => 'admin',
            'tenant_id' => $this->tenant->id,
            'status' => 1,
        ]);

        // Create test currency
        $this->currency = Currency::create([
            'full_name' => 'Test Currency',
            'name' => 'TC',
            'exchange_rate' => 1.0,
            'base_currency' => 1,
            'status' => 1,
            'tenant_id' => $this->tenant->id,
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
        ]);

        // Create test savings product
        $this->savingsProduct = SavingsProduct::create([
            'name' => 'Test Savings Product',
            'account_number_prefix' => 'TEST',
            'starting_account_number' => 1000,
            'currency_id' => $this->currency->id,
            'interest_rate' => 5.0,
            'allow_withdraw' => 1,
            'minimum_account_balance' => 0,
            'minimum_deposit_amount' => 100,
            'auto_create' => 0,
            'status' => 1,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function test_savings_accounts_table_loads_without_ambiguous_column_error()
    {
        // Create a test savings account
        $savingsAccount = SavingsAccount::create([
            'account_number' => 'TEST1001',
            'member_id' => $this->member->id,
            'savings_product_id' => $this->savingsProduct->id,
            'status' => 1,
            'opening_balance' => 1000,
            'description' => 'Test account',
            'tenant_id' => $this->tenant->id,
            'created_user_id' => $this->user->id,
        ]);

        // Login as admin user
        Auth::login($this->user);

        // Test the table data endpoint
        $response = $this->get(route('savings_accounts.get_table_data', ['tenant' => $this->tenant->slug]));

        // Should return successful response without SQL errors
        $this->assertEquals(200, $response->status());
        
        // Should return JSON data
        $this->assertJson($response->content());
        
        $data = json_decode($response->content(), true);
        
        // Should have the expected structure
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('recordsTotal', $data);
        $this->assertArrayHasKey('recordsFiltered', $data);
        
        // Should contain our test account
        $this->assertCount(1, $data['data']);
        $this->assertEquals('TEST1001', $data['data'][0]['account_number']);
        $this->assertEquals('Test Member', $data['data'][0]['member_first_name']);
        $this->assertEquals('Test Savings Product', $data['data'][0]['savings_type_name']);
    }

    /** @test */
    public function test_savings_accounts_table_with_multiple_status_columns()
    {
        // Create multiple accounts with different statuses
        SavingsAccount::create([
            'account_number' => 'TEST1002',
            'member_id' => $this->member->id,
            'savings_product_id' => $this->savingsProduct->id,
            'status' => 1, // Active
            'opening_balance' => 2000,
            'tenant_id' => $this->tenant->id,
            'created_user_id' => $this->user->id,
        ]);

        SavingsAccount::create([
            'account_number' => 'TEST1003',
            'member_id' => $this->member->id,
            'savings_product_id' => $this->savingsProduct->id,
            'status' => 0, // Inactive
            'opening_balance' => 3000,
            'tenant_id' => $this->tenant->id,
            'created_user_id' => $this->user->id,
        ]);

        // Login as admin user
        Auth::login($this->user);

        // Test the table data endpoint
        $response = $this->get(route('savings_accounts.get_table_data', ['tenant' => $this->tenant->slug]));

        // Should return successful response
        $this->assertEquals(200, $response->status());
        
        $data = json_decode($response->content(), true);
        
        // Should only return active accounts (status != 0)
        $this->assertCount(1, $data['data']);
        $this->assertEquals('TEST1002', $data['data'][0]['account_number']);
    }
}
