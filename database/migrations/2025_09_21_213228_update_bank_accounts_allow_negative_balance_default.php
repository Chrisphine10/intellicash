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
        // Only proceed if bank_accounts table exists and has the allow_negative_balance column
        if (Schema::hasTable('bank_accounts') && Schema::hasColumn('bank_accounts', 'allow_negative_balance')) {
            Schema::table('bank_accounts', function (Blueprint $table) {
                // Change the default value for allow_negative_balance to true
                $table->boolean('allow_negative_balance')->default(true)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            // Revert back to false as default
            $table->boolean('allow_negative_balance')->default(false)->change();
        });
    }
};
