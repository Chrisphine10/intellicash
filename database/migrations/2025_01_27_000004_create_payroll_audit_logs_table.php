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
        Schema::create('payroll_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 50); // created, updated, deleted, processed, approved, etc.
            $table->string('model_type', 50); // PayrollPeriod, PayrollItem, PayrollDeduction, etc.
            $table->unsignedBigInteger('model_id');
            $table->json('old_values')->nullable(); // Previous values
            $table->json('new_values')->nullable(); // New values
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['tenant_id', 'model_type', 'model_id'], 'idx_payroll_audit_tenant_model');
            $table->index(['tenant_id', 'action'], 'idx_payroll_audit_tenant_action');
            $table->index(['tenant_id', 'user_id'], 'idx_payroll_audit_tenant_user');
            $table->index(['tenant_id', 'created_at'], 'idx_payroll_audit_tenant_date');
            $table->index(['model_type', 'model_id'], 'idx_payroll_audit_model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_audit_logs');
    }
};
