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
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('period_name', 191); // e.g., "January 2025", "Q1 2025"
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('period_type', ['weekly', 'bi_weekly', 'monthly', 'quarterly', 'annually'])->default('monthly');
            $table->enum('status', ['draft', 'processing', 'completed', 'cancelled'])->default('draft');
            $table->date('pay_date')->nullable(); // When employees will be paid
            $table->decimal('total_gross_pay', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_benefits', 15, 2)->default(0);
            $table->decimal('total_net_pay', 15, 2)->default(0);
            $table->integer('total_employees')->default(0);
            $table->text('notes')->nullable();
            $table->json('processing_log')->nullable(); // JSON for processing history
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['tenant_id', 'start_date', 'end_date'], 'idx_payroll_periods_tenant_dates');
            $table->index(['tenant_id', 'status'], 'idx_payroll_periods_tenant_status');
            $table->index(['tenant_id', 'period_type'], 'idx_payroll_periods_tenant_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};