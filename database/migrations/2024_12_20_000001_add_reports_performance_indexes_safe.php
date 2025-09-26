<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddReportsPerformanceIndexesSafe extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Helper function to check if index exists
        $indexExists = function($table, $indexName) {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        };

        // Loans table indexes
        if (!$indexExists('loans', 'idx_loans_status_created')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->index(['status', 'created_at'], 'idx_loans_status_created');
            });
        }

        if (!$indexExists('loans', 'idx_loans_tenant_status')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->index(['tenant_id', 'status'], 'idx_loans_tenant_status');
            });
        }

        if (!$indexExists('loans', 'idx_loans_borrower_status')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->index(['borrower_id', 'status'], 'idx_loans_borrower_status');
            });
        }

        if (!$indexExists('loans', 'idx_loans_product_status')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->index(['loan_product_id', 'status'], 'idx_loans_product_status');
            });
        }

        if (!$indexExists('loans', 'idx_loans_comprehensive')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'created_at', 'loan_product_id'], 'idx_loans_comprehensive');
            });
        }

        // Transactions table indexes
        if (!$indexExists('transactions', 'idx_transactions_date_status')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->index(['trans_date', 'status'], 'idx_transactions_date_status');
            });
        }

        if (!$indexExists('transactions', 'idx_transactions_member_account')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->index(['member_id', 'savings_account_id'], 'idx_transactions_member_account');
            });
        }

        if (!$indexExists('transactions', 'idx_transactions_tenant_date')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->index(['tenant_id', 'trans_date'], 'idx_transactions_tenant_date');
            });
        }

        if (!$indexExists('transactions', 'idx_transactions_comprehensive')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'trans_date', 'member_id'], 'idx_transactions_comprehensive');
            });
        }

        // Loan payments table indexes
        if (!$indexExists('loan_payments', 'idx_loan_payments_date')) {
            Schema::table('loan_payments', function (Blueprint $table) {
                $table->index(['paid_at'], 'idx_loan_payments_date');
            });
        }

        if (!$indexExists('loan_payments', 'idx_loan_payments_loan_date')) {
            Schema::table('loan_payments', function (Blueprint $table) {
                $table->index(['loan_id', 'paid_at'], 'idx_loan_payments_loan_date');
            });
        }

        // Bank transactions table indexes
        if (!$indexExists('bank_transactions', 'idx_bank_transactions_date_status')) {
            Schema::table('bank_transactions', function (Blueprint $table) {
                $table->index(['trans_date', 'status'], 'idx_bank_transactions_date_status');
            });
        }

        if (!$indexExists('bank_transactions', 'idx_bank_transactions_account_date')) {
            Schema::table('bank_transactions', function (Blueprint $table) {
                $table->index(['bank_account_id', 'trans_date'], 'idx_bank_transactions_account_date');
            });
        }

        // Expenses table indexes
        if (!$indexExists('expenses', 'idx_expenses_date')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->index(['expense_date'], 'idx_expenses_date');
            });
        }

        if (!$indexExists('expenses', 'idx_expenses_tenant_date')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->index(['tenant_id', 'expense_date'], 'idx_expenses_tenant_date');
            });
        }

        // Members table indexes
        if (!$indexExists('members', 'idx_members_member_no')) {
            Schema::table('members', function (Blueprint $table) {
                $table->index(['member_no'], 'idx_members_member_no');
            });
        }

        if (!$indexExists('members', 'idx_members_tenant_member_no')) {
            Schema::table('members', function (Blueprint $table) {
                $table->index(['tenant_id', 'member_no'], 'idx_members_tenant_member_no');
            });
        }

        // Savings accounts table indexes
        if (!$indexExists('savings_accounts', 'idx_savings_accounts_number')) {
            Schema::table('savings_accounts', function (Blueprint $table) {
                $table->index(['account_number'], 'idx_savings_accounts_number');
            });
        }

        if (!$indexExists('savings_accounts', 'idx_savings_accounts_member_number')) {
            Schema::table('savings_accounts', function (Blueprint $table) {
                $table->index(['member_id', 'account_number'], 'idx_savings_accounts_member_number');
            });
        }

        // Bank accounts table indexes
        if (!$indexExists('bank_accounts', 'idx_bank_accounts_tenant')) {
            Schema::table('bank_accounts', function (Blueprint $table) {
                $table->index(['tenant_id'], 'idx_bank_accounts_tenant');
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
        // Helper function to check if index exists
        $indexExists = function($table, $indexName) {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        };

        // Drop indexes if they exist
        $indexesToDrop = [
            'loans' => ['idx_loans_status_created', 'idx_loans_tenant_status', 'idx_loans_borrower_status', 'idx_loans_product_status', 'idx_loans_comprehensive'],
            'transactions' => ['idx_transactions_date_status', 'idx_transactions_member_account', 'idx_transactions_tenant_date', 'idx_transactions_comprehensive'],
            'loan_payments' => ['idx_loan_payments_date', 'idx_loan_payments_loan_date'],
            'bank_transactions' => ['idx_bank_transactions_date_status', 'idx_bank_transactions_account_date'],
            'expenses' => ['idx_expenses_date', 'idx_expenses_tenant_date'],
            'members' => ['idx_members_member_no', 'idx_members_tenant_member_no'],
            'savings_accounts' => ['idx_savings_accounts_number', 'idx_savings_accounts_member_number'],
            'bank_accounts' => ['idx_bank_accounts_tenant']
        ];

        foreach ($indexesToDrop as $table => $indexes) {
            foreach ($indexes as $index) {
                if ($indexExists($table, $index)) {
                    Schema::table($table, function (Blueprint $table) use ($index) {
                        $table->dropIndex($index);
                    });
                }
            }
        }
    }
}
