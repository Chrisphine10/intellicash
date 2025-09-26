<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('withdraw_requests', function (Blueprint $table) {
            // Add indexes for frequently queried columns
            $table->index(['member_id', 'status'], 'idx_withdraw_requests_member_status');
            $table->index(['created_at', 'status'], 'idx_withdraw_requests_created_status');
            $table->index(['tenant_id', 'status'], 'idx_withdraw_requests_tenant_status');
            $table->index(['method_id'], 'idx_withdraw_requests_method');
            $table->index(['debit_account_id'], 'idx_withdraw_requests_account');
            
            // Add foreign key constraints if they don't exist
            if (!Schema::hasColumn('withdraw_requests', 'transaction_id')) {
                $table->foreignId('transaction_id')->nullable()->constrained('transactions')->onDelete('set null');
            }
        });

        Schema::table('withdraw_methods', function (Blueprint $table) {
            // Add indexes
            $table->index(['status'], 'idx_withdraw_methods_status');
            $table->index(['tenant_id', 'status'], 'idx_withdraw_methods_tenant_status');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Add indexes for withdrawal-related queries
            $table->index(['member_id', 'type', 'status'], 'idx_transactions_member_type_status');
            $table->index(['savings_account_id', 'dr_cr', 'status'], 'idx_transactions_account_dr_cr_status');
            $table->index(['created_at', 'type'], 'idx_transactions_created_type');
        });

        // Add check constraints using raw SQL
        $this->addCheckConstraints();
    }

    /**
     * Add check constraints using raw SQL
     */
    private function addCheckConstraints(): void
    {
        try {
            // Check constraints for withdraw_requests
            DB::statement('ALTER TABLE withdraw_requests ADD CONSTRAINT chk_withdraw_requests_amount_positive CHECK (amount > 0)');
            DB::statement('ALTER TABLE withdraw_requests ADD CONSTRAINT chk_withdraw_requests_converted_amount_positive CHECK (converted_amount > 0)');
            DB::statement('ALTER TABLE withdraw_requests ADD CONSTRAINT chk_withdraw_requests_status_valid CHECK (status IN (0, 1, 2, 3))');
            
            // Check constraints for withdraw_methods
            DB::statement('ALTER TABLE withdraw_methods ADD CONSTRAINT chk_withdraw_methods_status_valid CHECK (status IN (0, 1))');
            
            // Check constraints for transactions
            DB::statement('ALTER TABLE transactions ADD CONSTRAINT chk_transactions_amount_positive CHECK (amount > 0)');
            DB::statement('ALTER TABLE transactions ADD CONSTRAINT chk_transactions_charge_non_negative CHECK (charge >= 0)');
            DB::statement('ALTER TABLE transactions ADD CONSTRAINT chk_transactions_dr_cr_valid CHECK (dr_cr IN ("dr", "cr"))');
            DB::statement('ALTER TABLE transactions ADD CONSTRAINT chk_transactions_status_valid CHECK (status IN (0, 1, 2, 3))');
        } catch (\Exception $e) {
            // Log the error but don't fail the migration
            \Log::warning('Failed to add check constraints: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('withdraw_requests', function (Blueprint $table) {
            $table->dropIndex('idx_withdraw_requests_member_status');
            $table->dropIndex('idx_withdraw_requests_created_status');
            $table->dropIndex('idx_withdraw_requests_tenant_status');
            $table->dropIndex('idx_withdraw_requests_method');
            $table->dropIndex('idx_withdraw_requests_account');
            
            $table->dropForeign(['transaction_id']);
            $table->dropColumn('transaction_id');
        });

        Schema::table('withdraw_methods', function (Blueprint $table) {
            $table->dropIndex('idx_withdraw_methods_status');
            $table->dropIndex('idx_withdraw_methods_tenant_status');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_member_type_status');
            $table->dropIndex('idx_transactions_account_dr_cr_status');
            $table->dropIndex('idx_transactions_created_type');
        });

        // Drop check constraints using raw SQL
        $this->dropCheckConstraints();
    }

    /**
     * Drop check constraints using raw SQL
     */
    private function dropCheckConstraints(): void
    {
        try {
            // Drop check constraints for withdraw_requests
            DB::statement('ALTER TABLE withdraw_requests DROP CONSTRAINT IF EXISTS chk_withdraw_requests_amount_positive');
            DB::statement('ALTER TABLE withdraw_requests DROP CONSTRAINT IF EXISTS chk_withdraw_requests_converted_amount_positive');
            DB::statement('ALTER TABLE withdraw_requests DROP CONSTRAINT IF EXISTS chk_withdraw_requests_status_valid');
            
            // Drop check constraints for withdraw_methods
            DB::statement('ALTER TABLE withdraw_methods DROP CONSTRAINT IF EXISTS chk_withdraw_methods_status_valid');
            
            // Drop check constraints for transactions
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS chk_transactions_amount_positive');
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS chk_transactions_charge_non_negative');
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS chk_transactions_dr_cr_valid');
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS chk_transactions_status_valid');
        } catch (\Exception $e) {
            // Log the error but don't fail the migration
            \Log::warning('Failed to drop check constraints: ' . $e->getMessage());
        }
    }
};
