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
        // Add cycle_id to vsla_meetings table
        Schema::table('vsla_meetings', function (Blueprint $table) {
            $table->unsignedBigInteger('cycle_id')->nullable()->after('tenant_id');
            $table->foreign('cycle_id')->references('id')->on('vsla_cycles')->onDelete('cascade');
            $table->index('cycle_id');
        });

        // Add cycle_id to vsla_transactions table
        Schema::table('vsla_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('cycle_id')->nullable()->after('tenant_id');
            $table->foreign('cycle_id')->references('id')->on('vsla_cycles')->onDelete('cascade');
            $table->index('cycle_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove cycle_id from vsla_transactions table
        Schema::table('vsla_transactions', function (Blueprint $table) {
            $table->dropForeign(['cycle_id']);
            $table->dropIndex(['cycle_id']);
            $table->dropColumn('cycle_id');
        });

        // Remove cycle_id from vsla_meetings table
        Schema::table('vsla_meetings', function (Blueprint $table) {
            $table->dropForeign(['cycle_id']);
            $table->dropIndex(['cycle_id']);
            $table->dropColumn('cycle_id');
        });
    }
};
