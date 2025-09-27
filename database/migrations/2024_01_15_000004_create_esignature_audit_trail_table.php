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
        Schema::create('esignature_audit_trail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('signature_id')->nullable();
            $table->unsignedBigInteger('tenant_id');
            $table->string('action', 50); // created, sent, viewed, signed, declined, expired, etc.
            $table->string('actor_type', 50)->nullable(); // user, signer, system
            $table->string('actor_email', 191)->nullable();
            $table->string('actor_name', 191)->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional action data
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser_info', 191)->nullable();
            $table->string('device_info', 191)->nullable();
            $table->string('location', 191)->nullable();
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('esignature_documents')->onDelete('cascade');
            $table->foreign('signature_id')->references('id')->on('esignature_signatures')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['document_id', 'action'], 'idx_audit_doc_action');
            $table->index(['tenant_id', 'created_at'], 'idx_audit_tenant_date');
            $table->index('actor_email', 'idx_audit_actor_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('esignature_audit_trail');
    }
};
