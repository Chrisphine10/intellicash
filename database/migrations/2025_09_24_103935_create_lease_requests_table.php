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
        Schema::create('lease_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('asset_id');
            $table->string('request_number', 191)->unique();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('requested_days');
            $table->decimal('daily_rate', 10, 2);
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->decimal('deposit_amount', 10, 2)->default(0);
            $table->unsignedBigInteger('payment_account_id');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('reason')->nullable();
            $table->boolean('terms_accepted')->default(false);
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('created_user_id');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
            $table->foreign('payment_account_id')->references('id')->on('savings_accounts')->onDelete('cascade');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->index(['tenant_id', 'status']);
            $table->index(['member_id', 'status']);
            $table->index(['asset_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lease_requests');
    }
};