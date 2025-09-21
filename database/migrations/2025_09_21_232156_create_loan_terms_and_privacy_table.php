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
        Schema::create('loan_terms_and_privacy', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('loan_product_id')->nullable(); // null for general terms
            $table->string('title')->default('Loan Terms and Conditions');
            $table->longText('terms_and_conditions');
            $table->longText('privacy_policy');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('version')->default('1.0');
            $table->timestamp('effective_date')->nullable();
            $table->timestamp('expiry_date')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('loan_product_id')->references('id')->on('loan_products')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->unique(['tenant_id', 'loan_product_id', 'version']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['loan_product_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_terms_and_privacy');
    }
};
