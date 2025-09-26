<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDashboardPerformanceIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add indexes only if they don't exist
        $this->addIndexIfNotExists('transactions', ['status', 'type'], 'idx_status_type');
        $this->addIndexIfNotExists('transactions', ['member_id', 'status', 'dr_cr'], 'idx_member_status_dr_cr');
        $this->addIndexIfNotExists('transactions', ['savings_account_id', 'status'], 'idx_savings_account_status');

        $this->addIndexIfNotExists('members', ['status', 'created_at'], 'idx_status_created_at');
        $this->addIndexIfNotExists('members', ['branch_id', 'created_at'], 'idx_branch_created_at');

        $this->addIndexIfNotExists('loans', ['status', 'borrower_id'], 'idx_status_borrower');
        $this->addIndexIfNotExists('loans', ['currency_id', 'status'], 'idx_loan_currency_status');

        $this->addIndexIfNotExists('loan_repayments', ['loan_id', 'repayment_date', 'status'], 'idx_loan_date_status');
        $this->addIndexIfNotExists('loan_repayments', ['status', 'repayment_date'], 'idx_status_repayment_date');

        $this->addIndexIfNotExists('savings_accounts', ['savings_product_id', 'member_id'], 'idx_savings_product_member');
        $this->addIndexIfNotExists('savings_products', ['currency_id', 'status'], 'idx_savings_currency_status');

        // Add indexes for assets table if it exists
        if (Schema::hasTable('assets')) {
            $this->addIndexIfNotExists('assets', ['status', 'is_leasable'], 'idx_status_leasable');
            $this->addIndexIfNotExists('assets', ['category_id', 'status'], 'idx_category_status');
        }

        // Add indexes for employees table if it exists
        if (Schema::hasTable('employees')) {
            $this->addIndexIfNotExists('employees', ['is_active', 'department'], 'idx_active_department');
            $this->addIndexIfNotExists('employees', ['employment_type', 'is_active'], 'idx_employment_active');
        }
    }

    /**
     * Add index if it doesn't exist
     */
    private function addIndexIfNotExists($table, $columns, $name)
    {
        if (!Schema::hasIndex($table, $name)) {
            Schema::table($table, function (Blueprint $table) use ($columns, $name) {
                $table->index($columns, $name);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_trans_date_status');
            $table->dropIndex('idx_status_type');
            $table->dropIndex('idx_member_status_dr_cr');
            $table->dropIndex('idx_savings_account_status');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex('idx_status_created_at');
            $table->dropIndex('idx_branch_created_at');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex('idx_status_borrower');
            $table->dropIndex('idx_currency_status');
        });

        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropIndex('idx_loan_date_status');
            $table->dropIndex('idx_status_repayment_date');
        });

        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->dropIndex('idx_savings_product_member');
        });

        Schema::table('savings_products', function (Blueprint $table) {
            $table->dropIndex('idx_currency_status');
        });

        if (Schema::hasTable('assets')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropIndex('idx_status_leasable');
                $table->dropIndex('idx_category_status');
            });
        }

        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropIndex('idx_active_department');
                $table->dropIndex('idx_employment_active');
            });
        }
    }
}
