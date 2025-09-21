<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExpenseCategory;
use App\Models\Tenant;

class TestExpenseCategoriesCreation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vsla:test-expense-categories {--tenant-id=1} {--test : Test mode only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test VSLA expense categories creation functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->option('tenant-id');
        $testMode = $this->option('test');
        
        if ($testMode) {
            $this->info('Running in TEST mode - no categories will be created');
        }

        $this->info('Testing VSLA Expense Categories Creation...');
        $this->info("Tenant ID: {$tenantId}");

        try {
            // Get tenant
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("Tenant with ID {$tenantId} not found");
                return 1;
            }

            $this->info("Tenant: {$tenant->name}");

            // Check current expense categories
            $currentCategories = ExpenseCategory::where('tenant_id', $tenantId)->count();
            $this->info("Current expense categories: {$currentCategories}");

            if ($testMode) {
                $this->info('✅ Test mode - would create default expense categories');
                $this->showDefaultCategories();
                return 0;
            }

            // Test the expense category creation method
            $controller = new \App\Http\Controllers\VslaSettingsController();
            $reflection = new \ReflectionClass($controller);
            $method = $reflection->getMethod('createDefaultExpenseCategories');
            $method->setAccessible(true);
            
            $createdCount = $method->invoke($controller, $tenant);

            $this->info("✅ Created {$createdCount} new expense categories!");

            // Show all expense categories
            $allCategories = ExpenseCategory::where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get();

            $this->info('');
            $this->info('📋 All Expense Categories:');
            foreach ($allCategories as $category) {
                $this->line("  • {$category->name} - {$category->description}");
            }

        } catch (\Exception $e) {
            $this->error('Test failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    /**
     * Show the default categories that would be created
     */
    private function showDefaultCategories()
    {
        $defaultCategories = [
            'Administrative Expenses',
            'Staff Salaries & Benefits',
            'Office Rent & Utilities',
            'Office Supplies & Equipment',
            'Training & Development',
            'Marketing & Promotion',
            'Legal & Professional Fees',
            'Insurance & Security',
            'Transportation & Travel',
            'Bank Charges & Fees',
            'VSLA Meeting Expenses',
            'Community Development',
            'Emergency Fund',
            'Technology & IT',
            'Miscellaneous'
        ];

        $this->info('');
        $this->info('📋 Default Expense Categories:');
        foreach ($defaultCategories as $category) {
            $this->line("  • {$category}");
        }
    }
}
