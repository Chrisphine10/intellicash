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
        Schema::create('esignature_signatures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('signer_email');
            $table->string('signer_name')->nullable();
            $table->string('signer_phone')->nullable();
            $table->string('signer_company')->nullable();
            $table->string('signature_token')->unique();
            $table->string('status')->default('pending'); // pending, signed, declined, expired
            $table->text('signature_data')->nullable(); // Base64 encoded signature image
            $table->text('signature_type')->nullable(); // drawn, typed, uploaded
            $table->json('filled_fields')->nullable(); // Fields filled by signer
            $table->json('signature_metadata')->nullable(); // Additional signature data
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser_info')->nullable();
            $table->string('device_info')->nullable();
            $table->datetime('sent_at')->nullable();
            $table->datetime('viewed_at')->nullable();
            $table->datetime('signed_at')->nullable();
            $table->datetime('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('esignature_documents')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['document_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index('signature_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('esignature_signatures');
    }
};
