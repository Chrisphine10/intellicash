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
        Schema::create('advanced_loan_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number', 50)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained('members')->cascadeOnDelete();
            
            // Application Details
            $table->enum('application_type', ['business_loan', 'value_addition_enterprise', 'startup_loan'])->default('business_loan');
            $table->date('application_date');
            $table->decimal('requested_amount', 15, 2);
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->text('loan_purpose');
            $table->text('business_description');
            $table->enum('business_type', ['retail', 'manufacturing', 'service', 'agriculture', 'technology', 'construction', 'hospitality', 'transport', 'other'])->default('other');
            $table->string('business_name', 255);
            $table->string('business_registration_number', 100)->nullable();
            $table->date('business_start_date')->nullable();
            $table->integer('number_of_employees')->nullable();
            $table->decimal('monthly_revenue', 15, 2)->nullable();
            $table->decimal('monthly_expenses', 15, 2)->nullable();
            
            // Personal Information
            $table->string('applicant_name', 255);
            $table->string('applicant_email', 255);
            $table->string('applicant_phone', 50);
            $table->text('applicant_address');
            $table->string('applicant_id_number', 50)->nullable();
            $table->date('applicant_dob')->nullable();
            $table->enum('applicant_marital_status', ['single', 'married', 'divorced', 'widowed'])->default('single');
            $table->integer('applicant_dependents')->default(0);
            
            // Employment Information
            $table->string('employment_status', 50)->default('self_employed');
            $table->string('employer_name', 255)->nullable();
            $table->string('job_title', 255)->nullable();
            $table->decimal('monthly_income', 15, 2)->nullable();
            $table->integer('employment_years')->nullable();
            
            // Collateral Information
            $table->enum('collateral_type', ['bank_statement', 'payroll', 'property', 'vehicle', 'equipment', 'inventory', 'guarantor'])->default('bank_statement');
            $table->text('collateral_description')->nullable();
            $table->decimal('collateral_value', 15, 2)->nullable();
            $table->json('collateral_documents')->nullable();
            
            // Guarantor Information
            $table->json('guarantor_details')->nullable();
            
            // Documents
            $table->json('business_documents')->nullable(); // Business registration, tax certificates, etc.
            $table->json('financial_documents')->nullable(); // Bank statements, financial statements
            $table->json('personal_documents')->nullable(); // ID, proof of address, etc.
            
            // Application Status
            $table->enum('status', ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'cancelled', 'disbursed'])->default('draft');
            $table->text('review_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            
            // Risk Assessment
            $table->integer('credit_score')->nullable();
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('medium');
            $table->text('risk_factors')->nullable();
            $table->text('mitigation_measures')->nullable();
            
            // Additional Information
            $table->text('additional_information')->nullable();
            $table->json('custom_fields')->nullable();
            
            // System Fields
            $table->foreignId('created_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['applicant_id', 'application_date']);
            $table->index('application_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advanced_loan_applications');
    }
};
