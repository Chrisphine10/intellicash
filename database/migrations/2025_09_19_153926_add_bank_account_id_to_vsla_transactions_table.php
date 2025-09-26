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
        // Only proceed if vsla_transactions table exists and doesn't already have the column
        if (Schema::hasTable('vsla_transactions') && !Schema::hasColumn('vsla_transactions', 'bank_account_id')) {
            Schema::table('vsla_transactions', function (Blueprint $table) {
                if (Schema::hasTable('bank_accounts')) {
                    $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->onDelete('set null');
                } else {
                    $table->unsignedBigInteger('bank_account_id')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vsla_transactions', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn('bank_account_id');
        });
    }
};
