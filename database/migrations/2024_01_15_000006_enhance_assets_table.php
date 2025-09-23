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
        Schema::table('assets', function (Blueprint $table) {
            // Rename purchase_price to purchase_value for consistency
            $table->renameColumn('purchase_price', 'purchase_value');
            
            // Add depreciation and valuation fields
            $table->string('depreciation_method')->nullable()->after('current_value'); // straight_line, declining_balance, none
            $table->integer('useful_life')->nullable()->after('depreciation_method'); // in years
            $table->decimal('salvage_value', 10, 2)->nullable()->after('useful_life');
            
            // Add indexes for better performance
            $table->index(['tenant_id', 'is_leasable', 'status']);
            $table->index(['tenant_id', 'category_id']);
            $table->index(['tenant_id', 'purchase_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Drop the new columns
            $table->dropColumn(['depreciation_method', 'useful_life', 'salvage_value']);
            
            // Rename back to original
            $table->renameColumn('purchase_value', 'purchase_price');
            
            // Drop indexes
            $table->dropIndex(['tenant_id', 'is_leasable', 'status']);
            $table->dropIndex(['tenant_id', 'category_id']);
            $table->dropIndex(['tenant_id', 'purchase_date']);
        });
    }
};
