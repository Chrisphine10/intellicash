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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable(); // Link to system user if employee is a system user
            $table->string('employee_id', 191)->unique(); // Employee ID/Number
            $table->string('first_name', 191);
            $table->string('last_name', 191);
            $table->string('middle_name', 191)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address', 500)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('passport_number', 50)->nullable();
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->enum('employment_status', ['active', 'terminated', 'on_leave', 'suspended'])->default('active');
            $table->string('job_title', 191);
            $table->string('department', 191)->nullable();
            $table->string('employment_type', 50)->default('full_time'); // full_time, part_time, contract, intern
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->string('salary_currency', 3)->default('KES'); // Default to KES, will be updated per tenant
            $table->enum('pay_frequency', ['weekly', 'bi_weekly', 'monthly', 'quarterly', 'annually'])->default('monthly');
            $table->string('bank_name', 191)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_routing_number', 50)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->string('social_security_number', 50)->nullable();
            $table->json('emergency_contact')->nullable(); // JSON for emergency contact details
            $table->json('benefits')->nullable(); // JSON for benefits configuration
            $table->json('deductions')->nullable(); // JSON for deductions configuration
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['tenant_id', 'employee_id'], 'idx_employees_tenant_employee');
            $table->index(['tenant_id', 'employment_status'], 'idx_employees_tenant_status');
            $table->index(['tenant_id', 'department'], 'idx_employees_tenant_department');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};