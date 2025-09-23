<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Tests\VSLAUltimateTestWithTenant;

class RunVSLATests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vsla:test {--tenant : Create test tenant and accounts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run VSLA comprehensive tests with optional tenant setup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting VSLA Ultimate Test Suite...');
        
        if ($this->option('tenant')) {
            $this->info('📋 Setting up test tenant, admin user, and member accounts...');
        }

        try {
            // Initialize the VSLA Ultimate Test with tenant support
            $vslaTest = new VSLAUltimateTestWithTenant();
            
            // Run all VSLA tests
            $results = $vslaTest->runAllTests();
            
            // Display results
            $this->info("\n=== VSLA Test Results ===");
            $this->info("📊 Overall Results:");
            $this->info("   - Total Tests: " . $results['overall']['total']);
            $this->info("   - Passed: " . $results['overall']['passed']);
            $this->info("   - Failed: " . $results['overall']['failed']);
            $this->info("   - Success Rate: " . round(($results['overall']['passed'] / $results['overall']['total']) * 100, 2) . "%");
            $this->info("   - Duration: " . $results['duration'] . " seconds\n");
            
            $this->info("📋 Test Categories:");
            foreach ($results['categories'] as $categoryName => $categoryData) {
                $successRate = $categoryData['total'] > 0 ? round(($categoryData['passed'] / $categoryData['total']) * 100, 2) : 0;
                $status = $categoryData['failed'] === 0 ? '✅' : '⚠️';
                $this->info("   $status $categoryName: {$categoryData['passed']}/{$categoryData['total']} passed ({$successRate}%)");
                
                // Show failed tests
                if ($categoryData['failed'] > 0) {
                    foreach ($categoryData['tests'] as $test) {
                        if ($test['status'] === 'FAIL') {
                            $this->error("       - {$test['name']}: {$test['message']}");
                        }
                    }
                }
            }
            
            $this->info("\n=== Test Summary ===");
            if ($results['overall']['failed'] === 0) {
                $this->info("✅ All VSLA tests passed! VSLA module is working correctly.");
            } else {
                $this->warn("⚠️  Some VSLA tests failed. Please review the failed tests above.");
            }
            
            $this->info("\n🌐 Access VSLA tests through security dashboard:");
            $this->info("   - Main Security Testing: " . url('/admin/security/testing'));
            $this->info("   - VSLA Tests Only: " . url('/admin/security/testing/vsla'));
            
            if ($this->option('tenant')) {
                $this->info("\n📋 Test Accounts Created:");
                $this->info("   - Tenant: VSLA Test Tenant (vsla-test-tenant)");
                $this->info("   - Admin User: vsla-test-admin@example.com");
                $this->info("   - Member: vsla-test-member@example.com (John Doe)");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error running VSLA tests: " . $e->getMessage());
            $this->error("🔧 Make sure database is properly configured and accessible.");
            return 1;
        }

        $this->info("\n=== Test Complete ===");
        return 0;
    }
}
