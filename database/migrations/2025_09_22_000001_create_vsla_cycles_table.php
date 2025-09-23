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
        Schema::create('vsla_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('cycle_name')->default('Annual Cycle');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'completed', 'share_out_in_progress', 'archived'])->default('active');
            $table->decimal('total_shares_contributed', 15, 2)->default(0);
            $table->decimal('total_welfare_contributed', 15, 2)->default(0);
            $table->decimal('total_penalties_collected', 15, 2)->default(0);
            $table->decimal('total_loan_interest_earned', 15, 2)->default(0);
            $table->decimal('total_available_for_shareout', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('share_out_date')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vsla_cycles');
    }
};
