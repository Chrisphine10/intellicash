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
        Schema::create('security_test_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('test_type');
            $table->json('test_results');
            $table->json('test_summary');
            $table->integer('total_tests');
            $table->integer('passed_tests');
            $table->integer('failed_tests');
            $table->float('success_rate');
            $table->integer('duration_seconds');
            $table->timestamp('test_started_at');
            $table->timestamp('test_completed_at');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'test_type']);
            $table->index('test_completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_test_results');
    }
};