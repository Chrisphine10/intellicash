<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ShowPreservedData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:show-preserved-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show what data will be preserved after clearing transaction data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('📋 Data that will be PRESERVED after clearing transaction data:');
        $this->info('');

        // User and Authentication Data
        $this->info('👥 USER & AUTHENTICATION DATA:');
        $this->showTableData('users', 'User Accounts & Logins');
        $this->showTableData('roles', 'User Roles');
        $this->showTableData('permissions', 'System Permissions');
        $this->showTableData('model_has_roles', 'User Role Assignments');
        $this->showTableData('model_has_permissions', 'User Permission Assignments');

        // Member Data
        $this->info('');
        $this->info('👤 MEMBER DATA:');
        $this->showTableData('members', 'Member Profiles');
        $this->showTableData('member_documents', 'Member Documents');
        $this->showTableData('custom_fields', 'Custom Fields');

        // System Configuration
        $this->info('');
        $this->info('⚙️  SYSTEM CONFIGURATION:');
        $this->showTableData('tenants', 'Tenants');
        $this->showTableData('branches', 'Branches');
        $this->showTableData('currencies', 'Currencies');
        $this->showTableData('settings', 'System Settings');
        $this->showTableData('setting_translations', 'Setting Translations');
        $this->showTableData('tenant_settings', 'Tenant Settings');

        // Products and Types
        $this->info('');
        $this->info('📦 PRODUCTS & TYPES:');
        $this->showTableData('savings_products', 'Savings Products');
        $this->showTableData('loan_products', 'Loan Products');
        $this->showTableData('transaction_categories', 'Transaction Categories');
        $this->showTableData('expense_categories', 'Expense Categories');
        $this->showTableData('deposit_methods', 'Deposit Methods');
        $this->showTableData('withdraw_methods', 'Withdraw Methods');

        // Payment and Gateway Data
        $this->info('');
        $this->info('💳 PAYMENT & GATEWAY DATA:');
        $this->showTableData('payment_gateways', 'Payment Gateways');
        $this->showTableData('automatic_gateways', 'Automatic Gateways');
        $this->showTableData('charge_limits', 'Charge Limits');

        // VSLA Configuration
        $this->info('');
        $this->info('🏘️  VSLA CONFIGURATION:');
        $this->showTableData('vsla_settings', 'VSLA Settings');

        // Website and Content
        $this->info('');
        $this->info('🌐 WEBSITE & CONTENT:');
        $this->showTableData('pages', 'Website Pages');
        $this->showTableData('page_translations', 'Page Translations');
        $this->showTableData('posts', 'Blog Posts');
        $this->showTableData('post_comments', 'Post Comments');
        $this->showTableData('faqs', 'FAQs');
        $this->showTableData('faq_translations', 'FAQ Translations');
        $this->showTableData('features', 'Features');
        $this->showTableData('feature_translations', 'Feature Translations');
        $this->showTableData('testimonials', 'Testimonials');
        $this->showTableData('testimonial_translations', 'Testimonial Translations');
        $this->showTableData('teams', 'Team Members');
        $this->showTableData('team_translations', 'Team Translations');
        $this->showTableData('brands', 'Brands');

        // Email and Communication
        $this->info('');
        $this->info('📧 EMAIL & COMMUNICATION:');
        $this->showTableData('email_templates', 'Email Templates');
        $this->showTableData('email_subscribers', 'Email Subscribers');
        $this->showTableData('messages', 'Messages');
        $this->showTableData('message_attachments', 'Message Attachments');
        $this->showTableData('contact_messages', 'Contact Messages');

        // System Tables
        $this->info('');
        $this->info('🔧 SYSTEM TABLES:');
        $this->showTableData('packages', 'Packages');
        $this->showTableData('subscription_payments', 'Subscription Payments');
        $this->showTableData('jobs', 'Queue Jobs');
        $this->showTableData('cache', 'Cache Data');
        $this->showTableData('failed_jobs', 'Failed Jobs');
        $this->showTableData('migrations', 'Migration History');
        $this->showTableData('password_resets', 'Password Reset Tokens');
        $this->showTableData('personal_access_tokens', 'Personal Access Tokens');

        $this->info('');
        $this->info('✅ All the above data will be preserved during transaction data cleanup.');
        $this->info('🗑️  Only transaction-related data will be removed.');
    }

    /**
     * Show table data count
     */
    private function showTableData($table, $description)
    {
        if (Schema::hasTable($table)) {
            $count = DB::table($table)->count();
            $this->line("  ✓ {$description}: {$count} records");
        } else {
            $this->line("  ✗ {$description}: Table does not exist");
        }
    }
}
