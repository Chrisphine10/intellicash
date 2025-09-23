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
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('choice')->nullable(); // For referendum votes (yes/no/abstain)
            $table->integer('rank')->nullable(); // For ranked choice voting
            $table->decimal('weight', 8, 2)->default(1.00); // For weighted voting
            $table->boolean('is_abstain')->default(false);
            $table->timestamp('voted_at');
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            // Ensure one vote per member per election
            $table->unique(['election_id', 'member_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
