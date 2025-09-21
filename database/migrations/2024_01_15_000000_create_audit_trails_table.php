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
        Schema::create('audit_trails', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 50); // created, updated, deleted, viewed, logged_in, etc.
            $table->string('auditable_type', 100); // Model class name (e.g., BankAccount, Transaction)
            $table->unsignedBigInteger('auditable_id'); // ID of the record being audited
            $table->json('old_values')->nullable(); // Previous values before change
            $table->json('new_values')->nullable(); // New values after change
            $table->string('user_type', 20)->default('user'); // user, member, system_admin
            $table->unsignedBigInteger('user_id')->nullable(); // ID of user who performed action
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('method', 10)->nullable(); // GET, POST, PUT, DELETE
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional context data
            $table->string('session_id', 100)->nullable();
            $table->timestamp('created_at');
            
            // Tenant isolation
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            
            // Indexes for performance
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_type', 'user_id']);
            $table->index(['event_type']);
            $table->index(['created_at']);
            $table->index(['tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_trails');
    }
};
