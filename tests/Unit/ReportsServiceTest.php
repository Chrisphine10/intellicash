<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\ReportsService;
use App\Services\AuditService;
use App\Services\DataSanitizationService;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Transaction;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReportsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $reportsService;
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->reportsService = new ReportsService();
        $this->tenant = Tenant::factory()->create(['is_active' => true]);
    }

    /** @test */
    public function test_get_loan_summary_returns_correct_data()
    {
        // Create test loans
        Loan::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 1,
            'applied_amount' => 10000
        ]);
        
        Loan::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 2,
            'applied_amount' => 5000
        ]);
        
        $summary = $this->reportsService->getLoanSummary($this->tenant->id);
        
        $this->assertEquals(8, $summary['total_loans']);
        $this->assertEquals(65000, $summary['total_amount']); // 5*10000 + 3*5000
        $this->assertEquals(8125, $summary['average_amount']); // 65000/8
    }

    /** @test */
    public function test_get_loan_summary_with_filters()
    {
        // Create test loans
        Loan::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 1,
            'created_at' => now()->subDays(10)
        ]);
        
        Loan::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 1,
            'created_at' => now()->subDays(40)
        ]);
        
        $filters = [
            'status' => 1,
            'date_from' => now()->subDays(20)->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d')
        ];
        
        $summary = $this->reportsService->getLoanSummary($this->tenant->id, $filters);
        
        $this->assertEquals(3, $summary['total_loans']);
    }

    /** @test */
    public function test_get_outstanding_loans_calculates_correctly()
    {
        // Create loan with payments
        $loan = Loan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 1,
            'applied_amount' => 10000,
            'total_payable' => 12000,
            'total_paid' => 5000
        ]);
        
        // Create loan payments
        LoanPayment::factory()->create([
            'loan_id' => $loan->id,
            'interest' => 1000
        ]);
        
        $outstandingLoans = $this->reportsService->getOutstandingLoans($this->tenant->id);
        
        $this->assertCount(1, $outstandingLoans);
        $this->assertEquals(6000, $outstandingLoans[0]->outstanding_amount); // 12000 - (5000 + 1000)
    }

    /** @test */
    public function test_get_cash_in_hand_calculates_correctly()
    {
        // Create cash transactions
        Transaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'method' => 'cash',
            'dr_cr' => 'cr',
            'status' => 2,
            'amount' => 5000,
            'trans_date' => now()->subDays(5)
        ]);
        
        Transaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'method' => 'cash',
            'dr_cr' => 'dr',
            'status' => 2,
            'amount' => 2000,
            'trans_date' => now()->subDays(3)
        ]);
        
        $cashInHand = $this->reportsService->getCashInHand($this->tenant->id);
        
        $this->assertEquals(3000, $cashInHand); // 5000 - 2000
    }

    /** @test */
    public function test_get_retained_earnings_calculates_correctly()
    {
        // Create loan payments (revenue)
        LoanPayment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'interest' => 1000,
            'late_penalties' => 200,
            'paid_at' => now()->subDays(5)
        ]);
        
        // Create transaction charges (revenue)
        Transaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'charge' => 100,
            'status' => 2,
            'trans_date' => now()->subDays(3)
        ]);
        
        // Create expenses
        Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => 500,
            'expense_date' => now()->subDays(2)
        ]);
        
        $retainedEarnings = $this->reportsService->getRetainedEarnings($this->tenant->id);
        
        $this->assertEquals(800, $retainedEarnings); // (1000 + 200 + 100) - 500
    }

    /** @test */
    public function test_get_balance_sheet_data_returns_complete_data()
    {
        // Create test data
        Transaction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'method' => 'cash',
            'dr_cr' => 'cr',
            'status' => 2,
            'amount' => 10000
        ]);
        
        Loan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 1,
            'total_payable' => 5000,
            'total_paid' => 2000
        ]);
        
        $balanceSheet = $this->reportsService->getBalanceSheetData($this->tenant->id);
        
        $this->assertArrayHasKey('assets', $balanceSheet);
        $this->assertArrayHasKey('liabilities', $balanceSheet);
        $this->assertArrayHasKey('equity', $balanceSheet);
        $this->assertArrayHasKey('total_assets', $balanceSheet);
        $this->assertArrayHasKey('total_liabilities', $balanceSheet);
        $this->assertArrayHasKey('total_equity', $balanceSheet);
        
        $this->assertGreaterThan(0, $balanceSheet['total_assets']);
    }

    /** @test */
    public function test_caching_works_correctly()
    {
        // Clear cache first
        Cache::flush();
        
        // Create test data
        Loan::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 1
        ]);
        
        // First call
        $summary1 = $this->reportsService->getLoanSummary($this->tenant->id);
        
        // Modify data
        Loan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 1
        ]);
        
        // Second call should return cached data
        $summary2 = $this->reportsService->getLoanSummary($this->tenant->id);
        
        $this->assertEquals($summary1, $summary2);
        $this->assertEquals(3, $summary1['total_loans']); // Should still be 3, not 4
    }

    /** @test */
    public function test_clear_cache_works()
    {
        // Create test data and cache it
        Loan::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 1]);
        $this->reportsService->getLoanSummary($this->tenant->id);
        
        // Clear cache
        $this->reportsService->clearCache($this->tenant->id);
        
        // Add more data
        Loan::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 1]);
        
        // Should now return fresh data
        $summary = $this->reportsService->getLoanSummary($this->tenant->id);
        $this->assertEquals(2, $summary['total_loans']);
    }
}

class DataSanitizationServiceTest extends TestCase
{
    /** @test */
    public function test_sanitize_date_handles_valid_dates()
    {
        $validDate = '2024-12-20';
        $sanitized = DataSanitizationService::sanitizeReportInputs(['date1' => $validDate]);
        
        $this->assertEquals('2024-12-20', $sanitized['date1']);
    }

    /** @test */
    public function test_sanitize_date_handles_invalid_dates()
    {
        $invalidDate = 'invalid-date';
        $sanitized = DataSanitizationService::sanitizeReportInputs(['date1' => $invalidDate]);
        
        $this->assertNull($sanitized['date1']);
    }

    /** @test */
    public function test_sanitize_member_number_removes_invalid_characters()
    {
        $memberNo = 'MEM@001#';
        $sanitized = DataSanitizationService::sanitizeReportInputs(['member_no' => $memberNo]);
        
        $this->assertEquals('MEM001', $sanitized['member_no']);
    }

    /** @test */
    public function test_sanitize_integer_handles_valid_integers()
    {
        $validInt = '123';
        $sanitized = DataSanitizationService::sanitizeReportInputs(['status' => $validInt]);
        
        $this->assertEquals(123, $sanitized['status']);
    }

    /** @test */
    public function test_sanitize_integer_handles_invalid_integers()
    {
        $invalidInt = 'abc';
        $sanitized = DataSanitizationService::sanitizeReportInputs(['status' => $invalidInt]);
        
        $this->assertNull($sanitized['status']);
    }

    /** @test */
    public function test_sanitize_search_term_removes_dangerous_characters()
    {
        $searchTerm = 'test<script>alert("xss")</script>';
        $sanitized = DataSanitizationService::sanitizeReportInputs(['search' => $searchTerm]);
        
        $this->assertEquals('testalert("xss")', $sanitized['search']);
    }

    /** @test */
    public function test_sanitize_string_escapes_html()
    {
        $htmlString = '<script>alert("xss")</script>';
        $sanitized = DataSanitizationService::sanitizeReportInputs(['description' => $htmlString]);
        
        $this->assertStringNotContainsString('<script>', $sanitized['description']);
        $this->assertStringContainsString('&lt;script&gt;', $sanitized['description']);
    }

    /** @test */
    public function test_sanitize_email_handles_valid_emails()
    {
        $validEmail = 'test@example.com';
        $sanitized = DataSanitizationService::sanitizeEmail($validEmail);
        
        $this->assertEquals('test@example.com', $sanitized);
    }

    /** @test */
    public function test_sanitize_email_handles_invalid_emails()
    {
        $invalidEmail = 'not-an-email';
        $sanitized = DataSanitizationService::sanitizeEmail($invalidEmail);
        
        $this->assertNull($sanitized);
    }

    /** @test */
    public function test_sanitize_amount_handles_valid_amounts()
    {
        $validAmount = '$1,234.56';
        $sanitized = DataSanitizationService::sanitizeAmount($validAmount);
        
        $this->assertEquals(1234.56, $sanitized);
    }

    /** @test */
    public function test_sanitize_amount_handles_invalid_amounts()
    {
        $invalidAmount = 'not-a-number';
        $sanitized = DataSanitizationService::sanitizeAmount($invalidAmount);
        
        $this->assertNull($sanitized);
    }

    /** @test */
    public function test_sanitize_array_works_correctly()
    {
        $values = ['123', '456', 'abc', '789'];
        $sanitized = DataSanitizationService::sanitizeArray($values, 'integer');
        
        $this->assertCount(3, $sanitized); // Only valid integers
        $this->assertEquals([123, 456, 789], array_values($sanitized));
    }
}

class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create(['is_active' => true]);
        $this->user = User::factory()->create([
            'user_type' => 'admin',
            'tenant_id' => $this->tenant->id
        ]);
        
        app()->instance('tenant', $this->tenant);
    }

    /** @test */
    public function test_log_report_access_logs_correctly()
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Report accessed', \Mockery::on(function ($data) {
                return $data['user_id'] === $this->user->id &&
                       $data['tenant_id'] === $this->tenant->id &&
                       $data['report_type'] === 'loan_report' &&
                       isset($data['parameters']) &&
                       isset($data['ip_address']) &&
                       isset($data['timestamp']);
            }));
        
        AuditService::logReportAccess('loan_report', ['date1' => '2024-01-01']);
    }

    /** @test */
    public function test_log_security_event_logs_correctly()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Security event detected', \Mockery::on(function ($data) {
                return $data['event'] === 'sql_injection_attempt' &&
                       isset($data['user_id']) &&
                       isset($data['tenant_id']) &&
                       isset($data['ip_address']);
            }));
        
        AuditService::logSecurityEvent('sql_injection_attempt', ['input' => 'malicious']);
    }

    /** @test */
    public function test_log_failed_auth_logs_correctly()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Failed authentication attempt', \Mockery::on(function ($data) {
                return $data['email'] === 'test@example.com' &&
                       $data['reason'] === 'Invalid credentials' &&
                       isset($data['ip_address']);
            }));
        
        AuditService::logFailedAuth('test@example.com', 'Invalid credentials');
    }

    /** @test */
    public function test_log_permission_denied_logs_correctly()
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Permission denied', \Mockery::on(function ($data) {
                return $data['action'] === 'reports.view' &&
                       $data['resource'] === 'loan_report' &&
                       isset($data['user_id']) &&
                       isset($data['tenant_id']);
            }));
        
        AuditService::logPermissionDenied('reports.view', 'loan_report');
    }

    /** @test */
    public function test_log_data_modification_logs_correctly()
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Data modification', \Mockery::on(function ($data) {
                return $data['table'] === 'loans' &&
                       $data['action'] === 'update' &&
                       isset($data['old_data']) &&
                       isset($data['new_data']) &&
                       isset($data['user_id']);
            }));
        
        AuditService::logDataModification('loans', 'update', ['status' => 0], ['status' => 1]);
    }

    /** @test */
    public function test_log_system_error_logs_correctly()
    {
        Log::shouldReceive('error')
            ->once()
            ->with('System error', \Mockery::on(function ($data) {
                return $data['error'] === 'Database connection failed' &&
                       $data['context'] === 'Report generation' &&
                       isset($data['user_id']) &&
                       isset($data['tenant_id']);
            }));
        
        AuditService::logSystemError('Database connection failed', 'Report generation');
    }
}
