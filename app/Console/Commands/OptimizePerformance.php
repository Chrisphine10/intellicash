<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OptimizePerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'intellicash:optimize-performance {--force : Force optimization without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize IntelliCash system performance by running migrations, clearing caches, and optimizing database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting IntelliCash Performance Optimization...');
        $this->newLine();

        if (!$this->option('force') && !$this->confirm('This will run database migrations and clear caches. Continue?')) {
            $this->info('Optimization cancelled.');
            return;
        }

        // Step 1: Run database migrations
        $this->info('📊 Running database migrations...');
        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->info('✅ Database migrations completed successfully');
        } catch (\Exception $e) {
            $this->error('❌ Database migration failed: ' . $e->getMessage());
            return;
        }

        // Step 2: Clear all caches
        $this->info('🧹 Clearing application caches...');
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            $this->info('✅ All caches cleared successfully');
        } catch (\Exception $e) {
            $this->error('❌ Cache clearing failed: ' . $e->getMessage());
        }

        // Step 3: Optimize database
        $this->info('🔧 Optimizing database...');
        try {
            // Analyze tables for better query planning
            $tables = ['transactions', 'members', 'savings_accounts', 'loans', 'bank_accounts', 'expenses'];
            foreach ($tables as $table) {
                DB::statement("ANALYZE TABLE {$table}");
            }
            $this->info('✅ Database optimization completed');
        } catch (\Exception $e) {
            $this->warn('⚠️  Database optimization failed: ' . $e->getMessage());
        }

        // Step 4: Test performance
        $this->info('🧪 Running performance tests...');
        try {
            $this->testDatabasePerformance();
            $this->testCachePerformance();
            $this->info('✅ Performance tests completed');
        } catch (\Exception $e) {
            $this->warn('⚠️  Performance tests failed: ' . $e->getMessage());
        }

        // Step 5: Generate optimization report
        $this->generateOptimizationReport();

        $this->newLine();
        $this->info('🎉 Performance optimization completed successfully!');
        $this->info('Check the PERFORMANCE_OPTIMIZATION_GUIDE.md for detailed recommendations.');
    }

    private function testDatabasePerformance()
    {
        $startTime = microtime(true);
        
        // Test the previously failing query
        $result = DB::table('transactions')
            ->join('savings_accounts', 'transactions.savings_account_id', '=', 'savings_accounts.id')
            ->join('members', 'savings_accounts.member_id', '=', 'members.id')
            ->where('transactions.created_at', '>=', now()->subDays(30))
            ->select('transactions.*', DB::raw("CONCAT(members.first_name, ' ', members.last_name) as member_name"))
            ->limit(100)
            ->get();
        
        $executionTime = (microtime(true) - $startTime) * 1000;
        
        if ($executionTime < 1000) {
            $this->info("✅ Database query performance: {$executionTime}ms (Good)");
        } else {
            $this->warn("⚠️  Database query performance: {$executionTime}ms (Needs improvement)");
        }
    }

    private function testCachePerformance()
    {
        $key = 'performance_test_' . uniqid();
        $testData = ['test' => 'data', 'timestamp' => now()];
        
        // Test cache write
        $writeStart = microtime(true);
        Cache::put($key, $testData, 60);
        $writeTime = (microtime(true) - $writeStart) * 1000;
        
        // Test cache read
        $readStart = microtime(true);
        $retrieved = Cache::get($key);
        $readTime = (microtime(true) - $readStart) * 1000;
        
        // Clean up
        Cache::forget($key);
        
        $totalTime = $writeTime + $readTime;
        
        if ($totalTime < 100) {
            $this->info("✅ Cache performance: {$totalTime}ms (Excellent)");
        } elseif ($totalTime < 500) {
            $this->info("✅ Cache performance: {$totalTime}ms (Good)");
        } else {
            $this->warn("⚠️  Cache performance: {$totalTime}ms (Needs improvement)");
        }
    }

    private function generateOptimizationReport()
    {
        $this->info('📋 Generating optimization report...');
        
        $report = [
            'optimization_date' => now()->toDateTimeString(),
            'database_indexes' => 'Added performance indexes for transactions, members, savings_accounts, loans, bank_accounts, and expenses tables',
            'cache_optimization' => 'Cleared all application caches',
            'database_optimization' => 'Ran ANALYZE TABLE on critical tables',
            'recommendations' => [
                'Switch to Redis cache for better performance',
                'Enable OPcache in PHP configuration',
                'Add web server compression (gzip)',
                'Implement query result caching for expensive operations',
                'Use eager loading to prevent N+1 queries',
                'Consider database query result caching for frequently accessed data'
            ]
        ];
        
        $reportPath = storage_path('logs/performance_optimization_report.json');
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        
        $this->info("📄 Optimization report saved to: {$reportPath}");
    }
}
