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
        Schema::create('elections', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['single_winner', 'multi_position', 'referendum']);
            $table->enum('voting_mechanism', ['majority', 'ranked_choice', 'weighted']);
            $table->enum('privacy_mode', ['private', 'public', 'hybrid']);
            $table->boolean('allow_abstain')->default(true);
            $table->boolean('weighted_voting')->default(false);
            $table->datetime('start_date');
            $table->datetime('end_date');
            $table->enum('status', ['draft', 'active', 'closed', 'cancelled'])->default('draft');
            $table->foreignId('position_id')->nullable()->constrained('voting_positions')->nullOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('elections');
    }
};
