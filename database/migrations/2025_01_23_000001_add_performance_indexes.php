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
        // Add indexes only if they don't exist
        $this->addIndexIfNotExists('transactions', ['created_at', 'savings_account_id'], 'idx_transactions_created_savings');
        $this->addIndexIfNotExists('transactions', ['member_id', 'created_at'], 'idx_transactions_member_created');
        $this->addIndexIfNotExists('transactions', ['type', 'status', 'created_at'], 'idx_transactions_type_status_created');
        $this->addIndexIfNotExists('transactions', ['tenant_id', 'created_at'], 'idx_transactions_tenant_created');

        $this->addIndexIfNotExists('savings_accounts', ['member_id', 'status'], 'idx_savings_member_status');
        $this->addIndexIfNotExists('savings_accounts', ['tenant_id', 'status'], 'idx_savings_tenant_status');

        $this->addIndexIfNotExists('members', ['tenant_id', 'status'], 'idx_members_tenant_status');
        $this->addIndexIfNotExists('members', ['first_name', 'last_name'], 'idx_members_name');
        $this->addIndexIfNotExists('members', ['member_no', 'tenant_id'], 'idx_members_number_tenant');

        $this->addIndexIfNotExists('loans', ['borrower_id', 'status', 'created_at'], 'idx_loans_borrower_status_created');
        $this->addIndexIfNotExists('loans', ['status', 'first_payment_date'], 'idx_loans_status_payment_date');
        $this->addIndexIfNotExists('loans', ['tenant_id', 'status'], 'idx_loans_tenant_status');

        $this->addIndexIfNotExists('bank_accounts', ['tenant_id', 'status'], 'idx_bank_accounts_tenant_status');

        $this->addIndexIfNotExists('expenses', ['tenant_id', 'created_at'], 'idx_expenses_tenant_created');
        $this->addIndexIfNotExists('expenses', ['category_id', 'created_at'], 'idx_expenses_category_created');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('transactions', 'idx_transactions_created_savings');
        $this->dropIndexIfExists('transactions', 'idx_transactions_member_created');
        $this->dropIndexIfExists('transactions', 'idx_transactions_type_status_created');
        $this->dropIndexIfExists('transactions', 'idx_transactions_tenant_created');

        $this->dropIndexIfExists('savings_accounts', 'idx_savings_member_status');
        $this->dropIndexIfExists('savings_accounts', 'idx_savings_tenant_status');

        $this->dropIndexIfExists('members', 'idx_members_tenant_status');
        $this->dropIndexIfExists('members', 'idx_members_name');
        $this->dropIndexIfExists('members', 'idx_members_number_tenant');

        $this->dropIndexIfExists('loans', 'idx_loans_borrower_status_created');
        $this->dropIndexIfExists('loans', 'idx_loans_status_payment_date');
        $this->dropIndexIfExists('loans', 'idx_loans_tenant_status');

        $this->dropIndexIfExists('bank_accounts', 'idx_bank_accounts_tenant_status');

        $this->dropIndexIfExists('expenses', 'idx_expenses_tenant_created');
        $this->dropIndexIfExists('expenses', 'idx_expenses_category_created');
    }

    /**
     * Add index if it doesn't exist
     */
    private function addIndexIfNotExists(string $table, array $columns, string $indexName): void
    {
        $indexes = $this->getTableIndexes($table);
        
        if (!in_array($indexName, $indexes)) {
            try {
                Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
                    $table->index($columns, $indexName);
                });
                // Index added successfully
            } catch (\Exception $e) {
                // Index creation failed - continue silently
            }
        } else {
            // Index already exists
        }
    }

    /**
     * Drop index if it exists
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $indexes = $this->getTableIndexes($table);
        
        if (in_array($indexName, $indexes)) {
            try {
                Schema::table($table, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
                // Index dropped successfully
            } catch (\Exception $e) {
                // Index drop failed - continue silently
            }
        } else {
            // Index does not exist
        }
    }

    /**
     * Get all indexes for a table
     */
    private function getTableIndexes(string $table): array
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM {$table}");
            return array_unique(array_column($indexes, 'Key_name'));
        } catch (\Exception $e) {
            // Could not get indexes for table
            return [];
        }
    }
};