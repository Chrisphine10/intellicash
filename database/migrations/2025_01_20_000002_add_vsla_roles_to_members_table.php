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
        Schema::table('members', function (Blueprint $table) {
            // Add VSLA role fields
            $table->enum('vsla_role', ['chairperson', 'treasurer', 'secretary', 'member'])->default('member')->after('custom_fields');
            $table->boolean('is_vsla_chairperson')->default(false)->after('vsla_role');
            $table->boolean('is_vsla_treasurer')->default(false)->after('is_vsla_chairperson');
            $table->boolean('is_vsla_secretary')->default(false)->after('is_vsla_treasurer');
        });
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
