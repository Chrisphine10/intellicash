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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('category_id');
            $table->string('name', 191);
            $table->string('asset_code', 191)->unique();
            $table->text('description')->nullable();
            $table->decimal('purchase_price', 15, 2);
            $table->decimal('current_value', 15, 2)->nullable();
            $table->date('purchase_date');
            $table->date('warranty_expiry')->nullable();
            $table->string('location', 191)->nullable();
            $table->string('status', 50)->default('active'); // active, inactive, maintenance, disposed
            $table->boolean('is_leasable')->default(false);
            $table->decimal('lease_rate', 10, 2)->nullable(); // daily rate for leasable assets
            $table->string('lease_rate_type', 50)->default('daily'); // daily, weekly, monthly
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // for storing additional asset-specific data
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('asset_categories')->onDelete('cascade');
            $table->index(['tenant_id', 'status'], 'idx_assets_tenant_status');
            $table->index(['tenant_id', 'is_leasable'], 'idx_assets_tenant_leasable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
