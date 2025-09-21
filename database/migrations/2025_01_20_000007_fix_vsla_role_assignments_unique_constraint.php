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
        Schema::table('vsla_role_assignments', function (Blueprint $table) {
            // Drop the existing unique constraint
            $table->dropUnique('unique_active_role_assignment');
            
            // Add a new unique constraint that only applies to active records
            $table->unique(['tenant_id', 'member_id', 'role'], 'unique_active_role_per_member');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vsla_role_assignments', function (Blueprint $table) {
            // Drop the new constraint
            $table->dropUnique('unique_active_role_per_member');
            
            // Add back the old constraint
            $table->unique(['tenant_id', 'member_id', 'role', 'is_active'], 'unique_active_role_assignment');
        });
    }
};
