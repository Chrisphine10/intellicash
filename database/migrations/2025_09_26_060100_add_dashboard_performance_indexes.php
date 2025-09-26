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
        Schema::table('transactions', function (Blueprint $table) {
            // Add composite indexes for better query performance
            $table->index(['status', 'type', 'trans_date'], 'idx_transactions_status_type_date');
            $table->index(['dr_cr', 'status', 'trans_date'], 'idx_transactions_dr_cr_status_date');
            $table->index(['member_id', 'status', 'trans_date'], 'idx_transactions_member_status_date');
        });

        Schema::table('members', function (Blueprint $table) {
            // Add indexes for member queries
            $table->index(['status', 'created_at'], 'idx_members_status_created');
            $table->index(['branch_id', 'status'], 'idx_members_branch_status');
        });

        Schema::table('loans', function (Blueprint $table) {
            // Add indexes for loan queries
            $table->index(['status', 'created_at'], 'idx_loans_status_created');
            $table->index(['borrower_id', 'status'], 'idx_loans_borrower_status');
            $table->index(['status', 'currency_id'], 'idx_loans_status_currency');
        });

        Schema::table('loan_repayments', function (Blueprint $table) {
            // Add indexes for loan repayment queries
            $table->index(['status', 'repayment_date'], 'idx_repayments_status_date');
            $table->index(['loan_id', 'status'], 'idx_repayments_loan_status');
        });

        Schema::table('expenses', function (Blueprint $table) {
            // Add indexes for expense queries
            $table->index(['expense_date', 'expense_category_id'], 'idx_expenses_date_category');
        });

        // Add indexes for asset management if tables exist
        if (Schema::hasTable('assets')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->index(['status', 'category_id'], 'idx_assets_status_category');
                $table->index(['is_leasable', 'status'], 'idx_assets_leasable_status');
            });
        }

        // Add indexes for employee management if tables exist
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->index(['is_active', 'employment_status'], 'idx_employees_active_status');
                $table->index(['department', 'is_active'], 'idx_employees_dept_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_status_type_date');
            $table->dropIndex('idx_transactions_dr_cr_status_date');
            $table->dropIndex('idx_transactions_member_status_date');
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex('idx_members_status_created');
            $table->dropIndex('idx_members_branch_status');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex('idx_loans_status_created');
            $table->dropIndex('idx_loans_borrower_status');
            $table->dropIndex('idx_loans_status_currency');
        });

        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropIndex('idx_repayments_status_date');
            $table->dropIndex('idx_repayments_loan_status');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('idx_expenses_date_category');
        });

        if (Schema::hasTable('assets')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropIndex('idx_assets_status_category');
                $table->dropIndex('idx_assets_leasable_status');
            });
        }

        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropIndex('idx_employees_active_status');
                $table->dropIndex('idx_employees_dept_active');
            });
        }
    }
};