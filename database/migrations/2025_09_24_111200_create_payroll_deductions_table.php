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
        Schema::create('payroll_deductions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name', 191); // e.g., "Income Tax", "Social Security", "Health Insurance"
            $table->string('code', 50)->unique(); // e.g., "IT", "SS", "HI"
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed_amount', 'tiered'])->default('percentage');
            $table->decimal('rate', 8, 4)->nullable(); // Percentage rate (e.g., 15.5 for 15.5%)
            $table->decimal('amount', 15, 2)->nullable(); // Fixed amount
            $table->json('tiered_rates')->nullable(); // JSON for tiered calculation
            $table->decimal('minimum_amount', 15, 2)->default(0); // Minimum deduction amount
            $table->decimal('maximum_amount', 15, 2)->nullable(); // Maximum deduction amount
            $table->boolean('is_mandatory')->default(false); // Required by law
            $table->boolean('is_active')->default(true);
            $table->string('tax_category', 50)->nullable(); // For tax reporting
            $table->json('applicable_employees')->nullable(); // JSON array of employee IDs or criteria
            $table->json('calculation_rules')->nullable(); // JSON for complex calculation rules
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['tenant_id', 'is_active'], 'idx_deductions_tenant_active');
            $table->index(['tenant_id', 'type'], 'idx_deductions_tenant_type');
            $table->index(['tenant_id', 'is_mandatory'], 'idx_deductions_tenant_mandatory');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_deductions');
    }
};