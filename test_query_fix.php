<?php

// Quick test script to verify the savings accounts query works
require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

echo "Testing Savings Accounts Query Fix...\n";

try {
    // Test the exact query from the controller
    $query = DB::table('savings_accounts')
        ->select([
            'savings_accounts.id',
            'savings_accounts.account_number',
            'savings_accounts.member_id',
            'savings_accounts.savings_product_id',
            'savings_accounts.status as account_status',
            'savings_accounts.opening_balance',
            'savings_accounts.description',
            'savings_accounts.created_at',
            'savings_accounts.updated_at',
            'members.first_name as member_first_name',
            'members.last_name as member_last_name',
            'savings_products.name as savings_type_name',
            'currency.name as currency_name'
        ])
        ->leftJoin('members', 'savings_accounts.member_id', '=', 'members.id')
        ->leftJoin('savings_products', 'savings_accounts.savings_product_id', '=', 'savings_products.id')
        ->leftJoin('currency', 'savings_products.currency_id', '=', 'currency.id')
        ->where('savings_accounts.status', '!=', 0)
        ->orderBy("savings_accounts.id", "desc");

    // Get the SQL query
    $sql = $query->toSql();
    $bindings = $query->getBindings();
    
    echo "✅ Query structure is valid\n";
    echo "SQL: " . $sql . "\n";
    echo "Bindings: " . json_encode($bindings) . "\n";
    
    // Check for ambiguous column references
    $ambiguousPatterns = [
        '/\bstatus\b(?!\s+as)/i',  // status without "as" alias
        '/\bwhere.*status.*=/i'    // where clauses with status
    ];
    
    $hasAmbiguous = false;
    foreach ($ambiguousPatterns as $pattern) {
        if (preg_match($pattern, $sql)) {
            echo "❌ Potential ambiguous column reference found\n";
            $hasAmbiguous = true;
            break;
        }
    }
    
    if (!$hasAmbiguous) {
        echo "✅ No ambiguous column references detected\n";
    }
    
    // Test if the query can be executed
    try {
        $results = $query->limit(1)->get();
        echo "✅ Query execution successful\n";
        echo "Found " . $results->count() . " records\n";
        
        if ($results->count() > 0) {
            $firstRecord = $results->first();
            echo "Sample record keys: " . implode(', ', array_keys((array)$firstRecord)) . "\n";
        }
    } catch (Exception $e) {
        echo "❌ Query execution failed: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
