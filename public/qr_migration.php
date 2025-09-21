<?php
/**
 * QR Code Module Migration - Web Accessible
 * Run this script through your web browser to create the required database tables
 * URL: http://localhost/intellicash/qr_migration.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>QR Code Module Migration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>
    <h1>QR Code Module Migration</h1>
    <p>This script will create the required database tables for the QR Code module.</p>";

try {
    // Include Laravel bootstrap
    require_once '../vendor/autoload.php';
    
    // Load Laravel application
    $app = require_once '../bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
    
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;
    
    echo "<h2>Migration Progress</h2>";
    
    // Check if qr_code_settings table exists
    if (Schema::hasTable('qr_code_settings')) {
        echo "<p class='success'>✓ qr_code_settings table already exists</p>";
    } else {
        echo "<p class='info'>Creating qr_code_settings table...</p>";
        
        // Create qr_code_settings table
        Schema::create('qr_code_settings', function ($table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->boolean('enabled')->default(false);
            $table->boolean('ethereum_enabled')->default(false);
            $table->string('ethereum_network')->default('mainnet');
            $table->string('ethereum_rpc_url')->nullable();
            $table->string('ethereum_contract_address')->nullable();
            $table->string('ethereum_account_address')->nullable();
            $table->text('ethereum_private_key')->nullable();
            $table->integer('qr_code_size')->default(200);
            $table->string('qr_code_error_correction')->default('H');
            $table->integer('verification_cache_days')->default(30);
            $table->boolean('auto_generate_qr')->default(true);
            $table->boolean('include_blockchain_verification')->default(false);
            $table->json('custom_settings')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique('tenant_id');
        });
        
        echo "<p class='success'>✓ qr_code_settings table created</p>";
    }

    // Check if qr_code_enabled column exists in tenants table
    if (Schema::hasColumn('tenants', 'qr_code_enabled')) {
        echo "<p class='success'>✓ qr_code_enabled column already exists in tenants table</p>";
    } else {
        echo "<p class='info'>Adding qr_code_enabled column to tenants table...</p>";
        
        Schema::table('tenants', function ($table) {
            $table->boolean('qr_code_enabled')->default(false)->after('vsla_enabled');
        });
        
        echo "<p class='success'>✓ qr_code_enabled column added to tenants table</p>";
    }

    // Create default QR code settings for existing tenants
    echo "<p class='info'>Creating default QR code settings for existing tenants...</p>";
    
    $tenants = DB::table('tenants')->get();
    $created = 0;
    
    foreach ($tenants as $tenant) {
        $existing = DB::table('qr_code_settings')->where('tenant_id', $tenant->id)->first();
        
        if (!$existing) {
            DB::table('qr_code_settings')->insert([
                'tenant_id' => $tenant->id,
                'enabled' => false,
                'ethereum_enabled' => false,
                'ethereum_network' => 'mainnet',
                'qr_code_size' => 200,
                'qr_code_error_correction' => 'H',
                'verification_cache_days' => 30,
                'auto_generate_qr' => true,
                'include_blockchain_verification' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created++;
        }
    }
    
    echo "<p class='success'>✓ Created default settings for {$created} tenants</p>";

    echo "<h2 class='success'>Migration Completed Successfully!</h2>";
    echo "<p>✓ Database tables created<br>";
    echo "✓ Default settings configured<br>";
    echo "✓ QR Code module is ready to use</p>";
    
    echo "<h3>Next Steps</h3>";
    echo "<ol>";
    echo "<li>Go to: <a href='../intelliwealth/modules' target='_blank'>Module Management</a></li>";
    echo "<li>Enable QR Code module</li>";
    echo "<li>Configure settings as needed</li>";
    echo "<li>Test with sample transactions</li>";
    echo "</ol>";
    
    echo "<p><strong>Note:</strong> You can now safely delete this migration file for security reasons.</p>";

} catch (Exception $e) {
    echo "<h2 class='error'>Migration Failed</h2>";
    echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    echo "<h3>Manual Solution</h3>";
    echo "<p>If the migration fails, you can run the SQL script manually:</p>";
    echo "<ol>";
    echo "<li>Open <code>create_qr_code_tables.sql</code></li>";
    echo "<li>Run the SQL commands in your MySQL database</li>";
    echo "<li>Refresh the modules page</li>";
    echo "</ol>";
    
    echo "<h3>Error Details</h3>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</div></body></html>";
?>
