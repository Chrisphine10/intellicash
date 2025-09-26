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
        Schema::table('employees', function (Blueprint $table) {
            // Drop old US-specific fields
            $table->dropColumn(['bank_routing_number', 'tax_id', 'social_security_number']);
            
            // Add African-specific fields (suitable for various African countries)
            $table->string('bank_code', 10)->nullable()->after('bank_account_number');
            $table->string('tax_number', 20)->nullable()->after('bank_code');
            $table->string('insurance_number', 20)->nullable()->after('tax_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Drop African-specific fields
            $table->dropColumn(['bank_code', 'tax_number', 'insurance_number']);
            
            // Restore old US-specific fields
            $table->string('bank_routing_number', 20)->nullable()->after('bank_account_number');
            $table->string('tax_id', 20)->nullable()->after('bank_routing_number');
            $table->string('social_security_number', 20)->nullable()->after('tax_id');
        });
    }
};
