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
        Schema::create('esignature_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('document_type')->default('contract'); // contract, agreement, form, etc.
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_size');
            $table->string('file_type');
            $table->string('status')->default('draft'); // draft, sent, signed, expired, cancelled
            $table->text('custom_message')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();
            $table->string('sender_company')->nullable();
            $table->json('signers')->nullable(); // Array of signer information
            $table->json('fields')->nullable(); // Document fields configuration
            $table->json('signature_positions')->nullable(); // Where signatures should be placed
            $table->datetime('expires_at')->nullable();
            $table->datetime('sent_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('esignature_documents');
    }
};
