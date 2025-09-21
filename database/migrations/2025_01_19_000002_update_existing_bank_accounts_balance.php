<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing bank accounts to set current_balance = opening_balance
        DB::statement('UPDATE bank_accounts SET current_balance = opening_balance WHERE current_balance = 0');
        
        // Update last_balance_update timestamp for existing accounts
        DB::statement('UPDATE bank_accounts SET last_balance_update = NOW() WHERE last_balance_update IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this data migration
    }
};
