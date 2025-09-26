<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only proceed if deposit_requests table exists
        if (Schema::hasTable('deposit_requests')) {
            Schema::table('deposit_requests', function (Blueprint $table) {
                // Rename method_id to deposit_method_id to match controller expectations
                if (Schema::hasColumn('deposit_requests', 'method_id')) {
                    $table->renameColumn('method_id', 'deposit_method_id');
                }
                
                // Rename credit_account_id to savings_account_id for consistency
                if (Schema::hasColumn('deposit_requests', 'credit_account_id')) {
                    $table->renameColumn('credit_account_id', 'savings_account_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deposit_requests', function (Blueprint $table) {
            // Reverse the column renames
            $table->renameColumn('deposit_method_id', 'method_id');
            $table->renameColumn('savings_account_id', 'credit_account_id');
        });
    }
};
