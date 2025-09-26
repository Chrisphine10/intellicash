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
        Schema::table('packages', function (Blueprint $table) {
            // Advanced module limits
            $table->string('loan_limit')->nullable()->after('account_limit')->comment('Maximum number of loans');
            $table->string('asset_limit')->nullable()->after('loan_limit')->comment('Maximum number of assets');
            $table->string('election_limit')->nullable()->after('asset_limit')->comment('Maximum number of elections');
            $table->string('employee_limit')->nullable()->after('election_limit')->comment('Maximum number of employees');
            
            // Advanced features
            $table->tinyInteger('vsla_enabled')->default(0)->after('member_portal')->comment('1 = Yes | 0 = No');
            $table->tinyInteger('asset_management_enabled')->default(0)->after('vsla_enabled')->comment('1 = Yes | 0 = No');
            $table->tinyInteger('payroll_enabled')->default(0)->after('asset_management_enabled')->comment('1 = Yes | 0 = No');
            $table->tinyInteger('voting_enabled')->default(0)->after('payroll_enabled')->comment('1 = Yes | 0 = No');
            $table->tinyInteger('api_enabled')->default(0)->after('voting_enabled')->comment('1 = Yes | 0 = No');
            $table->tinyInteger('qr_code_enabled')->default(0)->after('api_enabled')->comment('1 = Yes | 0 = No');
            $table->tinyInteger('esignature_enabled')->default(0)->after('qr_code_enabled')->comment('1 = Yes | 0 = No');
            
            // Storage and file limits
            $table->integer('storage_limit_mb')->default(100)->after('esignature_enabled')->comment('Storage limit in MB');
            $table->integer('file_upload_limit_mb')->default(10)->after('storage_limit_mb')->comment('Max file upload size in MB');
            
            // Support and priority
            $table->tinyInteger('priority_support')->default(0)->after('file_upload_limit_mb')->comment('1 = Yes | 0 = No');
            $table->tinyInteger('custom_branding')->default(0)->after('priority_support')->comment('1 = Yes | 0 = No');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn([
                'loan_limit',
                'asset_limit', 
                'election_limit',
                'employee_limit',
                'vsla_enabled',
                'asset_management_enabled',
                'payroll_enabled',
                'voting_enabled',
                'api_enabled',
                'qr_code_enabled',
                'esignature_enabled',
                'storage_limit_mb',
                'file_upload_limit_mb',
                'priority_support',
                'custom_branding'
            ]);
        });
    }
};
