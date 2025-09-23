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
        Schema::create('vsla_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('action', 50); // created, updated, deleted, approved, rejected
            $table->string('model_type', 100); // VslaTransaction, VslaCycle, VslaMeeting, etc.
            $table->unsignedBigInteger('model_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['tenant_id', 'model_type', 'model_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'action']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vsla_audit_logs');
    }
};
