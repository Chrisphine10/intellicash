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
        Schema::table('vsla_settings', function (Blueprint $table) {
            $table->boolean('create_default_loan_product')->default(true)->after('max_loan_duration_days');
            $table->boolean('create_default_savings_products')->default(true)->after('create_default_loan_product');
            $table->boolean('create_default_bank_accounts')->default(true)->after('create_default_savings_products');
            $table->boolean('create_default_expense_categories')->default(true)->after('create_default_bank_accounts');
            $table->boolean('auto_create_member_accounts')->default(true)->after('create_default_expense_categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vsla_settings', function (Blueprint $table) {
            $table->dropColumn([
                'create_default_loan_product',
                'create_default_savings_products', 
                'create_default_bank_accounts',
                'create_default_expense_categories',
                'auto_create_member_accounts'
            ]);
        });
    }
};