<?php
/**
 * Debug Tenant Context in Shared Route
 */

// Include Laravel bootstrap
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tenant;

echo "<h1>Debug Tenant Context</h1>\n";

try {
    // Test 1: Direct tenant access
    echo "<h3>Test 1: Direct Tenant Access</h3>\n";
    $tenant = Tenant::where('slug', 'intelliwealth')->first();
    if ($tenant) {
        echo "<p>✅ Tenant found: " . $tenant->name . " (ID: " . $tenant->id . ")</p>\n";
        echo "<p><strong>qr_code_enabled:</strong> " . ($tenant->qr_code_enabled ? 'Yes' : 'No') . "</p>\n";
        echo "<p><strong>isQrCodeEnabled():</strong> " . ($tenant->isQrCodeEnabled() ? 'Yes' : 'No') . "</p>\n";
    } else {
        echo "<p class='error'>❌ Tenant not found</p>\n";
    }
    
    // Test 2: Simulate tenant context
    echo "<h3>Test 2: Simulate Tenant Context</h3>\n";
    app()->instance('tenant', $tenant);
    $boundTenant = app('tenant');
    if ($boundTenant) {
        echo "<p>✅ Tenant bound to container: " . $boundTenant->name . "</p>\n";
        echo "<p><strong>isQrCodeEnabled():</strong> " . ($boundTenant->isQrCodeEnabled() ? 'Yes' : 'No') . "</p>\n";
    } else {
        echo "<p class='error'>❌ Tenant not bound to container</p>\n";
    }
    
    // Test 3: Check if the issue is with the route
    echo "<h3>Test 3: Route Test</h3>\n";
    $url = "http://localhost/intellicash/intelliwealth/receipt/qr-code/1";
    echo "<p><strong>Testing URL:</strong> <a href='" . $url . "' target='_blank'>" . $url . "</a></p>\n";
    
    // Test 4: Check middleware
    echo "<h3>Test 4: Middleware Check</h3>\n";
    $route = \Route::getRoutes()->getByName('shared.receipt.qr-code');
    if ($route) {
        echo "<p>✅ Route found: " . $route->uri() . "</p>\n";
        echo "<p><strong>Middleware:</strong> " . implode(', ', $route->gatherMiddleware()) . "</p>\n";
    } else {
        echo "<p class='error'>❌ Route not found</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>\n";
    echo "<p><strong>Stack Trace:</strong></p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}

echo "<style>
.success { color: #28a745; font-weight: bold; }
.error { color: #dc3545; font-weight: bold; }
</style>";
?>
