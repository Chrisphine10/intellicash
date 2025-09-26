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
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('election_id');
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('candidate_id')->nullable();
            $table->string('choice')->nullable(); // For referendum votes (yes/no/abstain)
            $table->integer('rank')->nullable(); // For ranked choice voting
            $table->decimal('weight', 8, 2)->default(1.00); // For weighted voting
            $table->boolean('is_abstain')->default(false);
            $table->timestamp('voted_at');
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();
            
            // Ensure one vote per member per election
            $table->unique(['election_id', 'member_id']);
        });

        // Add foreign key constraints only if referenced tables exist
        if (Schema::hasTable('elections')) {
            Schema::table('votes', function (Blueprint $table) {
                $table->foreign('election_id')->references('id')->on('elections')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('members')) {
            Schema::table('votes', function (Blueprint $table) {
                $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('candidates')) {
            Schema::table('votes', function (Blueprint $table) {
                $table->foreign('candidate_id')->references('id')->on('candidates')->nullOnDelete();
            });
        }
        
        if (Schema::hasTable('tenants')) {
            Schema::table('votes', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
