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
            // Fix column name inconsistency: purchase_price -> purchase_value
            if (Schema::hasColumn('assets', 'purchase_price')) {
                $table->renameColumn('purchase_price', 'purchase_value');
            }
            
            // Add missing indexes for performance
            $table->index(['tenant_id', 'category_id', 'status'], 'idx_assets_tenant_category_status');
            $table->index(['tenant_id', 'is_leasable', 'status'], 'idx_assets_tenant_leasable_status');
            $table->index(['tenant_id', 'asset_code'], 'idx_assets_tenant_code');
            
            // Add unique constraint for asset codes per tenant
            $table->unique(['tenant_id', 'asset_code'], 'unique_asset_code_per_tenant');
        });
        
        Schema::table('asset_leases', function (Blueprint $table) {
            // Add missing indexes for performance
            $table->index(['tenant_id', 'asset_id', 'status'], 'idx_leases_tenant_asset_status');
            $table->index(['tenant_id', 'member_id', 'status'], 'idx_leases_tenant_member_status');
            $table->index(['start_date', 'end_date'], 'idx_leases_date_range');
        });
        
        Schema::table('asset_categories', function (Blueprint $table) {
            // Add missing indexes for performance
            $table->index(['tenant_id', 'is_active'], 'idx_categories_tenant_active');
            $table->index(['tenant_id', 'type'], 'idx_categories_tenant_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('idx_assets_tenant_category_status');
            $table->dropIndex('idx_assets_tenant_leasable_status');
            $table->dropIndex('idx_assets_tenant_code');
            $table->dropUnique('unique_asset_code_per_tenant');
            
            // Rename column back
            if (Schema::hasColumn('assets', 'purchase_value')) {
                $table->renameColumn('purchase_value', 'purchase_price');
            }
        });
        
        Schema::table('asset_leases', function (Blueprint $table) {
            $table->dropIndex('idx_leases_tenant_asset_status');
            $table->dropIndex('idx_leases_tenant_member_status');
            $table->dropIndex('idx_leases_date_range');
        });
        
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->dropIndex('idx_categories_tenant_active');
            $table->dropIndex('idx_categories_tenant_type');
        });
    }
};
