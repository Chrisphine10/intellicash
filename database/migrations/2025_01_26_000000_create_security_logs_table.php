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
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 50);
            $table->text('description');
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('severity', 20)->default('warning');
            $table->timestamp('created_at');
            
            $table->index(['event_type', 'created_at'], 'idx_security_event_date');
            $table->index(['user_id', 'created_at'], 'idx_security_user_date');
            $table->index(['tenant_id', 'created_at'], 'idx_security_tenant_date');
            $table->index(['ip_address', 'created_at'], 'idx_security_ip_date');
            $table->index('severity', 'idx_security_severity');
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};
