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
        // Add indexes for bank_transactions table
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->index(['bank_account_id', 'trans_date'], 'idx_bank_trans_account_date');
            $table->index(['bank_account_id', 'dr_cr', 'status'], 'idx_bank_trans_account_type_status');
            $table->index('trans_date', 'idx_bank_trans_date');
            $table->index('status', 'idx_bank_trans_status');
            $table->index('type', 'idx_bank_trans_type');
        });

        // Add indexes for transactions table
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['bank_account_id', 'trans_date'], 'idx_trans_bank_account_date');
            $table->index(['savings_account_id', 'dr_cr', 'status'], 'idx_trans_savings_account_type_status');
            $table->index(['member_id', 'savings_account_id'], 'idx_trans_member_savings');
        });

        // Add indexes for vsla_transactions table
        Schema::table('vsla_transactions', function (Blueprint $table) {
            $table->index(['bank_account_id', 'status'], 'idx_vsla_trans_bank_status');
            $table->index(['member_id', 'transaction_type'], 'idx_vsla_trans_member_type');
            $table->index('status', 'idx_vsla_trans_status');
        });

        // Add indexes for bank_accounts table
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->index(['tenant_id', 'account_name'], 'idx_bank_accounts_tenant_name');
            $table->index('currency_id', 'idx_bank_accounts_currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes from bank_transactions table
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_bank_trans_account_date');
            $table->dropIndex('idx_bank_trans_account_type_status');
            $table->dropIndex('idx_bank_trans_date');
            $table->dropIndex('idx_bank_trans_status');
            $table->dropIndex('idx_bank_trans_type');
        });

        // Remove indexes from transactions table
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_trans_bank_account_date');
            $table->dropIndex('idx_trans_savings_account_type_status');
            $table->dropIndex('idx_trans_member_savings');
        });

        // Remove indexes from vsla_transactions table
        Schema::table('vsla_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_vsla_trans_bank_status');
            $table->dropIndex('idx_vsla_trans_member_type');
            $table->dropIndex('idx_vsla_trans_status');
        });

        // Remove indexes from bank_accounts table
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropIndex('idx_bank_accounts_tenant_name');
            $table->dropIndex('idx_bank_accounts_currency');
        });
    }
};
