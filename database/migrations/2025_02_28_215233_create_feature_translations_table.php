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
        Schema::create('feature_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('feature_id');
            $table->string('locale');
            $table->string('title');
            $table->text('content');

            $table->unique(['feature_id', 'locale']);
            $table->foreign('feature_id')->references('id')->on('features')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_translations');
    }
};
