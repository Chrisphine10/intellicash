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
        Schema::create('vsla_shareouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('cycle_id')->constrained('vsla_cycles')->onDelete('cascade');
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->decimal('total_shares_contributed', 15, 2)->default(0);
            $table->decimal('total_welfare_contributed', 15, 2)->default(0);
            $table->decimal('share_percentage', 8, 5)->default(0); // e.g., 0.12345 = 12.345%
            $table->decimal('share_value_payout', 15, 2)->default(0);
            $table->decimal('profit_share', 15, 2)->default(0);
            $table->decimal('welfare_refund', 15, 2)->default(0);
            $table->decimal('total_payout', 15, 2)->default(0);
            $table->decimal('outstanding_loan_balance', 15, 2)->default(0);
            $table->decimal('net_payout', 15, 2)->default(0); // total_payout - outstanding_loan_balance
            $table->enum('payout_status', ['calculated', 'approved', 'paid', 'cancelled'])->default('calculated');
            $table->text('notes')->nullable();
            $table->foreignId('savings_account_id')->nullable()->constrained('savings_accounts')->onDelete('set null');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->onDelete('set null');
            $table->foreignId('created_user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            $table->unique(['cycle_id', 'member_id']);
            $table->index(['tenant_id', 'payout_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vsla_shareouts');
    }
};
