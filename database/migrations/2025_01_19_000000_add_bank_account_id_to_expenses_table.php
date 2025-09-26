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
        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (!Schema::hasColumn('expenses', 'bank_account_id')) {
                    $table->unsignedBigInteger('bank_account_id')->nullable()->after('expense_category_id');
                }
            });
            
            // Add foreign key constraint only if bank_accounts table exists
            if (Schema::hasTable('bank_accounts')) {
                Schema::table('expenses', function (Blueprint $table) {
                    $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn('bank_account_id');
        });
    }
};
