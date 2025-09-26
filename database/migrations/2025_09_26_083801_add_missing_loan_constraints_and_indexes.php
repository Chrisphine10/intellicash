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
        Schema::table('loan_payments', function (Blueprint $table) {
            // First modify the column type to match transactions.id
            $table->unsignedBigInteger('transaction_id')->nullable()->change();
            
            // Add missing foreign key constraint for transaction_id
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
            
            // Add composite index for better performance on payment queries
            $table->index(['loan_id', 'paid_at']);
            $table->index(['member_id', 'paid_at']);
        });

        Schema::table('loan_repayments', function (Blueprint $table) {
            // Add composite index for better performance on repayment queries
            $table->index(['loan_id', 'status', 'repayment_date']);
            $table->index(['repayment_date', 'status']);
        });

        Schema::table('loans', function (Blueprint $table) {
            // Add index for better performance on loan queries
            $table->index(['status', 'approved_date']);
            $table->index(['borrower_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_payments', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropIndex(['loan_id', 'paid_at']);
            $table->dropIndex(['member_id', 'paid_at']);
        });

        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropIndex(['loan_id', 'status', 'repayment_date']);
            $table->dropIndex(['repayment_date', 'status']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex(['status', 'approved_date']);
            $table->dropIndex(['borrower_id', 'status', 'created_at']);
        });
    }
};