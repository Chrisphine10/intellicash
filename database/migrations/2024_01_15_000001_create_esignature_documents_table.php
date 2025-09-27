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
        // Only create the table if it doesn't exist
        if (!Schema::hasTable('esignature_documents')) {
            Schema::create('esignature_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->string('document_type', 50)->default('contract'); // contract, agreement, form, etc.
                $table->string('file_path', 500);
                $table->string('file_name', 191);
                $table->string('file_size', 50);
                $table->string('file_type', 50);
                $table->string('status', 50)->default('draft'); // draft, sent, signed, expired, cancelled
                $table->text('custom_message')->nullable();
                $table->string('sender_name', 191)->nullable();
                $table->string('sender_email', 191)->nullable();
                $table->string('sender_company', 191)->nullable();
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
                $table->index(['tenant_id', 'status'], 'idx_esignature_docs_tenant_status');
                $table->index(['tenant_id', 'created_by'], 'idx_esignature_docs_tenant_created');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('esignature_documents');
    }
};
