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
        Schema::create('guarantor_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('borrower_id')->constrained('members')->cascadeOnDelete();
            $table->string('guarantor_email', 191);
            $table->string('guarantor_name', 191);
            $table->text('guarantor_message')->nullable();
            $table->string('token', 64)->unique();
            $table->enum('status', ['pending', 'accepted', 'declined', 'expired'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->text('response_message')->nullable();
            $table->timestamps();
            
            $table->index(['token', 'status'], 'idx_guarantor_requests_token_status');
            $table->index(['loan_id', 'status'], 'idx_guarantor_requests_loan_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guarantor_requests');
    }
};
