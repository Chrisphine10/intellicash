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
        // Drop advanced loan management tables
        Schema::dropIfExists('advanced_loan_applications');
        Schema::dropIfExists('loan_terms_and_privacy');
        Schema::dropIfExists('legal_templates');
        
        // Remove advanced loan management column from tenants table
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('advanced_loan_management_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as it removes data
        // If needed, the tables would need to be recreated from scratch
    }
};
