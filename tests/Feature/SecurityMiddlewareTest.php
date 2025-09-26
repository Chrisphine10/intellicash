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

class SecurityMiddlewareTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $tenant;
    protected $user;
    protected $member;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_type' => 'admin',
        ]);
        $this->member = Member::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        
        app()->instance('tenant', $this->tenant);
    }

    /** @test */
    public function test_ensure_tenant_isolation_middleware()
    {
        // Test with valid tenant
        Auth::login($this->user);
        
        $response = $this->get('/members');
        $this->assertEquals(200, $response->status());

        // Test with user without tenant_id
        $userWithoutTenant = User::factory()->create([
            'tenant_id' => null,
            'user_type' => 'admin',
        ]);
        
        Auth::login($userWithoutTenant);
        
        $response = $this->get('/members');
        $this->assertEquals(302, $response->status()); // Redirect to login
    }

    /** @test */
    public function test_prevent_global_scope_bypass_middleware()
    {
        Auth::login($this->user);

        // Test with suspicious request data
        $response = $this->post('/members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'withoutGlobalScopes' => true, // Suspicious parameter
        ]);

        // Should log the attempt
        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'global_scope_bypass_attempt',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function test_member_access_control_middleware()
    {
        // Test with admin user
        Auth::login($this->user);
        
        $response = $this->get('/members');
        $this->assertEquals(200, $response->status());

        // Test with regular user
        $regularUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_type' => 'user',
        ]);
        
        Auth::login($regularUser);
        
        $response = $this->get('/members');
        $this->assertEquals(403, $response->status());
    }

    /** @test */
    public function test_rate_limit_security_middleware()
    {
        Auth::login($this->user);

        // Make multiple requests quickly
        $responses = [];
        for ($i = 0; $i < 65; $i++) {
            $responses[] = $this->get('/members');
        }

        // Should eventually hit rate limit
        $lastResponse = end($responses);
        $this->assertEquals(429, $lastResponse->status());
    }

    /** @test */
    public function test_enhanced_csrf_protection_middleware()
    {
        Auth::login($this->user);

        // Test without CSRF token
        $response = $this->post('/members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertEquals(419, $response->status());

        // Test with invalid CSRF token
        $response = $this->post('/members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            '_token' => 'invalid-token',
        ]);

        $this->assertEquals(419, $response->status());
    }

    /** @test */
    public function test_middleware_order_and_execution()
    {
        Auth::login($this->user);

        // Test that middleware execute in correct order
        $response = $this->get('/members');
        
        // Should pass all middleware checks
        $this->assertEquals(200, $response->status());
    }

    /** @test */
    public function test_middleware_logging_security_events()
    {
        Auth::login($this->user);

        // Perform action that should be logged
        $response = $this->delete("/members/{$this->member->id}");

        // Check security logs
        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'member_deleted',
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function test_middleware_handles_exceptions_gracefully()
    {
        Auth::login($this->user);

        // Test with invalid member ID
        $response = $this->get('/members/999999');
        
        // Should return 404, not crash
        $this->assertEquals(404, $response->status());
    }

    /** @test */
    public function test_middleware_performance_impact()
    {
        Auth::login($this->user);

        $startTime = microtime(true);
        
        // Make multiple requests
        for ($i = 0; $i < 10; $i++) {
            $this->get('/members');
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should not significantly impact performance
        $this->assertLessThan(5.0, $executionTime);
    }

    /** @test */
    public function test_middleware_with_different_user_types()
    {
        $userTypes = ['admin', 'user', 'customer'];
        
        foreach ($userTypes as $userType) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'user_type' => $userType,
            ]);
            
            Auth::login($user);
            
            $response = $this->get('/members');
            
            if ($userType === 'admin') {
                $this->assertEquals(200, $response->status());
            } else {
                $this->assertEquals(403, $response->status());
            }
        }
    }

    /** @test */
    public function test_middleware_with_api_requests()
    {
        // Create API token
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Test API request with middleware
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->get('/api/members');

        $this->assertEquals(200, $response->status());
    }
}
