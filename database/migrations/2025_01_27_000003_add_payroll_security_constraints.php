<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add check constraints for payroll_periods table
        DB::statement('ALTER TABLE payroll_periods ADD CONSTRAINT chk_payroll_periods_positive_amounts 
            CHECK (total_gross_pay >= 0 AND total_deductions >= 0 AND total_benefits >= 0 AND total_net_pay >= 0)');
        
        DB::statement('ALTER TABLE payroll_periods ADD CONSTRAINT chk_payroll_periods_positive_employees 
            CHECK (total_employees >= 0)');

        // Add check constraints for payroll_items table
        DB::statement('ALTER TABLE payroll_items ADD CONSTRAINT chk_payroll_items_positive_amounts 
            CHECK (basic_salary >= 0 AND overtime_hours >= 0 AND overtime_rate >= 0 AND overtime_pay >= 0 
            AND bonus >= 0 AND commission >= 0 AND allowances >= 0 AND other_earnings >= 0 
            AND gross_pay >= 0 AND income_tax >= 0 AND social_security >= 0 AND health_insurance >= 0 
            AND retirement_contribution >= 0 AND loan_deductions >= 0 AND other_deductions >= 0 
            AND total_deductions >= 0 AND health_benefits >= 0 AND retirement_benefits >= 0 
            AND other_benefits >= 0 AND total_benefits >= 0 AND net_pay >= 0)');

        // Add check constraints for payroll_deductions table
        DB::statement('ALTER TABLE payroll_deductions ADD CONSTRAINT chk_payroll_deductions_positive_amounts 
            CHECK (rate >= 0 AND rate <= 100 AND amount >= 0 AND minimum_amount >= 0 
            AND (maximum_amount IS NULL OR maximum_amount >= 0))');

        // Add check constraints for payroll_benefits table
        DB::statement('ALTER TABLE payroll_benefits ADD CONSTRAINT chk_payroll_benefits_positive_amounts 
            CHECK (rate >= 0 AND rate <= 100 AND amount >= 0 AND minimum_amount >= 0 
            AND (maximum_amount IS NULL OR maximum_amount >= 0))');

        // Add check constraints for employee_deductions table
        DB::statement('ALTER TABLE employee_deductions ADD CONSTRAINT chk_employee_deductions_positive_amounts 
            CHECK (rate >= 0 AND rate <= 100 AND amount >= 0 AND minimum_amount >= 0 
            AND (maximum_amount IS NULL OR maximum_amount >= 0))');

        // Add check constraints for employee_benefits table
        DB::statement('ALTER TABLE employee_benefits ADD CONSTRAINT chk_employee_benefits_positive_amounts 
            CHECK (rate >= 0 AND rate <= 100 AND amount >= 0 AND minimum_amount >= 0 
            AND (maximum_amount IS NULL OR maximum_amount >= 0))');

        // Add check constraints for employees table
        DB::statement('ALTER TABLE employees ADD CONSTRAINT chk_employees_positive_salary 
            CHECK (basic_salary >= 0)');

        // Add unique constraints to prevent duplicate codes within tenant
        DB::statement('ALTER TABLE payroll_deductions ADD CONSTRAINT chk_payroll_deductions_unique_code_per_tenant 
            UNIQUE (tenant_id, code)');

        DB::statement('ALTER TABLE payroll_benefits ADD CONSTRAINT chk_payroll_benefits_unique_code_per_tenant 
            UNIQUE (tenant_id, code)');

        // Add check constraint for date ranges
        DB::statement('ALTER TABLE payroll_periods ADD CONSTRAINT chk_payroll_periods_date_range 
            CHECK (end_date > start_date)');

        DB::statement('ALTER TABLE payroll_benefits ADD CONSTRAINT chk_payroll_benefits_date_range 
            CHECK (expiry_date IS NULL OR expiry_date > effective_date)');

        DB::statement('ALTER TABLE employee_deductions ADD CONSTRAINT chk_employee_deductions_date_range 
            CHECK (expiry_date IS NULL OR expiry_date > effective_date)');

        DB::statement('ALTER TABLE employee_benefits ADD CONSTRAINT chk_employee_benefits_date_range 
            CHECK (expiry_date IS NULL OR expiry_date > effective_date)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop check constraints
        DB::statement('ALTER TABLE payroll_periods DROP CONSTRAINT IF EXISTS chk_payroll_periods_positive_amounts');
        DB::statement('ALTER TABLE payroll_periods DROP CONSTRAINT IF EXISTS chk_payroll_periods_positive_employees');
        DB::statement('ALTER TABLE payroll_items DROP CONSTRAINT IF EXISTS chk_payroll_items_positive_amounts');
        DB::statement('ALTER TABLE payroll_deductions DROP CONSTRAINT IF EXISTS chk_payroll_deductions_positive_amounts');
        DB::statement('ALTER TABLE payroll_benefits DROP CONSTRAINT IF EXISTS chk_payroll_benefits_positive_amounts');
        DB::statement('ALTER TABLE employee_deductions DROP CONSTRAINT IF EXISTS chk_employee_deductions_positive_amounts');
        DB::statement('ALTER TABLE employee_benefits DROP CONSTRAINT IF EXISTS chk_employee_benefits_positive_amounts');
        DB::statement('ALTER TABLE employees DROP CONSTRAINT IF EXISTS chk_employees_positive_salary');
        
        // Drop unique constraints
        DB::statement('ALTER TABLE payroll_deductions DROP CONSTRAINT IF EXISTS chk_payroll_deductions_unique_code_per_tenant');
        DB::statement('ALTER TABLE payroll_benefits DROP CONSTRAINT IF EXISTS chk_payroll_benefits_unique_code_per_tenant');
        
        // Drop date range constraints
        DB::statement('ALTER TABLE payroll_periods DROP CONSTRAINT IF EXISTS chk_payroll_periods_date_range');
        DB::statement('ALTER TABLE payroll_benefits DROP CONSTRAINT IF EXISTS chk_payroll_benefits_date_range');
        DB::statement('ALTER TABLE employee_deductions DROP CONSTRAINT IF EXISTS chk_employee_deductions_date_range');
        DB::statement('ALTER TABLE employee_benefits DROP CONSTRAINT IF EXISTS chk_employee_benefits_date_range');
    }
};
