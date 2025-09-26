<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add missing foreign key constraints to votes table (if not exists)
        try {
            Schema::table('votes', function (Blueprint $table) {
                // Ensure candidate belongs to the same election
                $table->foreign(['election_id', 'candidate_id'])
                      ->references(['election_id', 'id'])
                      ->on('candidates')
                      ->onDelete('cascade');
            });
        } catch (Exception $e) {
            // Foreign key might already exist, ignore error
        }

        // Add missing indexes for performance (if not exists)
        try {
            Schema::table('elections', function (Blueprint $table) {
                $table->index(['status', 'tenant_id']);
                $table->index(['start_date', 'end_date']);
                $table->index(['type', 'status']);
            });
        } catch (Exception $e) {
            // Indexes might already exist, ignore error
        }

        try {
            Schema::table('votes', function (Blueprint $table) {
                $table->index(['voted_at', 'tenant_id']);
                $table->index(['is_abstain', 'election_id']);
            });
        } catch (Exception $e) {
            // Indexes might already exist, ignore error
        }

        try {
            Schema::table('candidates', function (Blueprint $table) {
                $table->index(['is_active', 'election_id']);
                $table->index(['member_id', 'election_id']);
            });
        } catch (Exception $e) {
            // Indexes might already exist, ignore error
        }

        // Add check constraints for data integrity using raw SQL
        // Note: Laravel doesn't support check constraints directly in migrations
        try {
            DB::statement('ALTER TABLE elections ADD CONSTRAINT elections_start_before_end CHECK (start_date < end_date)');
            DB::statement("ALTER TABLE elections ADD CONSTRAINT elections_valid_status CHECK (status IN ('draft', 'active', 'closed', 'cancelled', 'calculation_failed'))");
            DB::statement('ALTER TABLE votes ADD CONSTRAINT votes_valid_security_score CHECK (security_score >= 0 AND security_score <= 100)');
            DB::statement('ALTER TABLE votes ADD CONSTRAINT votes_positive_weight CHECK (weight > 0)');
            DB::statement("ALTER TABLE candidates ADD CONSTRAINT candidates_valid_active_status CHECK (is_active IN (0, 1))");
        } catch (Exception $e) {
            // Constraints might already exist, ignore error
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->dropForeign(['election_id', 'candidate_id']);
        });

        Schema::table('elections', function (Blueprint $table) {
            $table->dropIndex(['status', 'tenant_id']);
            $table->dropIndex(['start_date', 'end_date']);
            $table->dropIndex(['type', 'status']);
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->dropIndex(['voted_at', 'tenant_id']);
            $table->dropIndex(['is_abstain', 'election_id']);
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'election_id']);
            $table->dropIndex(['member_id', 'election_id']);
        });

        // Drop check constraints
        try {
            DB::statement('ALTER TABLE elections DROP CONSTRAINT elections_start_before_end');
            DB::statement('ALTER TABLE elections DROP CONSTRAINT elections_valid_status');
            DB::statement('ALTER TABLE votes DROP CONSTRAINT votes_valid_security_score');
            DB::statement('ALTER TABLE votes DROP CONSTRAINT votes_positive_weight');
            DB::statement('ALTER TABLE candidates DROP CONSTRAINT candidates_valid_active_status');
        } catch (Exception $e) {
            // Constraints might not exist, ignore errors
        }
    }
};
