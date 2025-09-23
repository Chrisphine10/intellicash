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
            $table->string('action'); // created, sent, viewed, signed, declined, expired, etc.
            $table->string('actor_type')->nullable(); // user, signer, system
            $table->string('actor_email')->nullable();
            $table->string('actor_name')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional action data
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser_info')->nullable();
            $table->string('device_info')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('esignature_documents')->onDelete('cascade');
            $table->foreign('signature_id')->references('id')->on('esignature_signatures')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['document_id', 'action']);
            $table->index(['tenant_id', 'created_at']);
            $table->index('actor_email');
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
