<?php
/**
 * Run Laravel Migrations via Web Interface
 * This will run the QR Code module migrations
 */

// Include Laravel bootstrap
require_once 'vendor/autoload.php';

// Load Laravel application
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

echo "<!DOCTYPE html>
<html>
<head>
    <title>Run QR Code Migration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { color: #17a2b8; }
    </style>
</head>
<body>
<div class='container'>
    <h1>Run QR Code Migration</h1>";

try {
    echo "<h2>Running Migrations...</h2>";
    
    // Check if qr_code_settings table exists
    if (Schema::hasTable('qr_code_settings')) {
        echo "<p class='info'>✓ qr_code_settings table already exists</p>";
    } else {
        echo "<p class='info'>Creating qr_code_settings table...</p>";
        
        // Create qr_code_settings table
        Schema::create('qr_code_settings', function (Blueprint $table) {
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
        echo "<p class='info'>✓ qr_code_enabled column already exists in tenants table</p>";
    } else {
        echo "<p class='info'>Adding qr_code_enabled column to tenants table...</p>";
        
        Schema::table('tenants', function (Blueprint $table) {
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

    // Verify the migration
    $settingsCount = DB::table('qr_code_settings')->count();
    $tenantCount = DB::table('tenants')->count();
    
    echo "<h2 class='success'>✅ Migration Completed Successfully!</h2>";
    echo "<p><strong>Results:</strong></p>";
    echo "<ul>";
    echo "<li>✓ qr_code_settings table: {$settingsCount} records</li>";
    echo "<li>✓ tenants table: {$tenantCount} records</li>";
    echo "<li>✓ All foreign key constraints created</li>";
    echo "</ul>";
    
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li><strong>Go to Modules:</strong> <a href='intelliwealth/modules' target='_blank'>http://localhost/intellicash/intelliwealth/modules</a></li>";
    echo "<li><strong>Enable QR Code Module:</strong> Click 'Enable' on the QR Code module</li>";
    echo "<li><strong>Configure Settings:</strong> Click 'Configure' to set up preferences</li>";
    echo "</ol>";
    
    echo "<p class='success'><strong>The error should now be fixed!</strong></p>";

} catch (Exception $e) {
    echo "<h2 class='error'>❌ Migration Failed</h2>";
    echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}

echo "</div></body></html>";
?>
