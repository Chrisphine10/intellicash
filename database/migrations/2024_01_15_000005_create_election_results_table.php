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
        Schema::create('election_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('choice')->nullable(); // For referendum results
            $table->integer('total_votes')->default(0);
            $table->decimal('percentage', 5, 2)->default(0.00);
            $table->integer('rank')->nullable(); // Final ranking
            $table->boolean('is_winner')->default(false);
            $table->text('calculation_details')->nullable(); // JSON for audit trail
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('election_results');
    }
};
