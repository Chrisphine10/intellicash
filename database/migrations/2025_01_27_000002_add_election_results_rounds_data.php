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
        Schema::table('election_results', function (Blueprint $table) {
            // Add field to store round-by-round data for ranked choice voting
            $table->json('rounds_data')->nullable()->after('rank');
            
            // Add indexes for better performance
            $table->index(['is_winner', 'election_id']);
            $table->index(['percentage', 'election_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('election_results', function (Blueprint $table) {
            $table->dropIndex(['is_winner', 'election_id']);
            $table->dropIndex(['percentage', 'election_id']);
            $table->dropColumn('rounds_data');
        });
    }
};
