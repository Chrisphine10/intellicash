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
        // Add foreign keys and indexes to loans table if it exists
        if (Schema::hasTable('loans')) {
            Schema::table('loans', function (Blueprint $table) {
                // Add foreign key constraints with tenant validation
                if (Schema::hasTable('members')) {
                    $table->foreign('borrower_id')->references('id')->on('members')->onDelete('cascade');
                }
                if (Schema::hasTable('loan_products')) {
                    $table->foreign('loan_product_id')->references('id')->on('loan_products')->onDelete('cascade');
                }
                if (Schema::hasTable('currency')) {
                    $table->foreign('currency_id')->references('id')->on('currency')->onDelete('cascade');
                }
                if (Schema::hasTable('users')) {
                    $table->foreign('approved_user_id')->references('id')->on('users')->onDelete('set null');
                    $table->foreign('created_user_id')->references('id')->on('users')->onDelete('set null');
                }
                if (Schema::hasTable('branches')) {
                    $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
                }
                if (Schema::hasTable('savings_accounts')) {
                    $table->foreign('debit_account_id')->references('id')->on('savings_accounts')->onDelete('set null');
                }
                
                // Add tenant-aware indexes for better performance and security
                $table->index(['tenant_id', 'status', 'created_at'], 'idx_loans_tenant_status_created');
                $table->index(['tenant_id', 'borrower_id', 'status'], 'idx_loans_tenant_borrower_status');
                $table->index(['tenant_id', 'loan_product_id', 'status'], 'idx_loans_tenant_product_status');
                $table->index(['tenant_id', 'release_date'], 'idx_loans_tenant_release_date');
                $table->index(['tenant_id', 'first_payment_date'], 'idx_loans_tenant_payment_date');
                
                // Add composite unique constraint for tenant isolation
                $table->unique(['borrower_id', 'tenant_id'], 'loans_borrower_tenant_unique');
            });
        }

        // Add foreign keys and indexes to loan_repayments table if it exists
        if (Schema::hasTable('loan_repayments')) {
            Schema::table('loan_repayments', function (Blueprint $table) {
                // Add foreign key constraints
                if (Schema::hasTable('loans')) {
                    $table->foreign('loan_id')->references('id')->on('loans')->onDelete('cascade');
                }
                
                // Add indexes for better performance
                $table->index(['loan_id', 'status']);
                $table->index(['repayment_date', 'status']);
                $table->index('status');
            });
        }

        // Add indexes to loan_products table if it exists
        if (Schema::hasTable('loan_products')) {
            Schema::table('loan_products', function (Blueprint $table) {
                // Add indexes for better performance
                $table->index('status');
                $table->index(['minimum_amount', 'maximum_amount']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['borrower_id']);
            $table->dropForeign(['loan_product_id']);
            $table->dropForeign(['currency_id']);
            $table->dropForeign(['approved_user_id']);
            $table->dropForeign(['created_user_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['debit_account_id']);
            
            // Drop tenant-aware indexes
            $table->dropIndex('idx_loans_tenant_status_created');
            $table->dropIndex('idx_loans_tenant_borrower_status');
            $table->dropIndex('idx_loans_tenant_product_status');
            $table->dropIndex('idx_loans_tenant_release_date');
            $table->dropIndex('idx_loans_tenant_payment_date');
            
            // Drop composite unique constraint
            $table->dropUnique('loans_borrower_tenant_unique');
        });

        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropForeign(['loan_id']);
            
            $table->dropIndex(['loan_id', 'status']);
            $table->dropIndex(['repayment_date', 'status']);
            $table->dropIndex('status');
        });

        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropIndex('status');
            $table->dropIndex(['minimum_amount', 'maximum_amount']);
        });
    }
};
