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
        Schema::create('vsla_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('member_id');
            $table->enum('role', ['chairperson', 'treasurer', 'secretary'])->index();
            $table->timestamp('assigned_at')->useCurrent();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Ensure unique active role assignments per tenant
            $table->unique(['tenant_id', 'member_id', 'role', 'is_active'], 'unique_active_role_assignment');
        });

        // Add foreign key constraints only if referenced tables exist
        if (Schema::hasTable('tenants')) {
            Schema::table('vsla_role_assignments', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('members')) {
            Schema::table('vsla_role_assignments', function (Blueprint $table) {
                $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('users')) {
            Schema::table('vsla_role_assignments', function (Blueprint $table) {
                $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vsla_role_assignments');
    }
};
