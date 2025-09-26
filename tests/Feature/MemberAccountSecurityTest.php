<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Models\SavingsProduct;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class MemberAccountSecurityTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $tenant;
    protected $user;
    protected $member;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create test user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_type' => 'admin',
        ]);
        
        // Create test member
        $this->member = Member::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        
        // Set tenant context
        app()->instance('tenant', $this->tenant);
    }

    /** @test */
    public function test_tenant_isolation_prevents_cross_tenant_access()
    {
        // Create another tenant and member
        $otherTenant = Tenant::factory()->create();
        $otherMember = Member::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        // Login as user from first tenant
        Auth::login($this->user);

        // Try to access member from other tenant
        $response = $this->get("/members/{$otherMember->id}");
        
        // Should be denied
        $this->assertEquals(404, $response->status());
    }

    /** @test */
    public function test_global_scope_bypass_prevention()
    {
        // Create transaction with account from different tenant
        $otherTenant = Tenant::factory()->create();
        $otherAccount = SavingsAccount::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $transaction = Transaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'savings_account_id' => $otherAccount->id,
        ]);

        // Try to access account through transaction
        $account = $transaction->account;
        
        // Should return default empty model, not the other tenant's account
        $this->assertTrue($account->exists === false);
    }

    /** @test */
    public function test_mass_assignment_protection()
    {
        Auth::login($this->user);

        $maliciousData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'tenant_id' => 999, // Try to switch tenant
            'user_id' => 999,   // Try to change user
            'status' => 0,      // Try to deactivate
            'is_vsla_chairperson' => true, // Try privilege escalation
        ];

        $response = $this->put("/members/{$this->member->id}", $maliciousData);

        // Refresh member from database
        $this->member->refresh();

        // Sensitive fields should not be changed
        $this->assertEquals($this->tenant->id, $this->member->tenant_id);
        $this->assertNotEquals(999, $this->member->user_id);
        $this->assertEquals(1, $this->member->status);
        $this->assertFalse($this->member->is_vsla_chairperson);
    }

    /** @test */
    public function test_sql_injection_protection_in_helpers()
    {
        // Test get_account_balance with malicious input
        $maliciousId = "1; DROP TABLE members; --";
        
        $this->expectException(\InvalidArgumentException::class);
        get_account_balance($maliciousId, $this->member->id);
    }

    /** @test */
    public function test_rate_limiting_protection()
    {
        Auth::login($this->user);

        // Make multiple rapid requests
        for ($i = 0; $i < 65; $i++) {
            $response = $this->get('/members');
            if ($response->status() === 429) {
                break;
            }
        }

        // Should eventually hit rate limit
        $this->assertEquals(429, $response->status());
    }

    /** @test */
    public function test_csrf_protection()
    {
        Auth::login($this->user);

        // Try to create member without CSRF token
        $response = $this->post('/members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        // Should be denied
        $this->assertEquals(419, $response->status());
    }

    /** @test */
    public function test_race_condition_prevention_in_account_creation()
    {
        Auth::login($this->user);

        // Create savings product with auto-create enabled
        $product = SavingsProduct::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auto_create' => 1,
            'account_number_prefix' => 'ACC',
            'starting_account_number' => 1000,
        ]);

        // Simulate concurrent account creation
        $member1 = Member::factory()->create(['tenant_id' => $this->tenant->id]);
        $member2 = Member::factory()->create(['tenant_id' => $this->tenant->id]);

        // Create accounts concurrently
        DB::transaction(function () use ($member1, $product) {
            $account = new SavingsAccount();
            $account->account_number = $product->account_number_prefix . $product->starting_account_number;
            $account->member_id = $member1->id;
            $account->savings_product_id = $product->id;
            $account->tenant_id = $this->tenant->id;
            $account->save();

            $product->starting_account_number++;
            $product->save();
        });

        DB::transaction(function () use ($member2, $product) {
            $account = new SavingsAccount();
            $account->account_number = $product->account_number_prefix . $product->starting_account_number;
            $account->member_id = $member2->id;
            $account->savings_product_id = $product->id;
            $account->tenant_id = $this->tenant->id;
            $account->save();

            $product->starting_account_number++;
            $product->save();
        });

        // Check that account numbers are unique
        $accounts = SavingsAccount::where('savings_product_id', $product->id)->get();
        $accountNumbers = $accounts->pluck('account_number')->toArray();
        
        $this->assertEquals(count($accountNumbers), count(array_unique($accountNumbers)));
    }

    /** @test */
    public function test_authorization_checks_in_member_controller()
    {
        // Create regular user (not admin)
        $regularUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_type' => 'user',
        ]);

        Auth::login($regularUser);

        // Try to access member management
        $response = $this->get('/members');
        
        // Should be denied
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    /** @test */
    public function test_input_validation_security()
    {
        Auth::login($this->user);

        $maliciousInput = [
            'first_name' => '<script>alert("xss")</script>',
            'last_name' => 'Doe',
            'email' => 'invalid-email',
            'mobile' => '123', // Too short
        ];

        $response = $this->post('/members', $maliciousInput);

        // Should fail validation
        $this->assertTrue($response->status() === 422 || $response->status() === 302);
    }

    /** @test */
    public function test_audit_logging_security_events()
    {
        Auth::login($this->user);

        // Perform sensitive operation
        $response = $this->delete("/members/{$this->member->id}");

        // Check that security event was logged
        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'member_deleted',
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function test_secure_file_upload()
    {
        Auth::login($this->user);

        // Try to upload malicious file
        $maliciousFile = $this->createFakeFile('test.php', '<?php system($_GET["cmd"]); ?>');

        $response = $this->post("/members/{$this->member->id}", [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'photo' => $maliciousFile,
        ]);

        // Should be rejected
        $this->assertTrue($response->status() === 422 || $response->status() === 400);
    }

    /** @test */
    public function test_session_security()
    {
        Auth::login($this->user);

        // Check session configuration
        $sessionConfig = config('session');
        
        $this->assertTrue($sessionConfig['secure'] ?? false);
        $this->assertTrue($sessionConfig['http_only'] ?? false);
        $this->assertEquals('strict', $sessionConfig['same_site'] ?? 'lax');
    }

    private function createFakeFile($filename, $content)
    {
        $file = tmpfile();
        fwrite($file, $content);
        $path = stream_get_meta_data($file)['uri'];
        
        return new \Illuminate\Http\UploadedFile(
            $path,
            $filename,
            'text/plain',
            null,
            true
        );
    }
}
