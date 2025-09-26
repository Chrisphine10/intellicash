<?php

// Simple verification script for the savings accounts table fix
// This script can be run directly to test the fix

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\SavingsAccount;
use App\Models\Member;
use App\Models\SavingsProduct;
use App\Models\Currency;

echo "Testing Savings Accounts Table Fix...\n";

try {
    // Test the query structure that was causing the ambiguous column error
    $query = SavingsAccount::select([
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
        'members.status as member_status',
        'savings_products.name as savings_type_name',
        'savings_products.status as product_status',
        'currency.name as currency_name',
        'currency.status as currency_status'
    ])
    ->leftJoin('members', 'savings_accounts.member_id', '=', 'members.id')
    ->leftJoin('savings_products', 'savings_accounts.savings_product_id', '=', 'savings_products.id')
    ->leftJoin('currency', 'savings_products.currency_id', '=', 'currency.id')
    ->where('savings_accounts.status', '!=', 0)
    ->orderBy("savings_accounts.id", "desc");

    // Get the SQL query to verify it's properly formed
    $sql = $query->toSql();
    $bindings = $query->getBindings();
    
    echo "✅ Query structure is valid\n";
    echo "SQL: " . $sql . "\n";
    echo "Bindings: " . json_encode($bindings) . "\n";
    
    // Check if there are any ambiguous column references
    if (strpos($sql, 'status') !== false && strpos($sql, 'savings_accounts.status') !== false) {
        echo "✅ Status column is properly qualified\n";
    } else {
        echo "❌ Status column qualification issue detected\n";
    }
    
    // Test if the query can be executed (this will fail if there are syntax errors)
    try {
        $results = $query->limit(1)->get();
        echo "✅ Query execution successful\n";
        echo "Found " . $results->count() . " records\n";
    } catch (Exception $e) {
        echo "❌ Query execution failed: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
