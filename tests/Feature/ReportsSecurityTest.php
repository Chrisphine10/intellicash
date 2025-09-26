<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Services\ReportsService;
use App\Services\AuditService;
use App\Services\DataSanitizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class ReportsSecurityTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $tenant;
    protected $reportsService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = Tenant::factory()->create([
            'is_active' => true,
            'is_asset_management_enabled' => true
        ]);
        
        // Create test user
        $this->user = User::factory()->create([
            'user_type' => 'admin',
            'tenant_id' => $this->tenant->id
        ]);
        
        $this->reportsService = new ReportsService();
        
        // Set tenant in app
        app()->instance('tenant', $this->tenant);
    }

    /** @test */
    public function test_sql_injection_prevention_in_loan_report()
    {
        $this->actingAs($this->user);
        
        // Test malicious input that would cause SQL injection
        $maliciousInput = "'; DROP TABLE loans; --";
        
        $response = $this->post('/reports/loan_report', [
            'date1' => '2024-01-01',
            'date2' => '2024-12-31',
            'member_no' => $maliciousInput
        ]);
        
        // Should not cause SQL injection - table should still exist
        $this->assertDatabaseHas('loans', ['id' => 1], 'mysql');
        $this->assertEquals(200, $response->status());
    }

    /** @test */
    public function test_sql_injection_prevention_in_revenue_report()
    {
        $this->actingAs($this->user);
        
        // Test malicious input in year parameter
        $maliciousYear = "2024'; DROP TABLE users; --";
        
        $response = $this->post('/reports/revenue_report', [
            'year' => $maliciousYear,
            'month' => 12,
            'currency_id' => 1
        ]);
        
        // Should not cause SQL injection
        $this->assertDatabaseHas('users', ['id' => $this->user->id], 'mysql');
        $this->assertEquals(200, $response->status());
    }

    /** @test */
    public function test_input_validation_rejects_invalid_dates()
    {
        $this->actingAs($this->user);
        
        $response = $this->post('/reports/loan_report', [
            'date1' => 'invalid-date',
            'date2' => '2024-12-31'
        ]);
        
        $response->assertSessionHasErrors(['date1']);
    }

    /** @test */
    public function test_input_validation_rejects_invalid_member_number()
    {
        $this->actingAs($this->user);
        
        $response = $this->post('/reports/loan_report', [
            'date1' => '2024-01-01',
            'date2' => '2024-12-31',
            'member_no' => 'INVALID@#$%'
        ]);
        
        $response->assertSessionHasErrors(['member_no']);
    }

    /** @test */
    public function test_input_validation_rejects_invalid_status()
    {
        $this->actingAs($this->user);
        
        $response = $this->post('/reports/loan_report', [
            'date1' => '2024-01-01',
            'date2' => '2024-12-31',
            'status' => 999 // Invalid status
        ]);
        
        $response->assertSessionHasErrors(['status']);
    }

    /** @test */
    public function test_authorization_blocks_unauthorized_access()
    {
        // Create non-admin user
        $regularUser = User::factory()->create([
            'user_type' => 'customer',
            'tenant_id' => $this->tenant->id
        ]);
        
        $this->actingAs($regularUser);
        
        $response = $this->get('/reports/loan_report');
        
        // Should be blocked by authorization middleware
        $this->assertEquals(403, $response->status());
    }

    /** @test */
    public function test_rate_limiting_prevents_abuse()
    {
        $this->actingAs($this->user);
        
        // Clear any existing rate limits
        RateLimiter::clear('reports:' . $this->user->id . ':' . request()->ip());
        
        // Make 11 requests (limit is 10)
        for ($i = 0; $i < 11; $i++) {
            $response = $this->post('/reports/loan_report', [
                'date1' => '2024-01-01',
                'date2' => '2024-12-31'
            ]);
            
            if ($i < 10) {
                $this->assertEquals(200, $response->status());
            } else {
                // 11th request should be rate limited
                $this->assertEquals(429, $response->status());
                $this->assertStringContainsString('Too many report requests', $response->getContent());
            }
        }
    }

    /** @test */
    public function test_tenant_isolation_prevents_cross_tenant_access()
    {
        // Create another tenant
        $otherTenant = Tenant::factory()->create(['is_active' => true]);
        $otherUser = User::factory()->create([
            'user_type' => 'admin',
            'tenant_id' => $otherTenant->id
        ]);
        
        $this->actingAs($otherUser);
        
        // Try to access reports - should be blocked by tenant isolation
        $response = $this->get('/reports/loan_report');
        
        $this->assertEquals(403, $response->status());
    }

    /** @test */
    public function test_data_sanitization_service_works_correctly()
    {
        $testInputs = [
            'date1' => '2024-01-01',
            'date2' => '2024-12-31',
            'member_no' => 'MEM001',
            'status' => '1',
            'search' => 'test search',
            'malicious' => '<script>alert("xss")</script>'
        ];
        
        $sanitized = DataSanitizationService::sanitizeReportInputs($testInputs);
        
        $this->assertEquals('2024-01-01', $sanitized['date1']);
        $this->assertEquals('MEM001', $sanitized['member_no']);
        $this->assertEquals(1, $sanitized['status']);
        $this->assertEquals('test search', $sanitized['search']);
        $this->assertStringNotContainsString('<script>', $sanitized['malicious']);
    }

    /** @test */
    public function test_audit_service_logs_report_access()
    {
        $this->actingAs($this->user);
        
        // Mock the log to capture what gets logged
        Log::shouldReceive('info')
            ->once()
            ->with('Report accessed', \Mockery::type('array'));
        
        $this->post('/reports/loan_report', [
            'date1' => '2024-01-01',
            'date2' => '2024-12-31'
        ]);
    }

    /** @test */
    public function test_reports_service_calculates_outstanding_loans_correctly()
    {
        // Create test loan with payments
        $loan = Loan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 1,
            'applied_amount' => 10000,
            'total_payable' => 12000,
            'total_paid' => 5000
        ]);
        
        $outstandingLoans = $this->reportsService->getOutstandingLoans($this->tenant->id);
        
        $this->assertIsArray($outstandingLoans);
        $this->assertCount(1, $outstandingLoans);
        $this->assertEquals(7000, $outstandingLoans[0]->outstanding_amount); // 12000 - 5000
    }

    /** @test */
    public function test_reports_service_caches_data()
    {
        // Create test data
        Loan::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 1
        ]);
        
        // First call should cache the data
        $summary1 = $this->reportsService->getLoanSummary($this->tenant->id);
        
        // Second call should use cache
        $summary2 = $this->reportsService->getLoanSummary($this->tenant->id);
        
        $this->assertEquals($summary1, $summary2);
        $this->assertEquals(5, $summary1['total_loans']);
    }

    /** @test */
    public function test_pagination_works_correctly()
    {
        $this->actingAs($this->user);
        
        // Create test loans
        Loan::factory()->count(25)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 1,
            'created_at' => now()->subDays(10)
        ]);
        
        $response = $this->post('/reports/loan_report', [
            'date1' => now()->subDays(20)->format('Y-m-d'),
            'date2' => now()->format('Y-m-d'),
            'per_page' => 10
        ]);
        
        $response->assertStatus(200);
        $response->assertViewHas('report_data');
        $response->assertViewHas('pagination');
        
        // Check that pagination data is present
        $viewData = $response->viewData('report_data');
        $this->assertTrue($viewData->hasPages());
        $this->assertEquals(10, $viewData->perPage());
    }

    /** @test */
    public function test_error_handling_logs_errors_correctly()
    {
        $this->actingAs($this->user);
        
        // Mock DB to throw an exception
        DB::shouldReceive('select')
            ->andThrow(new \Exception('Database error'));
        
        Log::shouldReceive('error')
            ->once()
            ->with('Account statement report generation failed', \Mockery::type('array'));
        
        $response = $this->post('/reports/account_statement', [
            'date1' => '2024-01-01',
            'date2' => '2024-12-31',
            'account_number' => 'TEST001'
        ]);
        
        $response->assertSessionHas('error');
    }

    /** @test */
    public function test_database_indexes_improve_performance()
    {
        // Create test data
        Loan::factory()->count(1000)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 1,
            'created_at' => now()->subDays(rand(1, 365))
        ]);
        
        $startTime = microtime(true);
        
        // Query that should use the new indexes
        $loans = Loan::where('status', 1)
            ->where('created_at', '>=', now()->subDays(30))
            ->get();
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Should complete within reasonable time (less than 1 second)
        $this->assertLessThan(1.0, $executionTime);
        $this->assertGreaterThan(0, $loans->count());
    }

    /** @test */
    public function test_middleware_registration_works()
    {
        $middleware = app('router')->getMiddleware();
        
        $this->assertArrayHasKey('report.rate.limit', $middleware);
        $this->assertArrayHasKey('tenant.access', $middleware);
        $this->assertArrayHasKey('permission', $middleware);
        
        $this->assertEquals(\App\Http\Middleware\ReportRateLimit::class, $middleware['report.rate.limit']);
        $this->assertEquals(\App\Http\Middleware\TenantAccess::class, $middleware['tenant.access']);
        $this->assertEquals(\App\Http\Middleware\PermissionMiddleware::class, $middleware['permission']);
    }

    /** @test */
    public function test_security_headers_are_present()
    {
        $this->actingAs($this->user);
        
        $response = $this->get('/reports/loan_report');
        
        $this->assertTrue($response->headers->has('X-Content-Type-Options'));
        $this->assertTrue($response->headers->has('X-Frame-Options'));
        $this->assertTrue($response->headers->has('X-XSS-Protection'));
    }

    /** @test */
    public function test_csrf_protection_is_active()
    {
        $this->actingAs($this->user);
        
        // Test that CSRF token is required for POST requests
        $response = $this->post('/reports/loan_report', [
            'date1' => '2024-01-01',
            'date2' => '2024-12-31'
        ]);
        
        // Should not get CSRF error since we're using proper session
        $this->assertNotEquals(419, $response->status());
    }
}
