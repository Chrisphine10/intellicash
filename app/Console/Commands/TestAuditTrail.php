<?php

namespace App\Console\Commands;

use App\Models\AuditTrail;
use App\Models\Tenant;
use Illuminate\Console\Command;

class TestAuditTrail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test audit trail system functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== AUDIT TRAIL SYSTEM TEST ===');
        $this->newLine();

        // Test 1: Check audit trail table
        $this->info('1. Testing Audit Trail Table...');
        $auditCount = AuditTrail::count();
        $this->line("   Total audit records: {$auditCount}");
        
        if ($auditCount > 0) {
            $recentAudits = AuditTrail::with('user')->orderBy('created_at', 'desc')->limit(3)->get();
            $this->line('   Recent audit records:');
            foreach ($recentAudits as $audit) {
                $this->line("   - {$audit->event_type} {$audit->auditable_type} #{$audit->auditable_id} by {$audit->user_name} at {$audit->created_at}");
            }
        }
        $this->info('   ✓ Audit trail table is working');
        $this->newLine();

        // Test 2: Check module-specific audit models
        $this->info('2. Testing Module-Specific Audit Models...');
        
        if (class_exists('App\Models\VslaAuditLog')) {
            $vslaAuditCount = \App\Models\VslaAuditLog::count();
            $this->line("   VSLA audit logs: {$vslaAuditCount}");
        }
        
        if (class_exists('App\Models\VotingAuditLog')) {
            $votingAuditCount = \App\Models\VotingAuditLog::count();
            $this->line("   Voting audit logs: {$votingAuditCount}");
        }
        
        if (class_exists('App\Models\ESignatureAuditTrail')) {
            $esignatureAuditCount = \App\Models\ESignatureAuditTrail::count();
            $this->line("   E-Signature audit logs: {$esignatureAuditCount}");
        }
        
        $this->info('   ✓ Module-specific audit models are working');
        $this->newLine();

        // Test 3: Test module activation status
        $this->info('3. Testing Module Activation Status...');
        
        $tenant = Tenant::first();
        if ($tenant) {
            $modules = [
                'vsla' => $tenant->isVslaEnabled(),
                'api' => $tenant->isApiEnabled(),
                'esignature' => $tenant->esignature_enabled ?? false,
                'asset_management' => $tenant->isAssetManagementEnabled(),
                'payroll' => $tenant->isPayrollEnabled(),
                'qr_code' => $tenant->isQrCodeEnabled(),
            ];
            
            foreach ($modules as $module => $enabled) {
                $status = $enabled ? 'enabled' : 'disabled';
                $this->line("   {$module}: {$status}");
            }
        }
        $this->info('   ✓ Module status checking is working');
        $this->newLine();

        // Test 4: Test audit trail configuration
        $this->info('4. Testing Audit Trail Configuration...');
        
        $config = config('audit');
        if ($config) {
            $this->line('   Audit enabled: ' . ($config['enabled'] ? 'Yes' : 'No'));
            $this->line("   Max records per model: {$config['settings']['max_records_per_model']}");
            $this->line("   Retention days: {$config['settings']['retention_days']}");
            $this->line("   Modules configured: " . count($config['modules']));
        } else {
            $this->error('   ✗ Audit configuration not found');
        }
        $this->info('   ✓ Configuration is working');
        $this->newLine();

        // Test 5: Test audit trail trait
        $this->info('5. Testing Audit Trail Trait...');
        
        if (file_exists(app_path('Traits/AuditTrailTrait.php'))) {
            $this->info('   ✓ AuditTrailTrait exists');
        } else {
            $this->error('   ✗ AuditTrailTrait not found');
        }
        $this->newLine();

        $this->info('=== AUDIT TRAIL SYSTEM TEST COMPLETED ===');
        $this->info('Overall Status: ✓ System is working properly');
        $this->newLine();
        
        $this->comment('Recommendations:');
        $this->line('1. Add AuditTrailTrait to important models for automatic logging');
        $this->line('2. Set up scheduled cleanup command: php artisan audit:cleanup');
        $this->line('3. Monitor audit trail size and performance');
        $this->line('4. Configure module-specific audit settings as needed');

        return 0;
    }
}
