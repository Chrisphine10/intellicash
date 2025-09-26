<?php

namespace App\Console\Commands;

use App\Models\AuditTrail;
use Illuminate\Console\Command;

class CleanupAuditTrails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:cleanup {--days=365 : Number of days to keep audit records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old audit trail records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        
        $this->info("Cleaning up audit trail records older than {$days} days...");
        
        $deletedCount = AuditTrail::cleanupOldRecords($days);
        
        $this->info("Deleted {$deletedCount} old audit trail records.");
        
        return 0;
    }
}
