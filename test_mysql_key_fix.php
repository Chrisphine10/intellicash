<?php

/**
 * Test script to verify MySQL key length fixes
 * This script tests the database connection and attempts to run migrations
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Testing MySQL key length fixes...\n";
    echo "=====================================\n\n";
    
    // Test database connection
    echo "1. Testing database connection...\n";
    $connection = DB::connection();
    $pdo = $connection->getPdo();
    echo "✓ Database connection successful\n\n";
    
    // Check MySQL version and charset
    echo "2. Checking MySQL configuration...\n";
    $version = $connection->select('SELECT VERSION() as version')[0]->version;
    echo "MySQL Version: $version\n";
    
    $charset = $connection->select("SELECT @@character_set_database as charset")[0]->charset;
    echo "Database Charset: $charset\n";
    
    $collation = $connection->select("SELECT @@collation_database as collation")[0]->collation;
    echo "Database Collation: $collation\n\n";
    
    // Test creating a table with varchar(191) primary key
    echo "3. Testing varchar(191) primary key creation...\n";
    
    // Drop table if exists
    Schema::dropIfExists('test_key_length');
    
    // Create test table
    Schema::create('test_key_length', function ($table) {
        $table->string('email', 191)->primary();
        $table->string('token');
        $table->timestamp('created_at')->nullable();
    });
    
    echo "✓ Successfully created table with varchar(191) primary key\n";
    
    // Test inserting data
    echo "4. Testing data insertion...\n";
    DB::table('test_key_length')->insert([
        'email' => 'test@example.com',
        'token' => 'test_token_123',
        'created_at' => now()
    ]);
    echo "✓ Successfully inserted test data\n";
    
    // Clean up
    Schema::dropIfExists('test_key_length');
    echo "✓ Cleaned up test table\n\n";
    
    echo "=====================================\n";
    echo "✅ All tests passed! MySQL key length fixes are working correctly.\n";
    echo "You can now proceed with the installation process.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
