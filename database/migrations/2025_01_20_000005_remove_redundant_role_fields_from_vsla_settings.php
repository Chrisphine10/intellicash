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
        Schema::table('vsla_settings', function (Blueprint $table) {
            // Remove redundant role text fields since roles are now assigned to members
            $table->dropColumn(['chairperson_role', 'treasurer_role', 'secretary_role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vsla_settings', function (Blueprint $table) {
            // Add back the role text fields
            $table->string('chairperson_role', 100)->default('Chairperson');
            $table->string('treasurer_role', 100)->default('Treasurer');
            $table->string('secretary_role', 100)->default('Secretary');
        });
    }
};
