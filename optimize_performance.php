<?php

/**
 * IntelliCash Performance Optimization Script
 * 
 * This script runs the performance optimizations and tests
 * Run with: php optimize_performance.php
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

echo "🚀 IntelliCash Performance Optimization Script\n";
echo "==============================================\n\n";

// Step 1: Run the optimization command
echo "📊 Running performance optimizations...\n";
try {
    Artisan::call('intellicash:optimize-performance', ['--force' => true]);
    echo "✅ Performance optimizations completed successfully!\n\n";
} catch (Exception $e) {
    echo "❌ Error running optimizations: " . $e->getMessage() . "\n\n";
}

// Step 2: Run performance tests
echo "🧪 Running performance tests...\n";

// Test 1: Database Query Performance
echo "Testing database query performance...\n";
$startTime = microtime(true);

try {
    $result = DB::table('transactions')
        ->join('savings_accounts', 'transactions.savings_account_id', '=', 'savings_accounts.id')
        ->join('members', 'savings_accounts.member_id', '=', 'members.id')
        ->where('transactions.created_at', '>=', now()->subDays(30))
        ->select('transactions.*', DB::raw("CONCAT(members.first_name, ' ', members.last_name) as member_name"))
        ->limit(100)
        ->get();
    
    $executionTime = (microtime(true) - $startTime) * 1000;
    
    if ($executionTime < 1000) {
        echo "✅ Database Query Performance: {$executionTime}ms (PASS)\n";
    } else {
        echo "❌ Database Query Performance: {$executionTime}ms (FAIL - Should be < 1000ms)\n";
    }
} catch (Exception $e) {
    echo "❌ Database Query Performance: ERROR - " . $e->getMessage() . "\n";
}

// Test 2: Cache Performance
echo "Testing cache performance...\n";
$key = 'test_cache_' . uniqid();
$testData = ['test' => 'data', 'timestamp' => now()];

try {
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
        echo "✅ Cache Performance: {$totalTime}ms (EXCELLENT)\n";
    } elseif ($totalTime < 500) {
        echo "✅ Cache Performance: {$totalTime}ms (GOOD)\n";
    } else {
        echo "⚠️  Cache Performance: {$totalTime}ms (NEEDS IMPROVEMENT)\n";
    }
} catch (Exception $e) {
    echo "❌ Cache Performance: ERROR - " . $e->getMessage() . "\n";
}

// Test 3: Memory Usage
echo "Testing memory usage...\n";
$memoryUsage = memory_get_usage(true) / 1024 / 1024; // Convert to MB
echo "📊 Current Memory Usage: {$memoryUsage}MB\n";

if ($memoryUsage < 50) {
    echo "✅ Memory Usage: GOOD\n";
} elseif ($memoryUsage < 100) {
    echo "⚠️  Memory Usage: MODERATE\n";
} else {
    echo "❌ Memory Usage: HIGH - Consider optimization\n";
}

echo "\n🎉 Performance optimization script completed!\n";
echo "📋 Check PERFORMANCE_OPTIMIZATION_GUIDE.md for detailed recommendations.\n";
echo "📄 Performance report saved to storage/logs/performance_optimization_report.json\n";
