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
        // Only proceed if members table exists and has the state column
        if (Schema::hasTable('members') && Schema::hasColumn('members', 'state')) {
            Schema::table('members', function (Blueprint $table) {
                $table->renameColumn('state', 'county');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->renameColumn('county', 'state');
        });
    }
};
