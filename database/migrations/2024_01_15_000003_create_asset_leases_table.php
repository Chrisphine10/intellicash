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
        Schema::create('asset_leases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('member_id');
            $table->string('lease_number', 191)->unique();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('daily_rate', 10, 2);
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->decimal('deposit_amount', 10, 2)->default(0);
            $table->string('status', 50)->default('active'); // active, completed, cancelled, overdue
            $table->text('terms_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        // Add foreign key constraints only if referenced tables exist
        if (Schema::hasTable('tenants')) {
            Schema::table('asset_leases', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('assets')) {
            Schema::table('asset_leases', function (Blueprint $table) {
                $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('members')) {
            Schema::table('asset_leases', function (Blueprint $table) {
                $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('users')) {
            Schema::table('asset_leases', function (Blueprint $table) {
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // Add indexes
        Schema::table('asset_leases', function (Blueprint $table) {
            $table->index(['tenant_id', 'status'], 'idx_leases_tenant_status');
            $table->index(['asset_id', 'status'], 'idx_leases_asset_status');
            $table->index(['member_id', 'status'], 'idx_leases_member_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_leases');
    }
};
