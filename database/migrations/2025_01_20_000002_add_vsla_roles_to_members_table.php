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
        if (Schema::hasTable('members')) {
            Schema::table('members', function (Blueprint $table) {
                // Add VSLA role fields
                if (!Schema::hasColumn('members', 'vsla_role')) {
                    $table->enum('vsla_role', ['chairperson', 'treasurer', 'secretary', 'member'])->default('member');
                }
                if (!Schema::hasColumn('members', 'is_vsla_chairperson')) {
                    $table->boolean('is_vsla_chairperson')->default(false);
                }
                if (!Schema::hasColumn('members', 'is_vsla_treasurer')) {
                    $table->boolean('is_vsla_treasurer')->default(false);
                }
                if (!Schema::hasColumn('members', 'is_vsla_secretary')) {
                    $table->boolean('is_vsla_secretary')->default(false);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['vsla_role', 'is_vsla_chairperson', 'is_vsla_treasurer', 'is_vsla_secretary']);
        });
    }
};
