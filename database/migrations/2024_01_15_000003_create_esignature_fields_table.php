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
        if (!Schema::hasTable('esignature_fields')) {
            Schema::create('esignature_fields', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('document_id');
                $table->unsignedBigInteger('tenant_id');
                $table->string('field_type', 50); // text, signature, date, checkbox, dropdown, etc.
                $table->string('field_name', 191);
                $table->string('field_label', 191);
                $table->text('field_value')->nullable();
                $table->json('field_options')->nullable(); // For dropdowns, checkboxes, etc.
                $table->boolean('is_required')->default(false);
                $table->boolean('is_readonly')->default(false);
                $table->integer('position_x')->nullable();
                $table->integer('position_y')->nullable();
                $table->integer('width')->nullable();
                $table->integer('height')->nullable();
                $table->integer('page_number')->default(1);
                $table->string('assigned_to', 191)->nullable(); // Email of signer this field is assigned to
                $table->timestamps();

                $table->foreign('document_id')->references('id')->on('esignature_documents')->onDelete('cascade');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->index(['document_id', 'page_number'], 'idx_fields_doc_page');
                $table->index(['tenant_id', 'field_type'], 'idx_fields_tenant_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('esignature_fields');
    }
};
