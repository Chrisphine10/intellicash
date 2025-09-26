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
        Schema::create('employee_benefits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('benefit_id');
            $table->decimal('rate', 8, 4)->nullable(); // Override benefit rate for this employee
            $table->decimal('amount', 15, 2)->nullable(); // Override benefit amount for this employee
            $table->decimal('minimum_amount', 15, 2)->nullable(); // Override minimum amount
            $table->decimal('maximum_amount', 15, 2)->nullable(); // Override maximum amount
            $table->boolean('is_active')->default(true);
            $table->date('effective_date')->nullable(); // When this benefit becomes effective for employee
            $table->date('expiry_date')->nullable(); // When this benefit expires for employee
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('benefit_id')->references('id')->on('payroll_benefits')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'benefit_id']);
            $table->index(['tenant_id', 'is_active']);
            $table->unique(['employee_id', 'benefit_id']); // One benefit assignment per employee
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_benefits');
    }
};