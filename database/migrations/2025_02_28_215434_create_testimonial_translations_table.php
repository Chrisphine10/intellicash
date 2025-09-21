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
        Schema::create('testimonial_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('testimonial_id');
            $table->string('locale');
            $table->string('name');
            $table->text('testimonial');

            $table->unique(['testimonial_id', 'locale']);
            $table->foreign('testimonial_id')->references('id')->on('testimonials')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonial_translations');
    }
};
