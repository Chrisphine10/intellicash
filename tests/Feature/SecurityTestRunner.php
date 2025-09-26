<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class SecurityTestRunner extends TestCase
{
    use RefreshDatabase;

    /**
     * Run all security tests
     */
    public function test_run_all_security_tests()
    {
        $this->artisan('test', [
            '--filter' => 'Security',
            '--stop-on-failure' => false,
        ])->assertExitCode(0);
    }

    /**
     * Run member account security tests
     */
    public function test_member_account_security()
    {
        $this->artisan('test', [
            '--filter' => 'MemberAccountSecurityTest',
            '--stop-on-failure' => false,
        ])->assertExitCode(0);
    }

    /**
     * Run tenant isolation security tests
     */
    public function test_tenant_isolation_security()
    {
        $this->artisan('test', [
            '--filter' => 'TenantIsolationSecurityTest',
            '--stop-on-failure' => false,
        ])->assertExitCode(0);
    }

    /**
     * Run security middleware tests
     */
    public function test_security_middleware()
    {
        $this->artisan('test', [
            '--filter' => 'SecurityMiddlewareTest',
            '--stop-on-failure' => false,
        ])->assertExitCode(0);
    }

    /**
     * Run security fixes validation tests
     */
    public function test_security_fixes_validation()
    {
        $this->artisan('test', [
            '--filter' => 'SecurityFixesValidationTest',
            '--stop-on-failure' => false,
        ])->assertExitCode(0);
    }

    /**
     * Generate security test report
     */
    public function test_generate_security_report()
    {
        $output = $this->artisan('test', [
            '--filter' => 'Security',
            '--stop-on-failure' => false,
        ])->getOutput();

        // Save report to file
        file_put_contents(
            storage_path('app/security_test_report_' . date('Y-m-d_H-i-s') . '.txt'),
            $output
        );

        $this->assertTrue(true);
    }
}
