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
        Schema::create('vsla_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('meeting_id');
            $table->unsignedBigInteger('member_id');
            $table->enum('transaction_type', ['share_purchase', 'loan_issuance', 'loan_repayment', 'penalty_fine', 'welfare_contribution']);
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('loan_id')->nullable();
            $table->unsignedBigInteger('savings_account_id')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('created_user_id');
            $table->timestamps();
        });

        // Add foreign key constraints only if referenced tables exist
        if (Schema::hasTable('tenants')) {
            Schema::table('vsla_transactions', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('vsla_meetings')) {
            Schema::table('vsla_transactions', function (Blueprint $table) {
                $table->foreign('meeting_id')->references('id')->on('vsla_meetings')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('members')) {
            Schema::table('vsla_transactions', function (Blueprint $table) {
                $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('transactions')) {
            Schema::table('vsla_transactions', function (Blueprint $table) {
                $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
            });
        }
        
        if (Schema::hasTable('loans')) {
            Schema::table('vsla_transactions', function (Blueprint $table) {
                $table->foreign('loan_id')->references('id')->on('loans')->onDelete('set null');
            });
        }
        
        if (Schema::hasTable('savings_accounts')) {
            Schema::table('vsla_transactions', function (Blueprint $table) {
                $table->foreign('savings_account_id')->references('id')->on('savings_accounts')->onDelete('set null');
            });
        }
        
        if (Schema::hasTable('users')) {
            Schema::table('vsla_transactions', function (Blueprint $table) {
                $table->foreign('created_user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vsla_transactions');
    }
};
