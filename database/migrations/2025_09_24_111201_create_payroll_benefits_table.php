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
        Schema::create('payroll_benefits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name'); // e.g., "Health Insurance", "Retirement Plan", "Life Insurance"
            $table->string('code')->unique(); // e.g., "HI", "RP", "LI"
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed_amount', 'tiered'])->default('percentage');
            $table->decimal('rate', 8, 4)->nullable(); // Percentage rate (e.g., 5.0 for 5%)
            $table->decimal('amount', 15, 2)->nullable(); // Fixed amount
            $table->json('tiered_rates')->nullable(); // JSON for tiered calculation
            $table->decimal('minimum_amount', 15, 2)->default(0); // Minimum benefit amount
            $table->decimal('maximum_amount', 15, 2)->nullable(); // Maximum benefit amount
            $table->boolean('is_employer_paid')->default(true); // Employer pays vs employee pays
            $table->boolean('is_active')->default(true);
            $table->string('category')->nullable(); // e.g., "health", "retirement", "life", "dental"
            $table->json('applicable_employees')->nullable(); // JSON array of employee IDs or criteria
            $table->json('calculation_rules')->nullable(); // JSON for complex calculation rules
            $table->date('effective_date')->nullable(); // When this benefit becomes effective
            $table->date('expiry_date')->nullable(); // When this benefit expires
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'category']);
            $table->index(['tenant_id', 'is_employer_paid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_benefits');
    }
};