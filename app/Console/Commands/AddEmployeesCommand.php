<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\Tenant;

class AddEmployeesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employees:add';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add sample employees to the system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get the first tenant
        $tenant = Tenant::first();
        
        if (!$tenant) {
            $this->error('No tenant found. Please create a tenant first.');
            return 1;
        }

        $this->info('Creating sample employees for tenant: ' . $tenant->name);

        $employees = [
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'middle_name' => 'Michael',
                'email' => 'john.doe@company.com',
                'phone' => '+1234567890',
                'address' => '123 Main Street, City, State 12345',
                'date_of_birth' => '1985-05-15',
                'gender' => 'male',
                'national_id' => '123456789',
                'hire_date' => '2023-01-15',
                'job_title' => 'Software Developer',
                'department' => 'Engineering',
                'employment_type' => 'full_time',
                'basic_salary' => 75000,
                'salary_currency' => 'USD',
                'pay_frequency' => 'monthly',
                'bank_name' => 'First National Bank',
                'bank_account_number' => '1234567890',
                'bank_routing_number' => '021000021',
                'tax_id' => 'TAX123456',
                'social_security_number' => '123-45-6789',
                'emergency_contact' => 'Jane Doe - +1234567891',
                'employment_status' => 'active',
                'is_active' => true,
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'middle_name' => 'Elizabeth',
                'email' => 'jane.smith@company.com',
                'phone' => '+1234567892',
                'address' => '456 Oak Avenue, City, State 12345',
                'date_of_birth' => '1990-08-22',
                'gender' => 'female',
                'national_id' => '987654321',
                'hire_date' => '2023-03-01',
                'job_title' => 'Marketing Manager',
                'department' => 'Marketing',
                'employment_type' => 'full_time',
                'basic_salary' => 65000,
                'salary_currency' => 'USD',
                'pay_frequency' => 'monthly',
                'bank_name' => 'Second National Bank',
                'bank_account_number' => '0987654321',
                'bank_routing_number' => '021000022',
                'tax_id' => 'TAX987654',
                'social_security_number' => '987-65-4321',
                'emergency_contact' => 'Bob Smith - +1234567893',
                'employment_status' => 'active',
                'is_active' => true,
            ],
            [
                'first_name' => 'Michael',
                'last_name' => 'Johnson',
                'middle_name' => 'David',
                'email' => 'michael.johnson@company.com',
                'phone' => '+1234567894',
                'address' => '789 Pine Street, City, State 12345',
                'date_of_birth' => '1988-12-10',
                'gender' => 'male',
                'national_id' => '456789123',
                'hire_date' => '2023-06-15',
                'job_title' => 'Sales Representative',
                'department' => 'Sales',
                'employment_type' => 'full_time',
                'basic_salary' => 55000,
                'salary_currency' => 'USD',
                'pay_frequency' => 'monthly',
                'bank_name' => 'Third National Bank',
                'bank_account_number' => '4567891230',
                'bank_routing_number' => '021000023',
                'tax_id' => 'TAX456789',
                'social_security_number' => '456-78-9123',
                'emergency_contact' => 'Sarah Johnson - +1234567895',
                'employment_status' => 'active',
                'is_active' => true,
            ],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Williams',
                'middle_name' => 'Anne',
                'email' => 'sarah.williams@company.com',
                'phone' => '+1234567896',
                'address' => '321 Elm Street, City, State 12345',
                'date_of_birth' => '1992-03-25',
                'gender' => 'female',
                'national_id' => '789123456',
                'hire_date' => '2023-09-01',
                'job_title' => 'HR Specialist',
                'department' => 'Human Resources',
                'employment_type' => 'full_time',
                'basic_salary' => 60000,
                'salary_currency' => 'USD',
                'pay_frequency' => 'monthly',
                'bank_name' => 'Fourth National Bank',
                'bank_account_number' => '7891234560',
                'bank_routing_number' => '021000024',
                'tax_id' => 'TAX789123',
                'social_security_number' => '789-12-3456',
                'emergency_contact' => 'Tom Williams - +1234567897',
                'employment_status' => 'active',
                'is_active' => true,
            ],
            [
                'first_name' => 'David',
                'last_name' => 'Brown',
                'middle_name' => 'Robert',
                'email' => 'david.brown@company.com',
                'phone' => '+1234567898',
                'address' => '654 Maple Avenue, City, State 12345',
                'date_of_birth' => '1987-11-18',
                'gender' => 'male',
                'national_id' => '321654987',
                'hire_date' => '2023-12-01',
                'job_title' => 'Financial Analyst',
                'department' => 'Finance',
                'employment_type' => 'full_time',
                'basic_salary' => 70000,
                'salary_currency' => 'USD',
                'pay_frequency' => 'monthly',
                'bank_name' => 'Fifth National Bank',
                'bank_account_number' => '3216549870',
                'bank_routing_number' => '021000025',
                'tax_id' => 'TAX321654',
                'social_security_number' => '321-65-4987',
                'emergency_contact' => 'Lisa Brown - +1234567899',
                'employment_status' => 'active',
                'is_active' => true,
            ]
        ];

        $created = 0;
        $skipped = 0;

        foreach ($employees as $employeeData) {
            // Check if employee already exists
            $existingEmployee = Employee::where('tenant_id', $tenant->id)
                ->where('email', $employeeData['email'])
                ->first();

            if (!$existingEmployee) {
                $employee = Employee::create(array_merge($employeeData, [
                    'tenant_id' => $tenant->id,
                    'employee_id' => Employee::generateEmployeeId($tenant->id),
                    'created_by' => 1, // Assuming admin user ID is 1
                    'updated_by' => 1,
                ]));

                $this->info("✓ Created employee: {$employee->first_name} {$employee->last_name} (ID: {$employee->employee_id})");
                $created++;
            } else {
                $this->warn("⚠ Employee already exists: {$employeeData['first_name']} {$employeeData['last_name']}");
                $skipped++;
            }
        }

        $this->info("\nEmployee creation completed!");
        $this->info("Created: {$created} employees");
        $this->info("Skipped: {$skipped} employees");

        return 0;
    }
}
